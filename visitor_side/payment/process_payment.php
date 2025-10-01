<?php
// ============================================
// DEBUGGING MODE - REMOVE AFTER FIXING!
// ============================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Custom error handler to output errors as JSON
function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "PHP Error [$errno]: $errstr",
        'file' => basename($errfile),
        'line' => $errline,
        'full_path' => $errfile
    ]);
    exit;
}
set_error_handler('handleError');

// Custom exception handler
function handleException($exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
        'full_path' => $exception->getFile(),
        'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 5)
    ]);
    exit;
}
set_exception_handler('handleException');

// ============================================
// START SESSION
// ============================================
session_start();

// ============================================
// DATABASE CONNECTION
// ============================================
$db_path = '../../rfid-api/db.php';
if (!file_exists($db_path)) {
    throw new Exception("Database config file not found at: " . realpath(dirname(__FILE__)) . "/../../rfid-api/db.php");
}
require $db_path;

if (!isset($conn)) {
    throw new Exception('Database connection variable $conn not set after requiring db.php');
}

if ($conn->connect_error) {
    throw new Exception('Database connection failed: ' . $conn->connect_error);
}

// ============================================
// PHPMAILER INCLUDES
// ============================================
$phpmailer_base = '../../admin_side/amenity_booking/PHPMailer/src/';
$phpmailer_files = [
    'Exception.php' => $phpmailer_base . 'Exception.php',
    'PHPMailer.php' => $phpmailer_base . 'PHPMailer.php',
    'SMTP.php' => $phpmailer_base . 'SMTP.php'
];

$phpmailer_available = true;
foreach ($phpmailer_files as $name => $path) {
    if (!file_exists($path)) {
        error_log("PHPMailer file not found: $path");
        $phpmailer_available = false;
        break;
    }
}

if ($phpmailer_available) {
    require_once $phpmailer_files['Exception.php'];
    require_once $phpmailer_files['PHPMailer.php'];
    require_once $phpmailer_files['SMTP.php'];
    
    
}

// ============================================
// EMAIL CONFIGURATION
// ============================================
class EmailConfig {
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'lukemia19@gmail.com';
    const SMTP_PASSWORD = 'uezbntejweozhniv';
    const FROM_EMAIL = 'noreply@nsshai.com';
    const FROM_NAME = 'NSSHAI HOA Management';
    const REPLY_TO = 'admin@nsshai.com';
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function handleFileUpload($file, $uploadDir = 'payment_proofs/') {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error code: " . $file['error']);
        }
        return null;
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory: $uploadDir");
        }
    }
    
    // Check if writable
    if (!is_writable($uploadDir)) {
        throw new Exception("Upload directory not writable: $uploadDir (check permissions)");
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Invalid file type: " . $file['type']);
    }
    
    // Validate file size (10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception("File too large: " . round($file['size'] / 1024 / 1024, 2) . "MB (max 10MB)");
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'payment_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $filepath);
    }
    
    return $filename;
}

function getUserDetails($conn, $userType, $userId) {
    if ($userType === 'homeowner') {
        $stmt = $conn->prepare("SELECT household_id, first_name, last_name, email_address FROM household_accounts WHERE household_id = ?");
        if (!$stmt) {
            throw new Exception("Database prepare failed (household_accounts): " . $conn->error);
        }
        $stmt->bind_param("s", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Database execute failed (household_accounts): " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row;
        }
        $stmt->close();
        throw new Exception("Homeowner not found with ID: $userId");
        
    } elseif ($userType === 'visitor') {
        $stmt = $conn->prepare("SELECT visitor_id, first_name, last_name, email_address FROM visitor_details WHERE visitor_id = ?");
        if (!$stmt) {
            throw new Exception("Database prepare failed (visitor_details): " . $conn->error);
        }
        $stmt->bind_param("s", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Database execute failed (visitor_details): " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row;
        }
        $stmt->close();
        throw new Exception("Visitor not found with ID: $userId");
    }
    
    throw new Exception("Invalid user type: $userType");
}

function sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails) {
    global $phpmailer_available;
    
    if (!$phpmailer_available) {
        error_log("PHPMailer not available - skipping email");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EmailConfig::SMTP_USERNAME;
        $mail->Password = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = EmailConfig::SMTP_PORT;
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;
        
        // Recipients
        $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo(EmailConfig::REPLY_TO, 'NSSHAI Admin');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Payment Receipt - NSSHAI [' . $paymentDetails['invoice_number'] . ']';
        $mail->Body = generatePaymentEmailTemplate($recipientName, $paymentDetails);
        $mail->AltBody = generatePaymentPlainTextEmail($recipientName, $paymentDetails);
        
        $result = $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function generatePaymentEmailTemplate($recipientName, $paymentDetails) {
    $invoiceNumber = htmlspecialchars($paymentDetails['invoice_number']);
    $category = htmlspecialchars($paymentDetails['category']);
    $paymentDate = date('F j, Y', strtotime($paymentDetails['payment_date']));
    
    if ($paymentDetails['payment_status'] === 'Completed' || $paymentDetails['payment_status'] === 'paid') {
        $statusColor = '#28a745';
        $statusText = 'PAID IN FULL';
    } else {
        $statusColor = '#ffc107';
        $statusText = 'PARTIALLY PAID';
    }
    
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Receipt</title></head><body>';
    $html .= '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">';
    $html .= '<div style="background:#198754;color:white;padding:30px;text-align:center;">';
    $html .= '<h1>Payment Received!</h1><p>NSSHAI HOA Management</p></div>';
    $html .= '<div style="padding:30px;"><h2>Hello ' . htmlspecialchars($recipientName) . '!</h2>';
    $html .= '<p>Thank you for your payment of <strong>₱' . number_format($paymentDetails['amount_paid'], 2) . '</strong></p>';
    $html .= '<p>Invoice: <strong>' . $invoiceNumber . '</strong></p>';
    $html .= '<p>Category: ' . $category . '</p>';
    $html .= '<p>Remaining Balance: ₱' . number_format($paymentDetails['remaining_balance'], 2) . '</p>';
    $html .= '</div></div></body></html>';
    
    return $html;
}

function generatePaymentPlainTextEmail($recipientName, $paymentDetails) {
    $text = "PAYMENT RECEIPT - NSSHAI\n========================\n\n";
    $text .= "Hello " . $recipientName . "!\n\n";
    $text .= "Invoice: " . $paymentDetails['invoice_number'] . "\n";
    $text .= "Amount Paid: ₱" . number_format($paymentDetails['amount_paid'], 2) . "\n";
    $text .= "Remaining Balance: ₱" . number_format($paymentDetails['remaining_balance'], 2) . "\n";
    return $text;
}

// ============================================
// MAIN PAYMENT PROCESSING
// ============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    
    try {
        $admin_id = $_SESSION['admin_id'] ?? "visitor_payment";
        
        // Get and validate form data
$category = trim($_POST['category'] ?? '');
$user_type = trim($_POST['user_type'] ?? '');
$user_id = trim($_POST['user_id'] ?? '');
$invoice_number = trim($_POST['invoice_number'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$payment_method = ($_POST['payment_method'] === 'Bank Transfer') ? 'bank' : 'cash';
$reference_number = trim($_POST['reference_number'] ?? '');

// DEBUG: Log incoming data
error_log("=== INCOMING POST DATA ===");
error_log("category: $category");
error_log("user_type: $user_type");
error_log("user_id: $user_id");
error_log("invoice_number: $invoice_number");
error_log("amount: $amount");

// Validate required fields
if (empty($category)) throw new Exception('Category is required');
if (empty($user_type)) throw new Exception('User type is required');
if (empty($user_id)) throw new Exception('User ID is required');
if (empty($invoice_number)) throw new Exception('Invoice number is required');
if ($amount <= 0) throw new Exception('Amount must be greater than 0');

// Handle file upload
$proof_filename = null;
if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $proof_filename = handleFileUpload($_FILES['proof_of_payment']);
}

// Get user details
$db_user_type = ($user_type === 'Homeowner/Resident') ? 'homeowner' : 'visitor';
error_log("db_user_type: $db_user_type");

$userDetails = getUserDetails($conn, $db_user_type, $user_id);

// DEBUG: Log what we got back
error_log("=== USER DETAILS FROM DATABASE ===");
error_log("userDetails: " . json_encode($userDetails));

// Use actual database IDs
$household_id = null;
$visitor_id = null;

if ($db_user_type === 'homeowner') {
    $household_id = $userDetails['household_id'];
    error_log("Using household_id: $household_id");
} else {
    $visitor_id = $userDetails['visitor_id'];
    error_log("Using visitor_id: " . var_export($visitor_id, true));
    
    // CRITICAL: Verify this visitor_id actually exists
    $check_stmt = $conn->prepare("SELECT visitor_id FROM visitor_details WHERE visitor_id = ?");
    $check_stmt->bind_param("s", $visitor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    error_log("Verification query - rows found: " . $check_result->num_rows);
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        throw new Exception("VERIFICATION FAILED: Visitor ID '$visitor_id' does not exist in visitor_details table. Original user_id from POST was: '$user_id'");
    }
    
    $verify_row = $check_result->fetch_assoc();
    error_log("Verified visitor_id from database: " . var_export($verify_row['visitor_id'], true));
    $check_stmt->close();
}

// Start transaction
if (!$conn->begin_transaction()) {
    throw new Exception("Failed to start transaction: " . $conn->error);
}

$reference_id = null;
$paymentDetails = null;

// Process Amenity Fee payment
if ($category === 'Amenity Fee') {
    $booking_id_field = ($user_type === 'Homeowner/Resident') ? 'homeowner_id' : 'visitor_id';
    
    error_log("Querying amenity_bookings with $booking_id_field = '$user_id'");
    
    $stmt = $conn->prepare("SELECT * FROM amenity_bookings WHERE $booking_id_field = ? AND invoice_number = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed (amenity_bookings): " . $conn->error);
    }
    
    $stmt->bind_param("ss", $user_id, $invoice_number);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (amenity_bookings): " . $stmt->error);
    }
    
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        throw new Exception("Amenity booking not found for invoice: $invoice_number (User: $user_id, Field: $booking_id_field)");
    }
    
    error_log("Found booking ID: " . $booking['id']);
    
    $reference_id = $booking['id'];
    $new_amount_paid = floatval($booking['amount_paid']) + $amount;
    $balance = floatval($booking['total_amount']) - $new_amount_paid;
    
    // Determine new status
    if ($balance <= 0) {
        $new_status = 'paid';
    } elseif ($new_amount_paid > 0) {
        $new_status = 'partial';
    } else {
        $new_status = 'pending';
    }
    
    // Update booking
    $stmt = $conn->prepare("UPDATE amenity_bookings SET amount_paid = ?, status = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed (update amenity_bookings): " . $conn->error);
    }
    
    $stmt->bind_param("dsi", $new_amount_paid, $new_status, $reference_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (update amenity_bookings): " . $stmt->error);
    }
    $stmt->close();
    
    $paymentDetails = [
        'invoice_number' => $invoice_number,
        'category' => 'Amenity Fee',
        'payment_date' => date('Y-m-d'),
        'payment_method' => $payment_method,
        'amount_paid' => $amount,
        'reference_number' => $reference_number,
        'total_amount' => floatval($booking['total_amount']),
        'total_paid' => $new_amount_paid,
        'remaining_balance' => max(0, $balance),
        'payment_status' => $new_status
    ];
}

// Insert payment record - HANDLE NULL VALUES PROPERLY
$db_category = ($category === 'Amenity Fee') ? 'amenity' : 'monthly_dues';

// DEBUG: Log values before insert
error_log("=== ABOUT TO INSERT PAYMENT ===");
error_log("category: $db_category");
error_log("reference_id: $reference_id");
error_log("invoice_number: $invoice_number");
error_log("user_type: $db_user_type");
error_log("household_id: " . ($household_id ?? 'NULL'));
error_log("visitor_id: " . ($visitor_id ?? 'NULL'));
error_log("amount: $amount");
error_log("payment_method: $payment_method");
error_log("reference_number: $reference_number");
error_log("proof_of_payment: " . ($proof_filename ?? 'NULL'));

// Use different approach based on user type to avoid NULL issues
if ($db_user_type === 'visitor') {
    // For visitors: household_id must be NULL, visitor_id must have value
    if (empty($visitor_id)) {
        throw new Exception("CRITICAL: visitor_id is empty! Cannot insert payment.");
    }
    
    $stmt = $conn->prepare("
        INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
        VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed (insert payments for visitor): " . $conn->error);
    }
    
    error_log("Binding parameters for visitor payment");
    $stmt->bind_param("sissdsss", $db_category, $reference_id, $invoice_number, $db_user_type, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
    
} else {
    // For homeowners: visitor_id must be NULL, household_id must have value
    if (empty($household_id)) {
        throw new Exception("CRITICAL: household_id is empty! Cannot insert payment.");
    }
    
    $stmt = $conn->prepare("
        INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
        VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed (insert payments for homeowner): " . $conn->error);
    }
    
    error_log("Binding parameters for homeowner payment");
    $stmt->bind_param("sisssdsss", $db_category, $reference_id, $invoice_number, $db_user_type, $household_id, $amount, $payment_method, $reference_number, $proof_filename);
}

error_log("Executing payment insert");
if (!$stmt->execute()) {
    $error_detail = "Execute failed (insert payments): " . $stmt->error;
    $error_detail .= " | visitor_id=" . ($visitor_id ?? 'NULL');
    $error_detail .= " | household_id=" . ($household_id ?? 'NULL');
    throw new Exception($error_detail);
}

$payment_id = $conn->insert_id;
error_log("Payment inserted successfully with ID: $payment_id");
$stmt->close();
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Failed to commit transaction: " . $conn->error);
        }
        
        // Send email (non-critical, don't fail if this errors)
        $emailSent = false;
        $recipientEmail = trim($userDetails['email_address'] ?? '');
        
        if (!empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) && $paymentDetails) {
            $recipientName = trim($userDetails['first_name'] . ' ' . $userDetails['last_name']);
            try {
                $emailSent = sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails);
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
            }
        }
        
        // Return success
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully',
            'payment_id' => $payment_id,
            'email_sent' => $emailSent
        ]);
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
        ]);
        exit;
    }
}

// Redirect if not POST request
header("Location: ../visitor_payment.php");
exit;