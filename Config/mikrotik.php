<?php

require_once __DIR__ . '/../config/database.php';

class MikroTikConfig
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getRouter()
    {
        $sql = "SELECT * FROM router ORDER BY CASE WHEN status='ONLINE' THEN 0 ELSE 1 END, id ASC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllRouters()
    {
        $stmt = $this->conn->query("SELECT id,router_name,ip_address,username,api_port,status,created_at FROM router ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status)
    {
        $sql = "UPDATE router SET status=:status WHERE id=:id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':status'=>$status,':id'=>$id]);
    }

}