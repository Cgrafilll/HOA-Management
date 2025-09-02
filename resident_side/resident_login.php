<?php
// ✅ Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.cookie_lifetime', 7200); // 2 hours

// Set session cookie parameters for better longevity and security
session_set_cookie_params([
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Use secure cookies on HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);

// NOW start the session
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

// ✅ Login success: store session with timestamps
$_SESSION['household_id'] = $user['household_id'];
$_SESSION['resident_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['email_address'] = $user['email_address'];
$_SESSION['login_time'] = time(); // Store login timestamp
$_SESSION['last_activity'] = time(); // Store last activity timestamp

// Redirect to dashboard
header("Location: dashboard.php");
exit;
?>