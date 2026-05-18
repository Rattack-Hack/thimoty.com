<?php
// ══════════════════════════════════════════════
// DATABASE CONFIG — db.php
// ══════════════════════════════════════════════
// IMPORTANT: Place this file OUTSIDE your webroot in production.
// Example: /var/www/config/db.php  and require it as:
//          require '/var/www/config/db.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'portfolio_db');
define('DB_USER', 'portfolio_db');  // Change this
define('DB_PASS', 'PASSWORD');  // Change this
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            die('Service temporairement indisponible.');
        }
    }
    return $pdo;
}
