<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    log_activity('Authentication', 'Signed out of SMART STOCK');
    log_audit('Authentication', 'LOGOUT', 'User', (int) $_SESSION['user_id']);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

header('Location: ' . base_url('auth/login.php'));
exit;
