
<?php
require_once 'config.php';
require_admin();

$page_title = 'Reportes';

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$estado = $_GET['estado'] ?? '';

if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = '';
}

if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = '';
}

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    flash('La fecha desde no puede ser posterior a la fecha hasta.', 'error');
    $desde = '';
    $hasta = '';
}

$cats = $pdo->query(
    "SELECT * FROM categories ORDER BY name"
)->fetchAll();

$conditions = [];
$params = [];

if ($categoria !== '' && ctype_digit($categoria)) {
    $conditions[] = 'm.category_id = ?';
    $params[] = (int)$categoria;
} else {
    $categoria = '';
}

if (in_array($estado, ['Bueno', 'Regular', 'Dañado/Baja'], true)) {
    $conditions[] = 'm.condition_status = ?';
    $params[] = $estado;
} else {
    $estado = '';
}

$where = $conditions
    ? ' WHERE ' . implode(' AND ', $conditions)
    : '';

$sql = "SELECT m.code, m.name, m.total_qty, m.available_qty,
               m.condition_status, c.name AS category
        FROM materials m
        LEFT JOIN categories c ON c.id = m.category_id
        $where
        ORDER BY m.name";

$s = $pdo->prepare($sql);
$s->execute($params);
$stock = $s->fetchAll();

// MATERIALES DAÑADOS
$damaged = array_filter($stock, function($r) {
    return $r['condition_status'] === 'Dañado/Baja';
});

// HISTORIAL
$loanConditions = [];
$loanParams = [];

if ($desde !== '') {
    $loanConditions[] = 'l.loan_date >= ?';
    $loanParams[] = $desde . ' 00:00:00';
}

if ($hasta !== '') {
    $loanConditions[] = 'l.loan_date < DATE_ADD(?, INTERVAL 1 DAY)';
    $loanParams[] = $hasta;
}

if ($categoria !== '') {
    $loanConditions[] = 'm.category_id = ?';
    $loanParams[] = (int)$categoria;
}

if ($estado !== '') {
    $loanConditions[] = 'm.condition_status = ?';
    $loanParams[] = $estado;
}

$loanWhere = $loanConditions
    ? ' WHERE ' . implode(' AND ', $loanConditions)
    : '';

$historySql = "
    SELECT l.id, u.full_name AS teacher,
           m.name AS material, l.quantity,
           l.loan_date, l.returned_at, l.status
    FROM loans l
    JOIN users u ON u.id = l.teacher_id
    JOIN materials m ON m.id = l.material_id
    $loanWhere
    ORDER BY l.loan_date DESC
";

$s = $pdo->prepare($historySql);
$s->execute($loanParams);
$history = $s->fetchAll();

$total = array_sum(array_column($stock, 'total_qty'));
$available = array_sum(array_column($stock, 'available_qty'));

require 'header.php';
?>

<h1>Reportes del inventario</h1>

<p class="muted">
Consulta de existencias, materiales dañados e historial de préstamos.
</p>

<div class="panel">

<h2>Filtros de búsqueda</h2>

<form method="GET" class="form-grid">

<div>
<label>Fecha desde</label>
<input type="date" name="desde" value="<?= e($desde) ?>">
</div>

<div>
<label>Fecha hasta</label>
<input type="date" name="hasta" value="<?= e($hasta) ?>">
</div>

<div>
<label>Tipo de material</label>

<select name="categoria">
<option value="">Todas las categorías</option>

<?php foreach ($cats as $c): ?>
<option value="<?= $c['id'] ?>"
<?= $categoria == $c['id'] ? 'selected' : '' ?>>
<?= e($c['name']) ?>
</option>
<?php endforeach; ?>

</select>
</div>

<div>
<label>Estado físico</label>

<select name="estado">
<option value="">Todos los estados</option>

<option value="Bueno" <?= $estado === 'Bueno' ? 'selected' : '' ?>>
Bueno
</option>

<option value="Regular" <?= $estado === 'Regular' ? 'selected' : '' ?>>
Regular
</option>

<option value="Dañado/Baja" <?= $estado === 'Dañado/Baja' ? 'selected' : '' ?>>
Dañado/Baja
</option>

</select>
</div>

<div class="span-all actions">

<button class="btn primary">Filtrar</button>

<a href="reports.php" class="btn">
Limpiar filtros
</a>

<a href="exportar_pdf.php?<?= e(http_build_query($_GET)) ?>"
target="_blank" class="btn">
Exportar PDF
</a>

<a href="exportar_excel.php?<?= e(http_build_query($_GET)) ?>"
class="btn">
Exportar Excel
</a>

</div>

</form>
</div>

<div class="stats">

<div class="stat">
<span>Unidades registradas</span>
<strong><?= $total ?></strong>
</div>

<div class="stat">
<span>Unidades disponibles</span>
<strong><?= $available ?></strong>
</div>

<div class="stat">
<span>Materiales dañados</span>
<strong><?= count($damaged) ?></strong>
</div>

<div class="stat">
<span>Registros de préstamos</span>
<strong><?= count($history) ?></strong>
</div>

</div>

<div class="panel">

<h2>Existencias actuales</h2>

<div class="table-wrap">
<table>

<tr>
<th>Código</th>
<th>Material</th>
<th>Categoría</th>
<th>Total</th>
<th>Disponible</th>
<th>Estado</th>
</tr>

<?php foreach ($stock as $r): ?>

<tr>
<td><?= e($r['code']) ?></td>
<td><?= e($r['name']) ?></td>
<td><?= e($r['category'] ?? '—') ?></td>
<td><?= $r['total_qty'] ?></td>
<td><?= $r['available_qty'] ?></td>
<td><?= e($r['condition_status']) ?></td>
</tr>

<?php endforeach; ?>

<?php if (!$stock): ?>
<tr><td colspan="6">No existen materiales para estos filtros.</td></tr>
<?php endif; ?>

</table>
</div>
</div>

<div class="panel">

<h2>Materiales dañados o dados de baja</h2>

<table>

<tr>
<th>Código</th>
<th>Material</th>
<th>Total</th>
<th>Disponible</th>
</tr>

<?php foreach ($damaged as $r): ?>

<tr>
<td><?= e($r['code']) ?></td>
<td><?= e($r['name']) ?></td>
<td><?= $r['total_qty'] ?></td>
<td><?= $r['available_qty'] ?></td>
</tr>

<?php endforeach; ?>

<?php if (!$damaged): ?>
<tr><td colspan="4">No hay materiales dañados.</td></tr>
<?php endif; ?>

</table>
</div>

<div class="panel">

<h2>Historial de préstamos</h2>

<div class="table-wrap">

<table>

<tr>
<th>ID</th>
<th>Docente</th>
<th>Material</th>
<th>Cantidad</th>
<th>Fecha préstamo</th>
<th>Devolución</th>
<th>Estado</th>
</tr>

<?php foreach ($history as $r): ?>

<tr>
<td><?= $r['id'] ?></td>
<td><?= e($r['teacher']) ?></td>
<td><?= e($r['material']) ?></td>
<td><?= $r['quantity'] ?></td>
<td><?= e($r['loan_date']) ?></td>
<td><?= e($r['returned_at'] ?? 'Pendiente') ?></td>
<td><?= e($r['status']) ?></td>
</tr>

<?php endforeach; ?>

<?php if (!$history): ?>
<tr><td colspan="7">No hay préstamos para los filtros seleccionados.</td></tr>
<?php endif; ?>

</table>

</div>
</div>

<?php require 'footer.php'; ?>