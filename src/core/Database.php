<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/core/Database.php

// First, load the configuration
require_once __DIR__ . '/../config/config.php';

/**
 * Singleton Database Connection Class
 *
 * This ensures we only ever have one database connection open,
 * which is efficient and prevents resource limits.
 */
class Database {

    // Hold the class instance.
    private static $instance = null;
    private $conn;

    // Database connection details from config.php
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $dbname = DB_DATABASE;
    private $username = DB_USERNAME;
    private $password = DB_PASSWORD;

    // Private constructor so newb_ cannot be instantiated
    private function __construct() {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // In a real app, you'd log this error, not just echo it.
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    // The static method that controls access to the instance
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Get the PDO connection object
    public function getConnection() {
        return $this->conn;
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {}
}

?>