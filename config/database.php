<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'u202760275_smartstock_db');
define('DB_USER', 'u202760275_smartstock');
define('DB_PASS', 'Danish8112'); 
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(
                '<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px 28px;' .
                'border:1px solid #f1c2c2;background:#fdecec;color:#7a1f1f;border-radius:12px;">' .
                '<h2 style="margin-top:0;">Database connection failed</h2>' .
                '<p>SMART STOCK could not connect to MySQL. Please check that:</p>' .
                '<ul>' .
                '<li>MySQL service on Hostinger is active</li>' .
                '<li>A database named <code>' . DB_NAME . '</code> exists</li>' .
                '<li>The credentials in <code>config/database.php</code> match your Hostinger setup</li>' .
                '</ul>' .
                '<p style="color:#a33;font-family:monospace;font-size:13px;">' . htmlspecialchars($e->getMessage()) . '</p>' .
                '</div>'
            );
        }
    }

    return $pdo;
}