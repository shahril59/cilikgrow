<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = 'cilikgrow_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");
