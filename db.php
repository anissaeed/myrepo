<?php
$host = "sql301.infinityfree.com";
$user = "if0_42656684";
$pass = "AAaa11anis01";
$db   = "if0_42656684_watan";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
?>
