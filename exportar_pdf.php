<?php
require_once 'config.php';
require_admin();

$categoria = $_GET['categoria'] ?? '';
$estado = $_GET['estado'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$conditions = [];
$params = [];

if ($categoria !== '' && ctype_digit($categoria)) {
    $conditions[] = 'm.category_id = ?';
    $params[] = (int)$categoria;
}

if (in_array($estado, ['Bueno', 'Regular', 'Dañado/Baja'], true)) {
    $conditions[] = 'm.condition_status = ?';
    $params[] = $estado;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT m.code, m.name, c.name AS category,
               m.total_qty, m.available_qty, m.condition_status
        FROM materials m
        LEFT JOIN categories c ON c.id = m.category_id
        $where
        ORDER BY m.name";

$s = $pdo->prepare($sql);
$s->execute($params);
$stock = $s->fetchAll();

$loanConditions = [];
$loanParams = [];

if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $loanConditions[] = 'l.loan_date >= ?';
    $loanParams[] = $desde . ' 00:00:00';
}

if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $loanConditions[] = 'l.loan_date < DATE_ADD(?, INTERVAL 1 DAY)';
    $loanParams[] = $hasta;
}

if ($categoria !== '' && ctype_digit($categoria)) {
    $loanConditions[] = 'm.category_id = ?';
    $loanParams[] = (int)$categoria;
}

if (in_array($estado, ['Bueno', 'Regular', 'Dañado/Baja'], true)) {
    $loanConditions[] = 'm.condition_status = ?';
    $loanParams[] = $estado;
}

$loanWhere = $loanConditions
    ? ' WHERE ' . implode(' AND ', $loanConditions)
    : '';

$sql = "SELECT u.full_name AS teacher, m.name AS material,
               l.quantity, l.loan_date, l.returned_at, l.status
        FROM loans l
        JOIN users u ON u.id = l.teacher_id
        JOIN materials m ON m.id = l.material_id
        $loanWhere
        ORDER BY l.loan_date DESC";

$s = $pdo->prepare($sql);
$s->execute($loanParams);
$history = $s->fetchAll();

$total = array_sum(array_column($stock, 'total_qty'));
$available = array_sum(array_column($stock, 'available_qty'));

?>

<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte SIMD-KENNEDY</title>

<style>

body {
    font-family: Arial, sans-serif;
    margin: 30px;
    color: #222;
}

h1, h2 {
    color: #173f5f;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
    font-size: 12px;
}

th, td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}

th {
    background: #e8eef4;
}

.resumen {
    margin: 20px 0;
    padding: 15px;
    background: #edf2f6;
    line-height: 1.8;
}

.botones {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
}

.btn {
    display: inline-block;
    padding: 11px 18px;
    background: #174f79;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 14px;
}

.btn:hover {
    background: #285573;
}

.btn.volver {
    background: #526071;
}

.btn.volver:hover {
    background: #344454;
}

@media print {

    .botones {
        display: none;
    }

    body {
        margin: 10mm;
    }

}

</style>

</head>

<body>

<!-- BOTONES DE NAVEGACION -->

<div class="botones">

<button class="btn volver" type="button" onclick="window.close()">
    &#8592; Volver a reportes
</button>

<button class="btn" onclick="window.print()">
Imprimir / Guardar PDF
</button>

</div>


<!-- ENCABEZADO -->

<h1>SIMD-KENNEDY</h1>

<h2>Sistema de Inventario de Materiales Deportivos</h2>

<p>Reporte general del inventario deportivo.</p>

<p>
Fecha de generación:
<?= date('d/m/Y H:i') ?>
</p>

<p>
Periodo de préstamos:
<?= e($desde ?: 'Sin fecha inicial') ?>
hasta
<?= e($hasta ?: 'Sin fecha final') ?>
</p>


<!-- RESUMEN -->

<div class="resumen">

<strong>Unidades registradas:</strong>
<?= $total ?><br>

<strong>Unidades disponibles:</strong>
<?= $available ?><br>

<strong>Materiales registrados:</strong>
<?= count($stock) ?><br>

<strong>Préstamos encontrados:</strong>
<?= count($history) ?>

</div>


<!-- EXISTENCIAS -->

<h2>Existencias actuales</h2>

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
<tr>
<td colspan="6">No existen materiales registrados.</td>
</tr>
<?php endif; ?>

</table>


<!-- HISTORIAL DE PRESTAMOS -->

<h2>Historial de préstamos</h2>

<table>

<tr>
<th>Docente</th>
<th>Material</th>
<th>Cantidad</th>
<th>Fecha préstamo</th>
<th>Devolución</th>
<th>Estado</th>
</tr>

<?php foreach ($history as $r): ?>

<tr>

<td><?= e($r['teacher']) ?></td>
<td><?= e($r['material']) ?></td>
<td><?= $r['quantity'] ?></td>
<td><?= e($r['loan_date']) ?></td>
<td><?= e($r['returned_at'] ?? 'Pendiente') ?></td>
<td><?= e($r['status']) ?></td>

</tr>

<?php endforeach; ?>

<?php if (!$history): ?>
<tr>
<td colspan="6">No existen préstamos registrados.</td>
</tr>
<?php endif; ?>

</table>

</body>
</html>