<?php
// ============================================================
// BiT Payroll — Database Connection
// OOP Singleton pattern — backward-compatible via getDB()
// ============================================================

// ── Connection constants ────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'payroll_db');
define('DB_USER',    'root');       // change in production
define('DB_PASS',    '');           // change in production
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Database — Singleton class
// ============================================================
class Database
{
    // Holds the single instance of this class
    private static ?Database $instance = null;

    // Holds the PDO connection
    private PDO $pdo;

    // ── Private constructor — prevents direct instantiation ──
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            die(json_encode([
                'error' => 'Database connection failed. Please contact the system administrator.'
            ]));
        }
    }

    // ── Prevent cloning of the singleton ────────────────────
    private function __clone() {}

    // ── Prevent unserialization of the singleton ─────────────
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize a singleton.');
    }

    // ── Returns the single Database instance ────────────────
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    // ── Returns the underlying PDO connection ───────────────
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // ── Convenience: prepare a statement directly ───────────
    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    // ── Convenience: execute a query directly ───────────────
    public function query(string $sql): \PDOStatement
    {
        return $this->pdo->query($sql);
    }

    // ── Convenience: begin a transaction ────────────────────
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    // ── Convenience: commit a transaction ───────────────────
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    // ── Convenience: roll back a transaction ────────────────
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    // ── Returns the last inserted auto-increment ID ─────────
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}

// ============================================================
// Backward-compatible wrapper — every existing file that calls
// getDB() continues to work without any modification.
// ============================================================
function getDB(): PDO
{
    return Database::getInstance()->getConnection();
}
