<?php
/**
 * Database Class - MySQLi Wrapper
 * Provides simplified database operations with prepared statements
 */

class Database {
    private $mysqli;
    private $error;
    private $lastInsertId;
    private $affectedRows;
    
    /**
     * Constructor - Initialize MySQLi connection
     */
    public function __construct($host, $user, $pass, $db, $charset = 'utf8mb4') {
        $this->mysqli = new mysqli($host, $user, $pass, $db);
        
        // Check connection
        if ($this->mysqli->connect_error) {
            $this->error = 'Connection Error: ' . $this->mysqli->connect_error;
            throw new Exception($this->error);
        }
        
        // Set charset
        if (!$this->mysqli->set_charset($charset)) {
            $this->error = 'Error loading charset ' . $charset;
            throw new Exception($this->error);
        }
    }
    
    /**
     * Execute SELECT query - Get single row
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
     * @param array $params Array of parameter values
     * @return array|false Single row as associative array or false on error
     */
    public function getRow($query, $types = '', $params = []) {
        return $this->executeSelect($query, $types, $params, true);
    }
    
    /**
     * Execute SELECT query - Get multiple rows
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameter values
     * @return array|false Array of rows or false on error
     */
    public function getRows($query, $types = '', $params = []) {
        return $this->executeSelect($query, $types, $params, false);
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE query
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameter values
     * @return int|false Last insert ID for INSERT, affected rows for UPDATE/DELETE, or false on error
     */
    public function execute($query, $types = '', $params = []) {
        // Prepare statement
        if (!$stmt = $this->mysqli->prepare($query)) {
            $this->error = 'Prepare failed: ' . $this->mysqli->error;
            return false;
        }
        
        // Bind parameters if provided
        if (!empty($params)) {
            if (!$stmt->bind_param($types, ...$params)) {
                $this->error = 'Bind failed: ' . $stmt->error;
                $stmt->close();
                return false;
            }
        }
        
        // Execute statement
        if (!$stmt->execute()) {
            $this->error = 'Execute failed: ' . $stmt->error;
            $stmt->close();
            return false;
        }
        
        // Get insert ID and affected rows
        $this->lastInsertId = $this->mysqli->insert_id;
        $this->affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        // Return insert ID if available, otherwise return affected rows
        return $this->lastInsertId > 0 ? $this->lastInsertId : $this->affectedRows;
    }
    
    /**
     * Get last inserted ID
     */
    public function getLastInsertId() {
        return $this->lastInsertId;
    }
    
    /**
     * Get affected rows count
     */
    public function getAffectedRows() {
        return $this->affectedRows;
    }
    
    /**
     * Private helper - Execute SELECT query
     */
    private function executeSelect($query, $types, $params, $single = false) {
        // Prepare statement
        if (!$stmt = $this->mysqli->prepare($query)) {
            $this->error = 'Prepare failed: ' . $this->mysqli->error;
            return false;
        }
        
        // Bind parameters if provided
        if (!empty($params)) {
            if (!$stmt->bind_param($types, ...$params)) {
                $this->error = 'Bind failed: ' . $stmt->error;
                $stmt->close();
                return false;
            }
        }
        
        // Execute statement
        if (!$stmt->execute()) {
            $this->error = 'Execute failed: ' . $stmt->error;
            $stmt->close();
            return false;
        }
        
        // Get result
        $result = $stmt->get_result();
        
        // Fetch data
        if ($single) {
            $data = $result->fetch_assoc();
        } else {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get last error message
     */
    public function getError() {
        return $this->error;
    }
    
    /**
     * Close database connection
     */
    public function close() {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }
    
    /**
     * Destructor - Ensure connection is closed
     */
    public function __destruct() {
        $this->close();
    }
}

?>
