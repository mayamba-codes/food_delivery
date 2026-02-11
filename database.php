<?php
/**
 * Database Configuration File
 * Using MySQLi with Prepared Statements
 */

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "food_delivery";
    private $conn;
    
    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        // Set charset to UTF-8
        $this->conn->set_charset("utf8mb4");
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
    
    public function close() {
        $this->conn->close();
    }
}

// Global database instance
$db = new Database();
?>
