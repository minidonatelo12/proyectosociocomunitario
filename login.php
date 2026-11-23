<?php
require_once 'config.php';

if (logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        $error = 'El usuario es obligatorio.';
    } elseif ($password === '') {
        $error = 'La contraseña es obligatoria.';
    } else {
        $s = $pdo->prepare(
            "SELECT * FROM users
             WHERE username = ? AND active = 1 LIMIT 1"
        );

        $s->execute([$username]);
        $u = $s->fetch();

        if ($u && password_verify($password, $u['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $u['id'],
                'name' => $u['full_name'],
                'role' => $u['role']
            ];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingreso | SIM-D KENNEDY</title>
<link rel="stylesheet" href="styles.css">
</head>

<body class="login-bg">

<main class="login-card">

<div class="brand-mark">SK</div>

<h1>SIM-D KENNEDY</h1>

<p class="muted">
Sistema de Inventario de Materiales Deportivos
</p>

<?php if ($error): ?>
<div class="notice error"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST">

<label>Usuario</label>
<input type="text" name="username" required autofocus>

<label>Contraseña</label>
<input type="password" name="password" required>

<button class="btn primary full" type="submit">
Iniciar sesión
</button>

</form>

</main>
</body>
</html>