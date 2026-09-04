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
        $sql = "SELECT * FROM router LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status)
    {
        $sql = "UPDATE router
                SET status=:status
                WHERE id=:id";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':status'=>$status,
            ':id'=>$id
        ]);
    }

}