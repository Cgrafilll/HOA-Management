<?php
$host = 'localhost';
$db   = 'u247214800_sitioseville';
$user = 'u247214800_sitioseville';
$pass = 'Admin123'; // default for XAMPP

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
