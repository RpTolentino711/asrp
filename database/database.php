<?php

class Database {

    private $host = "mysql.hostinger.com";
    private $dbname = "u321173822_asrp"; // UPDATED DATABASE NAME
    private $user = "u321173822_alicia";
    private $pass = "Pogilameg@10";
    private $pdo;

    public function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            $this->pdo->exec("SET time_zone = '+08:00'");

        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

       public function getRow($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- Private Helper Methods ---
    // CHANGED FROM private TO protected FOR EXTENSIBILITY, BUT NOT public!
    protected function runQuery($sql, $params = [], $fetchAll = false) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $fetchAll ? $stmt->fetchAll() : $stmt->fetch();
        } catch (PDOException $e) { return false; }
    }

public function executeStatement($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) { return false; }
    }

    protected function insertAndGetId($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) { return false; }
    }

    public function invoiceExists($client_id, $due_date) {
        $sql = "SELECT 1 FROM invoice WHERE Client_ID = ? AND InvoiceDate = ?";
        $result = $this->runQuery($sql, [$client_id, $due_date]);
        return (bool)$result;
    }


    
    public function getSpaceByName($name) {
    $sql = "SELECT * FROM space WHERE Name = ? ORDER BY Space_ID DESC LIMIT 1";
    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getSpaceByName Error: " . $e->getMessage());
        return null;
    }
}


    
public function executeQuery($sql, $params = []) {
    try {
        // FIX: Use $this->pdo instead of $this->conn
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return [];
    }
}




public function getOccupancyData($startDate, $endDate) {
    try {
        $monthDays = date('t', strtotime($startDate));
        
        $sql = "SELECT 
                    s.Space_ID, s.Name, s.Price, st.SpaceTypeName,
                    c.Client_fn, c.Client_ln,
                    cs.active,
                    CASE WHEN cs.active = 1 THEN 'Occupied' ELSE 'Vacant' END as Status,
                    DATEDIFF(LEAST(?, i.EndDate), GREATEST(?, i.InvoiceDate)) as occupied_days,
                    ? as month_days,
                    (DATEDIFF(LEAST(?, i.EndDate), GREATEST(?, i.InvoiceDate)) / ? * 100) as utilization_rate,
                    i.InvoiceTotal as revenue
                FROM space s
                LEFT JOIN spacetype st ON s.SpaceType_ID = st.SpaceType_ID
                LEFT JOIN clientspace cs ON s.Space_ID = cs.Space_ID AND cs.active = 1
                LEFT JOIN client c ON cs.Client_ID = c.Client_ID
                LEFT JOIN invoice i ON s.Space_ID = i.Space_ID 
                    AND i.Status = 'paid' 
                    AND i.InvoiceDate BETWEEN ? AND ?
                WHERE s.Flow_Status = 'old'
                ORDER BY s.SpaceType_ID, s.Name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $endDate, $startDate, $monthDays,
            $endDate, $startDate, $monthDays,
            $startDate, $endDate
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting occupancy data: " . $e->getMessage());
        return [];
    }
}

public function getFinancialSummary($startDate, $endDate) {
    try {
        $sql = "SELECT 
                    COUNT(CASE WHEN Status = 'unpaid' AND EndDate < CURDATE() THEN 1 END) as overdue_count,
                    COALESCE(SUM(CASE WHEN st.SpaceTypeName = 'Space' AND Status = 'paid' THEN i.InvoiceTotal ELSE 0 END), 0) as space_revenue,
                    COALESCE(SUM(CASE WHEN st.SpaceTypeName = 'Apartment' AND Status = 'paid' THEN i.InvoiceTotal ELSE 0)


