<?php
$host = 'localhost';
$db   = 'u247214800_sitioseville';
$user = 'u247214800_sitioseville';
$pass = 'I0wacxI29;1j';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
