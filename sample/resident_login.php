<?php
session_start();
require '../rfid-api/db.php'; // fixed path

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: login.php?error=Please fill in all fields.");
    exit;
}

$sql = "SELECT * FROM residents WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['resident_id'] = $user['id'];
    $_SESSION['resident_name'] = $user['name'];
    header("Location: dashboard.php"); // use .php so it can read session
} else {
    header("Location: login.php?error=Invalid email or password.");
}
?>