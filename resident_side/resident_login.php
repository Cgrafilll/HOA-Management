<?php
session_start();
require '../rfid-api/db.php'; // fixed path

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: login.php?error=" . urlencode("Please fill in all fields."));
    exit;
}

$sql = "SELECT * FROM residents WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: login.php?error=" . urlencode("Email address not found."));
    exit;
}

if (!password_verify($password, $user['password'])) {
    header("Location: login.php?error=" . urlencode("Incorrect password."));
    exit;
}

// Login success
$_SESSION['resident_id'] = $user['resident_id'];
$_SESSION['resident_name'] = $user['first_name'] . ' ' . $user['last_name'];
header("Location: dashboard.php");
exit;
?>