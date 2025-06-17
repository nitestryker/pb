<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO('sqlite:database.sqlite');
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_TIMEOUT, 10);
            // Enable WAL mode for better concurrency
            $this->connection->exec('PRAGMA journal_mode=WAL;');
            $this->connection->exec('PRAGMA synchronous=NORMAL;');
            $this->connection->exec('PRAGMA cache_size=1000;');
            $this->connection->exec('PRAGMA temp_store=memory;');
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
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
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    private function __wakeup() {}
}
?>
