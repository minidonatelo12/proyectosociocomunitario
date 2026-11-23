
<?php
require_once 'config.php';
require_login();

$page_title = 'Panel principal';

$total = (int)$pdo->query(
    "SELECT COALESCE(SUM(total_qty),0) FROM materials"
)->fetchColumn();

$available = (int)$pdo->query(
    "SELECT COALESCE(SUM(available_qty),0) FROM materials"
)->fetchColumn();

$active = (int)$pdo->query(
    "SELECT COUNT(*) FROM loans WHERE status='Activo'"
)->fetchColumn();

$damaged = (int)$pdo->query(
    "SELECT COUNT(*) FROM materials
     WHERE condition_status='Dañado/Baja'"
)->fetchColumn();

require 'header.php';
?>

<h1>Panel principal</h1>

<p class="muted">
Sistema de Inventario de Materiales Deportivos
de la Unidad Educativa Jaqueline Kennedy.
</p>

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
<span>Préstamos activos</span>
<strong><?= $active ?></strong>
</div>

<div class="stat">
<span>Materiales dañados</span>
<strong><?= $damaged ?></strong>
</div>

</div>

<div class="panel">

<h2>Accesos rápidos</h2>

<div class="actions">

<a class="btn primary" href="materials.php">
Ver inventario
</a>

<?php if ($_SESSION['user']['role'] === 'admin'): ?>

<a class="btn" href="loans.php">
Registrar préstamo
</a>

<a class="btn" href="reports.php">
Consultar reportes
</a>

<?php else: ?>

<a class="btn" href="my_loans.php">
Consultar mis préstamos
</a>

<?php endif; ?>

</div>
</div>

<div class="panel">

<h2>Información del sistema</h2>

<p>
SIM-D KENNEDY permite administrar los materiales deportivos,
controlar las existencias, registrar préstamos, gestionar
devoluciones y generar reportes del inventario.
</p>

</div>

<?php require 'footer.php'; ?>