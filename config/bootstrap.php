<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Work out the site's base URL so links/assets work no matter what folder
// name this project is installed under inside htdocs.
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot     = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');

$relative = $docRoot !== '' && strpos($projectRoot, $docRoot) === 0
    ? substr($projectRoot, strlen($docRoot))
    : '';

define('BASE_URL', ($relative === '' ? '' : $relative) . '/');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

/* Demo 3: idle session timeout (configurable in System Settings).
   Runs on every request, after the helpers are available. */
enforce_session_timeout();
