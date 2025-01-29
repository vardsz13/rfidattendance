<?php
// config/database.php
require_once dirname(__FILE__) . '/constants.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }

    public function connect() {
        try {
            if ($this->conn === null) {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                    ]
                );
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return false;
        }
    }

    public function single($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    public function all($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { 
            return ":$field"; 
        }, $fields);
        
        $sql = "INSERT INTO $table (" . implode(", ", $fields) . ") 
                VALUES (" . implode(", ", $placeholders) . ")";
        
        if ($this->query($sql, $data)) {
            return $this->connect()->lastInsertId();
        }
        return false;
    }

    public function update($table, $data, $where) {
        $fields = array_map(function($field) { 
            return "$field = :$field"; 
        }, array_keys($data));
        
        $sql = "UPDATE $table SET " . implode(", ", $fields) . " WHERE ";
        
        if (is_array($where)) {
            $whereClauses = array_map(function($field) { 
                return "$field = :w_$field"; 
            }, array_keys($where));
            $sql .= implode(" AND ", $whereClauses);
            
            // Add where params with 'w_' prefix to avoid name collisions
            foreach ($where as $key => $value) {
                $data["w_$key"] = $value;
            }
        } else {
            $sql .= $where;
        }
        
        return $this->query($sql, $data) !== false;
    }
}