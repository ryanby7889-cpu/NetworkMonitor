<?php
require_once __DIR__ . '/../Config/auth.php';
logoutUser();
header('Location: login.php?logout=1');
exit;
