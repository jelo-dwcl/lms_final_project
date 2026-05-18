<?php

class Database {

    private static $instance = null;
    private $conn;

    private function __construct() {

        try {

            $this->conn = new PDO(
                "mysql:host=localhost;dbname=elsa_university;charset=utf8mb4",
                "root",
                ""
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            die("DB CONNECTION ERROR: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // ADDED: safety checker (para madaling ma-debug)
    public function isConnected() {
        return $this->conn !== null;
    }
}
