<?php
require_once __DIR__ . '/Config/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard/index.php');
} else {
    header('Location: auth/login.php');
}
exit;
