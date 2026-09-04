<?php

class Database
{
    private $host = "localhost";
    private $db   = "network_monitor";
    private $user = "root";
    private $pass = "";

    public function connect()
    {
        try {

            $pdo = new PDO(
                "mysql:host=".$this->host.
                ";dbname=".$this->db.
                ";charset=utf8mb4",

                $this->user,
                $this->pass
            );

            $pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $pdo->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $pdo;

        } catch (PDOException $e) {

            die(
                "Database Error: ".
                $e->getMessage()
            );

        }
    }
}