<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test 1: Check if PHPMailer exists
echo "<h3>Test 1: PHPMailer Files</h3>";
$paths = [
    '../../admin_side/amenity_booking/PHPMailer/src/Exception.php',
    '../../admin_side/amenity_booking/PHPMailer/src/PHPMailer.php',
    '../../admin_side/amenity_booking/PHPMailer/src/SMTP.php'
];

foreach ($paths as $path) {
    $fullPath = __DIR__ . '/' . $path;
    echo $path . ": " . (file_exists($fullPath) ? "✅ EXISTS" : "❌ NOT FOUND") . "<br>";
}

// Test 2: Try to load PHPMailer
echo "<h3>Test 2: Load PHPMailer</h3>";
try {
    require_once '../../admin_side/amenity_booking/PHPMailer/src/Exception.php';
    require_once '../../admin_side/amenity_booking/PHPMailer/src/PHPMailer.php';
    require_once '../../admin_side/amenity_booking/PHPMailer/src/SMTP.php';
    echo "✅ PHPMailer loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Failed to load PHPMailer: " . $e->getMessage() . "<br>";
    exit;
}

// Test 3: Test email sending
echo "<h3>Test 3: Send Test Email</h3>";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2; // Enable verbose debug
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lukemia19@gmail.com';
    $mail->Password = 'uezbntejweozhniv';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 30;
    
    $mail->setFrom('noreply@nsshai.com', 'NSSHAI Test');
    $mail->addAddress('lukemia19@gmail.com', 'Test Recipient'); // Send to yourself
    
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Hostinger';
    $mail->Body = '<h1>Test Successful!</h1><p>If you receive this, PHPMailer is working.</p>';
    
    $mail->send();
    echo "✅ Email sent successfully!";
    
} catch (Exception $e) {
    echo "❌ Email failed: {$mail->ErrorInfo}<br>";
    echo "Exception: " . $e->getMessage();
}
?>