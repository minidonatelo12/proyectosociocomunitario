
<?php
require_once 'config.php';
require_login();

$page_title = 'Mis préstamos';

$s = $pdo->prepare(
    "SELECT l.*, m.name AS material
     FROM loans l
     JOIN materials m ON m.id = l.material_id
     WHERE l.teacher_id = ?
     ORDER BY l.loan_date DESC"
);

$s->execute([$_SESSION['user']['id']]);
$rows = $s->fetchAll();

require 'header.php';
?>

<h1>Mis préstamos</h1>

<div class="panel">

<div class="table-wrap">

<table>

<tr>
<th>Material</th>
<th>Cantidad</th>
<th>Fecha de préstamo</th>
<th>Fecha estimada de devolución</th>
<th>Fecha de devolución</th>
<th>Estado</th>
<th>Condición</th>
</tr>

<?php foreach ($rows as $r): ?>

<tr>
<td><?= e($r['material']) ?></td>
<td><?= $r['quantity'] ?></td>
<td><?= e($r['loan_date']) ?></td>
<td><?= e($r['expected_return'] ?? 'No definida') ?></td>
<td><?= e($r['returned_at'] ?? 'Pendiente') ?></td>
<td><?= e($r['status']) ?></td>
<td><?= e($r['return_condition'] ?? 'Pendiente') ?></td>
</tr>

<?php endforeach; ?>

<?php if (!$rows): ?>
<tr>
<td colspan="7">Todavía no tienes préstamos registrados.</td>
</tr>
<?php endif; ?>

</table>

</div>
</div>

<?php require 'footer.php'; ?>