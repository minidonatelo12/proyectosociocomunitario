
<?php
require_once 'config.php';

header('Location: ' . (logged_in() ? 'dashboard.php' : 'login.php'));
exit;
