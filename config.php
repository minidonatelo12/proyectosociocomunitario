
<?php

// ==================================================
// SISTEMA: INVENTARIO DE MATERIALES DEPORTIVOS
// INSTITUCION: UNIDAD EDUCATIVA JAQUELINE KENNEDY
// NOMBRE: SIMD-KENNEDY
// ARCHIVO: config.php
// ==================================================


// ==================================================
// 1. CONFIGURACION DE SESIONES
// ==================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ==================================================
// 2. CONFIGURACION DE BASE DE DATOS
// ==================================================

$host = 'localhost';
$db = 'siged_kennedy';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';


// ==================================================
// 3. CONEXION PDO CON MYSQL
// ==================================================

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user,
        $pass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );

} catch (PDOException $e) {

    http_response_code(500);

    exit(
        'ERROR: No se pudo conectar con la base de datos. 
        Verifica que MySQL este iniciado en XAMPP 
        y que exista la base de datos siged_kennedy.'
    );

}


// ==================================================
// 4. FUNCION PARA PROTEGER DATOS HTML
// ==================================================

function e($valor): string {

    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );

}


// ==================================================
// 5. VERIFICAR SI EL USUARIO INICIO SESION
// ==================================================

function logged_in(): bool {

    return isset($_SESSION['user']);

}


// ==================================================
// 6. VERIFICAR ACCESO AL SISTEMA
// ==================================================

function require_login(): void {

    if (!logged_in()) {

        header('Location: login.php');
        exit;

    }

}


// ==================================================
// 7. VERIFICAR PERMISOS DE ADMINISTRADOR
// ==================================================

function require_admin(): void {

    require_login();

    if (
        !isset($_SESSION['user']['role']) ||
        $_SESSION['user']['role'] !== 'admin'
    ) {

        http_response_code(403);

        exit(
            'ACCESO DENEGADO. 
            Solo el encargado puede realizar esta accion.'
        );

    }

}


// ==================================================
// 8. SISTEMA DE MENSAJES TEMPORALES
// ==================================================

function flash(string $mensaje, string $tipo = 'ok'): void {

    $_SESSION['flash'] = array(
        'message' => $mensaje,
        'type' => $tipo
    );

}


// ==================================================
// 9. MOSTRAR MENSAJES DEL SISTEMA
// ==================================================

function show_flash(): void {

    if (isset($_SESSION['flash'])) {

        $f = $_SESSION['flash'];

        echo '<div class="notice ' . e($f['type']) . '">';

        echo e($f['message']);

        echo '</div>';

        unset($_SESSION['flash']);

    }

}


// ==================================================
// 10. FIN DE CONFIGURACION
// ==================================================