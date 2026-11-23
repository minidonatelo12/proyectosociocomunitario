
<?php
require_once 'config.php';
require_admin();

$page_title = 'Usuarios';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'docente';

    if ($name === '' || $username === '') {
        flash('El nombre y usuario son obligatorios.', 'error');

    } elseif (strlen($pass) < 6) {
        flash('La contraseña debe tener mínimo 6 caracteres.', 'error');

    } elseif (!in_array($role, ['admin', 'docente'], true)) {
        flash('El rol seleccionado no es válido.', 'error');

    } else {

        try {

            $s = $pdo->prepare(
                "INSERT INTO users
                (full_name, username, password_hash, role)
                VALUES (?, ?, ?, ?)"
            );

            $s->execute([
                $name,
                $username,
                password_hash($pass, PASSWORD_DEFAULT),
                $role
            ]);

            flash('Usuario creado correctamente.');

        } catch (PDOException $e) {
            flash('No se pudo crear. Comprueba si el usuario ya existe.', 'error');
        }
    }

    header('Location: users.php');
    exit;
}

$users = $pdo->query(
    "SELECT full_name, username, role, active
     FROM users
     ORDER BY full_name"
)->fetchAll();

require 'header.php';
?>

<h1>Administración de usuarios</h1>

<div class="panel">

<h2>Registrar usuario</h2>

<form method="POST" class="form-grid">

<div>
<label>Nombre completo</label>
<input name="full_name" required>
</div>

<div>
<label>Nombre de usuario</label>
<input name="username" required>
</div>

<div>
<label>Contraseña</label>
<input type="password" name="password" minlength="6" required>
</div>

<div>
<label>Rol</label>

<select name="role">
<option value="docente">Docente</option>
<option value="admin">Encargado</option>
</select>

</div>

<div class="span-all">
<button class="btn primary">Crear usuario</button>
</div>

</form>

</div>

<div class="panel">

<h2>Usuarios registrados</h2>

<div class="table-wrap">

<table>

<tr>
<th>Nombre completo</th>
<th>Usuario</th>
<th>Rol</th>
<th>Estado</th>
</tr>

<?php foreach ($users as $u): ?>

<tr>
<td><?= e($u['full_name']) ?></td>
<td><?= e($u['username']) ?></td>
<td><?= $u['role'] === 'admin' ? 'Encargado' : 'Docente' ?></td>
<td><?= $u['active'] ? 'Activo' : 'Inactivo' ?></td>
</tr>

<?php endforeach; ?>

</table>

</div>
</div>

<?php require 'footer.php'; ?>