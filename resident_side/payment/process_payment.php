<?php
// CRITICAL: Set JSON header and disable HTML errors BEFORE any output
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Create log file in a writable location
$logFile = __DIR__ . '/payment_debug.log';
ini_set('error_log', $logFile);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
// Create log file if it doesn't exist and make it writable
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

// Custom error handler to log to our file
function customErrorLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Wrap everything in try-catch
try {
    customErrorLog("========== NEW REQUEST ==========");
    customErrorLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
    customErrorLog("POST data: " . print_r($_POST, true));
    
    session_start();
    require '../../rfid-api/db.php';


    require_once '../../admin_side/amenity_booking/PHPMailer/src/Exception.php';
    require_once '../../admin_side/amenity_booking/PHPMailer/src/PHPMailer.php';
    require_once '../../admin_side/amenity_booking/PHPMailer/src/SMTP.php';

    // Email configuration
    class EmailConfig {
        const SMTP_HOST = 'smtp.gmail.com';
        const SMTP_PORT = 587;
        const SMTP_USERNAME = 'lukemia19@gmail.com';
        const SMTP_PASSWORD = 'uezbntejweozhniv';
        const FROM_EMAIL = 'noreply@nsshai.com';
        const FROM_NAME = 'NSSHAI HOA Management';
        const REPLY_TO = 'admin@nsshai.com';
    }

    // Helper function to handle file upload
    function handleFileUpload($file, $uploadDir = 'payment_proofs/') {
        customErrorLog("Handling file upload...");
        
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            customErrorLog("No file or file error: " . ($file['error'] ?? 'no file'));
            return null;
        }
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            customErrorLog("Created upload directory: $uploadDir");
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            customErrorLog("File uploaded successfully: $filepath");
            return $filename;
        }
        
        customErrorLog("Failed to move uploaded file");
        return null;
    }

    // Function to get user details
    function getUserDetails($conn, $userType, $userId) {
        customErrorLog("Getting user details for type: $userType, ID: $userId");
        
        if ($userType === 'homeowner') {
            $stmt = $conn->prepare("SELECT household_id, first_name, last_name, email_address FROM household_accounts WHERE household_id = ?");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                customErrorLog("User found: " . $row['email_address']);
                $stmt->close();
                return $row;
            }
            $stmt->close();
        } elseif ($userType === 'visitor') {
            $stmt = $conn->prepare("SELECT visitor_id, first_name, last_name, email_address FROM visitor_details WHERE visitor_id = ?");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                customErrorLog("User found: " . $row['email_address']);
                $stmt->close();
                return $row;
            }
            $stmt->close();
        }
        
        customErrorLog("User not found");
        return null;
    }

    // Email sending function
    function sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails) {
        customErrorLog("📧 Sending email to: $recipientEmail");
        
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = EmailConfig::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = EmailConfig::SMTP_USERNAME;
            $mail->Password = EmailConfig::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = EmailConfig::SMTP_PORT;

            // Recipients
            $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
            $mail->addAddress($recipientEmail, $recipientName);
            $mail->addReplyTo(EmailConfig::REPLY_TO, 'NSSHAI Admin');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Payment Receipt - NSSHAI [' . $paymentDetails['invoice_number'] . ']';
            $mail->Body = generatePaymentEmailTemplate($recipientName, $paymentDetails);
            $mail->AltBody = generatePlainTextEmail($recipientName, $paymentDetails);

            $mail->send();
            customErrorLog("✅ Email sent successfully");
            return true;

        } catch (Exception $e) {
            customErrorLog("❌ Email error: " . $e->getMessage());
            return false;
        }
    }

    // Generate HTML email
    function generatePaymentEmailTemplate($recipientName, $paymentDetails) {
        $invoice = htmlspecialchars($paymentDetails['invoice_number']);
        $category = htmlspecialchars($paymentDetails['category']);
        $date = date('F j, Y', strtotime($paymentDetails['payment_date']));
        $status = ($paymentDetails['payment_status'] === 'Completed' || $paymentDetails['payment_status'] === 'paid') ? 'PAID' : 'PARTIAL';
        $statusColor = ($status === 'PAID') ? '#28a745' : '#ffc107';
        
        return '<!DOCTYPE html><html><head><style>
            body { font-family: Arial, sans-serif; background: #f6f9fc; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
            .header { background: linear-gradient(135deg, #198754, #20c997); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .banner { background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
            .invoice { font-size: 24px; font-weight: bold; color: #1b5e20; font-family: monospace; }
            .details { background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
            .label { font-weight: 600; }
            .status { background: ' . $statusColor . '; color: white; padding: 15px; text-align: center; border-radius: 8px; font-size: 20px; font-weight: bold; margin: 20px 0; }
            .footer { background: #2c3e50; color: #ecf0f1; padding: 20px; text-align: center; font-size: 14px; }
        </style></head><body>
            <div class="container">
                <div class="header"><h1>Payment Received</h1><p>NSSHAI HOA Management</p></div>
                <div class="content">
                    <p>Hello ' . htmlspecialchars($recipientName) . ',</p>
                    <p>Thank you for your payment for <strong>' . $category . '</strong>.</p>
                    <div class="banner"><h3>Invoice Number</h3><div class="invoice">' . $invoice . '</div></div>
                    <div class="details">
                        <div class="row"><span class="label">Category</span><span>' . $category . '</span></div>
                        <div class="row"><span class="label">Date</span><span>' . $date . '</span></div>
                        <div class="row"><span class="label">Amount Paid</span><span>₱' . number_format($paymentDetails['amount_paid'], 2) . '</span></div>
                        <div class="row"><span class="label">Total Amount</span><span>₱' . number_format($paymentDetails['total_amount'], 2) . '</span></div>
                        <div class="row"><span class="label">Balance</span><span>₱' . number_format($paymentDetails['remaining_balance'], 2) . '</span></div>
                    </div>
                    <div class="status">' . $status . '</div>
                    <p style="text-align: center;">Thank you!<br><strong>NSSHAI Administration</strong></p>
                </div>
                <div class="footer"><p>NSSHAI HOA | Contact: 8-2457647 | admin@nsshai.com</p></div>
            </div>
        </body></html>';
    }

    // Generate plain text email
    function generatePlainTextEmail($recipientName, $paymentDetails) {
        return "PAYMENT RECEIPT - NSSHAI\n\nHello " . $recipientName . "!\n\n" .
               "Invoice: " . $paymentDetails['invoice_number'] . "\n" .
               "Category: " . $paymentDetails['category'] . "\n" .
               "Amount Paid: ₱" . number_format($paymentDetails['amount_paid'], 2) . "\n" .
               "Balance: ₱" . number_format($paymentDetails['remaining_balance'], 2) . "\n\n" .
               "Thank you!\nNSSHAI Administration";
    }

    // Validate request
    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['action']) || $_POST['action'] !== 'process_payment') {
        throw new Exception('Invalid request');
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        customErrorLog("Database connection failed");
        throw new Exception('Database connection failed');
    }

    customErrorLog("Database connected successfully");

    // Get form data
    $category = $_POST['category'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    $invoice_number = $_POST['invoice_number'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = ($_POST['payment_method'] === 'Bank Transfer') ? 'bank' : 'cash';
    $reference_number = $_POST['reference_number'] ?? '';
    
    customErrorLog("Form data - Category: $category, User: $user_id, Invoice: $invoice_number, Amount: $amount");
    
    // Validate
    if (empty($category) || empty($user_type) || empty($user_id) || empty($invoice_number) || $amount <= 0) {
        throw new Exception('Missing required fields');
    }
    
    // Handle file upload
    $proof_filename = null;
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $proof_filename = handleFileUpload($_FILES['proof_of_payment']);
    }
    
    // Get user details
    $db_user_type = ($user_type === 'Homeowner/Resident') ? 'homeowner' : 'visitor';
    $userDetails = getUserDetails($conn, $db_user_type, $user_id);
    
    if (!$userDetails) {
        throw new Exception('User not found');
    }
    
    customErrorLog("Starting transaction...");
    $conn->begin_transaction();
    
    $reference_id = null;
    $household_id = ($user_type === 'Homeowner/Resident') ? $user_id : null;
    $visitor_id = ($user_type === 'Visitor') ? $user_id : null;
    $paymentDetails = null;
    
    if ($category === 'Amenity Fee') {
        customErrorLog("Processing Amenity Fee payment");
        
        $booking_id_field = ($user_type === 'Homeowner/Resident') ? 'homeowner_id' : 'visitor_id';
        $stmt = $conn->prepare("SELECT * FROM amenity_bookings WHERE $booking_id_field = ? AND invoice_number = ?");
        $stmt->bind_param("ss", $user_id, $invoice_number);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if (!$booking) {
            throw new Exception('Amenity booking not found');
        }
        
        $reference_id = $booking['id'];
        $new_amount_paid = $booking['amount_paid'] + $amount;
        $balance = $booking['total_amount'] - $new_amount_paid;
        $new_status = ($balance <= 0) ? 'paid' : (($new_amount_paid > 0) ? 'partial' : 'pending');
        
        $stmt = $conn->prepare("UPDATE amenity_bookings SET amount_paid = ?, status = ? WHERE id = ?");
        $stmt->bind_param("dsi", $new_amount_paid, $new_status, $reference_id);
        $stmt->execute();
        
        $paymentDetails = [
            'invoice_number' => $invoice_number,
            'category' => 'Amenity Fee',
            'payment_date' => date('Y-m-d'),
            'payment_method' => $payment_method,
            'amount_paid' => $amount,
            'reference_number' => $reference_number,
            'total_amount' => $booking['total_amount'],
            'total_paid' => $new_amount_paid,
            'remaining_balance' => max(0, $balance),
            'payment_status' => $new_status
        ];
        
    } elseif ($category === 'Monthly Dues') {
        customErrorLog("Processing Monthly Dues payment");
        
        if ($user_type !== 'Homeowner/Resident') {
            throw new Exception('Monthly dues only apply to homeowners/residents');
        }
        
        $stmt = $conn->prepare("SELECT * FROM monthly_dues WHERE household_id = ? AND invoice_number = ?");
        $stmt->bind_param("ss", $user_id, $invoice_number);
        $stmt->execute();
        $dues = $stmt->get_result()->fetch_assoc();
        
        if (!$dues) {
            throw new Exception('Monthly dues record not found');
        }
        
        $reference_id = $dues['id'];
        $new_amount_paid = $dues['amount_paid'] + $amount;
        $new_balance = max(0, $dues['balance_remaining'] - $amount);
        $new_status = ($new_balance <= 0) ? 'Completed' : (($new_amount_paid > 0) ? 'Partial' : 'Pending');
        
        $stmt = $conn->prepare("UPDATE monthly_dues SET amount_paid = ?, balance_remaining = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ddsi", $new_amount_paid, $new_balance, $new_status, $reference_id);
        $stmt->execute();
        
        $total_amount = $dues['amount_paid'] + $dues['balance_remaining'];
        $paymentDetails = [
            'invoice_number' => $invoice_number,
            'category' => 'Monthly Dues',
            'payment_date' => date('Y-m-d'),
            'payment_method' => $payment_method,
            'amount_paid' => $amount,
            'reference_number' => $reference_number,
            'total_amount' => $total_amount,
            'total_paid' => $new_amount_paid,
            'remaining_balance' => $new_balance,
            'payment_status' => $new_status
        ];
    }
    
    // Insert payment record
    $db_category = ($category === 'Amenity Fee') ? 'amenity' : 'monthly_dues';
    $stmt = $conn->prepare("INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssdssss", $db_category, $reference_id, $invoice_number, $db_user_type, $household_id, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
    $stmt->execute();
    
    $payment_id = $conn->insert_id;
    customErrorLog("Payment record inserted with ID: $payment_id");
    
    $conn->commit();
    customErrorLog("Transaction committed");
    
    // Send email
    $emailSent = false;
    $recipientName = trim($userDetails['first_name'] . ' ' . $userDetails['last_name']);
    $recipientEmail = $userDetails['email_address'];

    if (!empty($recipientEmail) && $paymentDetails) {
        customErrorLog("Attempting to send email to: $recipientEmail");
        $emailSent = sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails);
    } else {
        customErrorLog("Email not sent - Email: " . ($recipientEmail ?? 'EMPTY') . ", Details exist: " . ($paymentDetails ? 'YES' : 'NO'));
    }
    
    customErrorLog("Sending JSON response...");
    echo json_encode([
        'success' => true, 
        'message' => 'Payment processed successfully',
        'payment_id' => $payment_id,
        'email_sent' => $emailSent
    ]);
    customErrorLog("Response sent successfully");
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
        customErrorLog("Transaction rolled back");
    }
    
    customErrorLog("❌ ERROR: " . $e->getMessage());
    customErrorLog("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

customErrorLog("========== REQUEST END ==========\n");
exit;