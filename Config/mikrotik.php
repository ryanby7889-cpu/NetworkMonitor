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
        $sql = "SELECT * FROM router ORDER BY is_active DESC, CASE WHEN status='ONLINE' THEN 0 ELSE 1 END, id ASC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRouterById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM router WHERE id=:id LIMIT 1");
        $stmt->execute([':id'=>(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllRouters()
    {
        $stmt = $this->conn->query("SELECT id,router_name,ip_address,username,api_port,status,is_active,created_at FROM router ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setActive($id)
    {
        $id=(int)$id;
        $this->conn->beginTransaction();
        try {
            $this->conn->exec("UPDATE router SET is_active=0");
            $stmt=$this->conn->prepare("UPDATE router SET is_active=1 WHERE id=:id");
            $stmt->execute([':id'=>$id]);
            if($stmt->rowCount()<1) throw new RuntimeException('Router tidak ditemukan.');
            $this->conn->commit();
            return true;
        } catch(Throwable $e) {
            if($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function updateStatus($id, $status)
    {
        $sql = "UPDATE router SET status=:status WHERE id=:id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':status'=>$status,':id'=>$id]);
    }

}