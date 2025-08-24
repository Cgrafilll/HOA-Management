<?php
session_start();
require '../rfid-api/db.php'; // adjust path if needed

// Collect form data
$email = trim($_POST['email_address'] ?? '');
$password = $_POST['password'] ?? '';

// If fields are empty
if (empty($email) || empty($password)) {
    header("Location: login.php?error=" . urlencode("Please fill in all fields."));
    exit;
}

// Query resident by email
$sql = "SELECT * FROM household_accounts WHERE email_address = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: login.php?error=" . urlencode("Email address not found."));
    exit;
}

// Check hashed password
if (!password_verify($password, $user['password'])) {
    header("Location: login.php?error=" . urlencode("Incorrect password."));
    exit;
}

// ✅ Login success: store session
$_SESSION['household_id']   = $user['household_id'];
$_SESSION['resident_name']  = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['email_address']  = $user['email_address'];

// Redirect to dashboard
header("Location: dashboard.php");
exit;
?>
