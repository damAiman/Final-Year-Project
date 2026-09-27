<?php
require_once __DIR__ . '/config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}
