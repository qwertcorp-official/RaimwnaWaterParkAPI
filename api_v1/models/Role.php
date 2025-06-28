<?php
require_once 'config/database.php';

class Role {
    private $conn;
    private $table = 'role';

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
            
            if (!$this->conn) {
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            error_log("Role model constructor error: " . $e->getMessage());
            throw $e;
        }
    }

}
?>