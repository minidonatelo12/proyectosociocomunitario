
<?php
require_once 'config.php';
require_login();

$page_title = 'Inventario deportivo';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_admin();

    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $cat = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $qtyText = $_POST['total_qty'] ?? '';
    $condition = $_POST['condition_status'] ?? 'Bueno';

    $errors = [];

    if ($code === '') {
        $errors[] = 'El código es obligatorio.';
    }

    if ($name === '') {
        $errors[] = 'El nombre es obligatorio.';
    }

    if ($qtyText === '' || filter_var($qtyText, FILTER_VALIDATE_INT) === false || (int)$qtyText < 0) {
        $errors[] = 'La cantidad debe ser un número entero igual o mayor que cero.';
    }

    if (!in_array($condition, ['Bueno', 'Regular', 'Dañado/Baja'], true)) {
        $errors[] = 'Estado físico no válido.';
    }

    if ($errors) {
        flash(implode(' ', $errors), 'error');
    } else {
        try {
            $s = $pdo->prepare(
                "INSERT INTO materials
                (code, name, category_id, brand, total_qty,
                 available_qty, condition_status)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $s->execute([
                $code,
                $name,
                $cat ?: null,
                $brand,
                (int)$qtyText,
                (int)$qtyText,
                $condition
            ]);

            flash('Material registrado correctamente.');

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('El código del material ya existe.', 'error');
            } else {
                flash('Error al registrar el material.', 'error');
            }
        }
    }

    header('Location: materials.php');
    exit;
}

$cats = $pdo->query(
    "SELECT * FROM categories ORDER BY name"
)->fetchAll();

$rows = $pdo->query(
    "SELECT m.*, c.name AS category
     FROM materials m
     LEFT JOIN categories c ON c.id = m.category_id
     ORDER BY m.name"
)->fetchAll();

require 'header.php';
?>

<h1>Inventario deportivo</h1>

<p class="muted">
Registro y consulta de materiales deportivos.
</p>

<?php if ($_SESSION['user']['role'] === 'admin'): ?>

<div class="panel">

<h2>Registrar material deportivo</h2>

<form method="POST" id="materialForm" class="form-grid">

<div>
<label>Código *</label>
<input type="text" name="code" id="code" required>
<small id="codeError" class="field-error"></small>
</div>

<div>
<label>Nombre del material *</label>
<input type="text" name="name" id="name" required>
<small id="nameError" class="field-error"></small>
</div>

<div>
<label>Tipo de material / Categoría</label>

<select name="category_id">
<option value="">Sin categoría</option>

<?php foreach ($cats as $c): ?>
<option value="<?= $c['id'] ?>">
<?= e($c['name']) ?>
</option>
<?php endforeach; ?>

</select>
</div>

<div>
<label>Marca</label>
<input type="text" name="brand">
</div>

<div>
<label>Cantidad total</label>
<input type="number" name="total_qty" min="0" value="1" required>
</div>

<div>
<label>Estado físico</label>

<select name="condition_status">
<option value="Bueno">Bueno</option>
<option value="Regular">Regular</option>
<option value="Dañado/Baja">Dañado/Baja</option>
</select>

</div>

<div class="span-all">
<button class="btn primary" id="saveButton" disabled>
Guardar material
</button>
</div>

</form>
</div>

<script>
const form = document.getElementById('materialForm');
const code = document.getElementById('code');
const name = document.getElementById('name');
const saveButton = document.getElementById('saveButton');

function validateMaterial() {

    const validCode = code.value.trim() !== '';
    const validName = name.value.trim() !== '';

    document.getElementById('codeError').textContent =
        validCode ? '' : 'El código es obligatorio.';

    document.getElementById('nameError').textContent =
        validName ? '' : 'El nombre es obligatorio.';

    saveButton.disabled = !(validCode && validName);
}

code.addEventListener('input', validateMaterial);
name.addEventListener('input', validateMaterial);

form.addEventListener('submit', function(event) {

    validateMaterial();

    if (code.value.trim() === '' || name.value.trim() === '') {
        event.preventDefault();
        alert('El código y el nombre son obligatorios.');
    }
});

validateMaterial();
</script>

<?php endif; ?>

<div class="panel">

<h2>Materiales registrados</h2>

<div class="table-wrap">

<table>

<tr>
<th>Código</th>
<th>Material</th>
<th>Categoría</th>
<th>Marca</th>
<th>Total</th>
<th>Disponible</th>
<th>Estado</th>
</tr>

<?php foreach ($rows as $m): ?>

<tr>
<td><?= e($m['code']) ?></td>
<td><?= e($m['name']) ?></td>
<td><?= e($m['category'] ?? '—') ?></td>
<td><?= e($m['brand']) ?></td>
<td><?= $m['total_qty'] ?></td>
<td><?= $m['available_qty'] ?></td>
<td><?= e($m['condition_status']) ?></td>
</tr>

<?php endforeach; ?>

<?php if (!$rows): ?>
<tr>
<td colspan="7">Todavía no hay materiales registrados.</td>
</tr>
<?php endif; ?>

</table>

</div>
</div>

<?php require 'footer.php'; ?>