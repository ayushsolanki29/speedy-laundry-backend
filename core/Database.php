<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            // First connect without DB name to check/create database
            $this->conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES utf8mb4");
            
            // Create database if it doesn't exist
            $this->conn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Now connect to the specific database
            $this->conn->exec("USE `" . DB_NAME . "`");

            // Ensure MySQL session timezone matches app timezone (handles DST via offset)
            $tz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC';
            try {
                $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P'); // e.g. +00:00 / +01:00
            } catch (Exception $e) {
                $offset = '+00:00';
            }
            $this->conn->exec("SET time_zone = " . $this->conn->quote($offset));
            
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Helper to run migrations or initial setup
    public function initializeSchema($sqlFile) {
        if (!file_exists($sqlFile)) return false;
        
        $sql = file_get_contents($sqlFile);
        try {
            $this->conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Schema initialization error: " . $e->getMessage());
            return false;
        }
    }
}
?>
