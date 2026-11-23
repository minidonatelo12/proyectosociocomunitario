<?php
require_once 'config.php';
require_login();

$page_title = $page_title ?? 'SIM-D KENNEDY';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | SIM-D KENNEDY</title>
<link rel="stylesheet" href="styles.css">
</head>

<body>

<header class="topbar">

<a class="brand" href="dashboard.php">
<span class="brand-mark small">SK</span>
<span>SIM-D KENNEDY</span>
</a>

<div class="userbox">
<?= e($_SESSION['user']['name']) ?>
·
<?= $_SESSION['user']['role'] === 'admin' ? 'Encargado' : 'Docente' ?>
<a href="logout.php">Salir</a>
</div>

</header>

<div class="layout">

<aside class="sidebar">

<a href="dashboard.php">Panel principal</a>
<a href="materials.php">Inventario deportivo</a>

<?php if ($_SESSION['user']['role'] === 'admin'): ?>
<a href="loans.php">Préstamos y devoluciones</a>
<a href="reports.php">Reportes</a>
<a href="users.php">Usuarios</a>
<?php else: ?>
<a href="my_loans.php">Mis préstamos</a>
<?php endif; ?>

</aside>

<main class="content">

<?php show_flash(); ?>