<?php
session_start();
require '../../rfid-api/db.php'; // Adjust this if needed

$email = $_POST['email_address'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: login.php?error=" . urlencode("Please fill in all fields."));
    exit;
}

// Check if email exists
$sql = "SELECT * FROM admin_accounts WHERE email_address = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: login.php?error=" . urlencode("Email address not found."));
    exit;
}

// Verify password
if (!password_verify($password, $user['password'])) {
    header("Location: login.php?error=" . urlencode("Incorrect password."));
    exit;
}

// Success — set session
$_SESSION['admin_id'] = $user['admin_id'];
header("Location: ../admin_dashboard.php");
exit;
?>