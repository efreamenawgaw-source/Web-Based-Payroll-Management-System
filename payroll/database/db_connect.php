<?php
// ============================================================
// BiT Payroll — Database Connection
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'payroll_db');
define('DB_USER',    'root');       // change in production
define('DB_PASS',    '');           // change in production
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection instance (singleton).
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In production: log error, show generic message
            error_log('DB Connection failed: ' . $e->getMessage());
            die(json_encode([
                'error' => 'Database connection failed. Please contact the system administrator.'
            ]));
        }
    }

    return $pdo;
}
