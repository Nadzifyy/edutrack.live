<?php
/**
 * Database Connection Class
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Suppress warnings for connection attempt
            $this->connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                // More user-friendly error message
                $error_msg = "Database connection failed. ";
                $error_msg .= "Please check your database configuration in config/config.php. ";
                $error_msg .= "Error: " . $this->connection->connect_error;
                throw new Exception($error_msg);
            }
            
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            // Show error but don't expose sensitive info
            if (ini_get('display_errors')) {
                die("<h2>Database Connection Error</h2><p>" . htmlspecialchars($e->getMessage()) . "</p><p><a href='test.php'>Run Diagnostic Test</a></p>");
            } else {
                die("Database connection error. Please contact administrator.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

