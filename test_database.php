<?php

require_once "config/database.php";

$db = new Database();

$pdo = $db->connect();

echo "<h2 style='color:green'>";

echo "Database Connected";

echo "</h2>";