<?php
/**
 * Base Model Class
 * All models will extend this class
 */
abstract class Model {
    protected $db;
    
    /**
     * Constructor - Connect to the database
     */
    public function __construct() {
        // Create a new PDO instance
        try {
            $this->db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die('Database Connection Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute a query
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters for the query
     * @return PDOStatement The result set
     */
    protected function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die('Query Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get a single record
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters for the query
     * @return object|bool The record or false if not found
     */
    protected function single($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->fetch(PDO::FETCH_OBJ);
    }
    
    /**
     * Get multiple records
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters for the query
     * @return array The records
     */
    protected function resultSet($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->fetchAll(PDO::FETCH_OBJ);
    }
    
    /**
     * Count rows
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters for the query
     * @return int The row count
     */
    protected function rowCount($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result->rowCount();
    }
}