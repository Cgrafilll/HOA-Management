<?php
// Output buffering to catch any errors
ob_start();

// Set JSON header FIRST
header('Content-Type: application/json');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Simple debug array
$debug = [];
$debug[] = "Script started";

try {
    session_start();
    $debug[] = "Session started";
    
    require '../../rfid-api/db.php';
    $debug[] = "Database included";

    

    require_once '../../admin_side/amenity_booking/PHPMailer/src/Exception.php';
    require_once '../../admin_side/amenity_booking/PHPMailer/src/PHPMailer.php';
    require_once '../../admin_side/amenity_booking/PHPMailer/src/SMTP.php';
    $debug[] = "PHPMailer loaded";

    class EmailConfig {
        const SMTP_HOST = 'smtp.gmail.com';
        const SMTP_PORT = 587;
        const SMTP_USERNAME = 'lukemia19@gmail.com';
        const SMTP_PASSWORD = 'uezbntejweozhniv';
        const FROM_EMAIL = 'noreply@nsshai.com';
        const FROM_NAME = 'NSSHAI HOA Management';
        const REPLY_TO = 'admin@nsshai.com';
    }

    function handleFileUpload($file, $uploadDir = 'payment_proofs/') {
        global $debug;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $debug[] = "No file uploaded";
            return null;
        }
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $debug[] = "File uploaded: $filename";
            return $filename;
        }
        return null;
    }

    function getUserDetails($conn, $userType, $userId) {
        global $debug;
        $debug[] = "Getting user: $userType - $userId";
        
        if ($userType === 'homeowner') {
            $stmt = $conn->prepare("SELECT household_id, first_name, last_name, email_address FROM household_accounts WHERE household_id = ?");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $debug[] = "User found: " . $row['email_address'];
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
                $debug[] = "User found: " . $row['email_address'];
                $stmt->close();
                return $row;
            }
            $stmt->close();
        }
        
        $debug[] = "User not found";
        return null;
    }

    function sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails) {
        global $debug;
        $debug[] = "Sending email to: $recipientEmail";
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = EmailConfig::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = EmailConfig::SMTP_USERNAME;
            $mail->Password = EmailConfig::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = EmailConfig::SMTP_PORT;

            $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
            $mail->addAddress($recipientEmail, $recipientName);

            $mail->isHTML(true);
            $mail->Subject = 'Payment Receipt - NSSHAI [' . $paymentDetails['invoice_number'] . ']';
            $mail->Body = generateEmailHTML($recipientName, $paymentDetails);

            $mail->send();
            $debug[] = "Email sent successfully";
            return true;
        } catch (Exception $e) {
            $debug[] = "Email error: " . $e->getMessage();
            return false;
        }
    }

    function generateEmailHTML($name, $details) {
        $invoice = htmlspecialchars($details['invoice_number']);
        $category = htmlspecialchars($details['category']);
        $date = date('F j, Y', strtotime($details['payment_date']));
        
        return '<!DOCTYPE html><html><head><style>
            body{font-family:Arial,sans-serif;background:#f6f9fc;margin:0;padding:20px}
            .container{max-width:600px;margin:0 auto;background:white;border-radius:8px}
            .header{background:linear-gradient(135deg,#198754,#20c997);color:white;padding:30px;text-align:center}
            .content{padding:30px}
            .banner{background:#e8f5e9;border:2px solid #4caf50;border-radius:8px;padding:20px;text-align:center;margin:20px 0}
            .invoice{font-size:24px;font-weight:bold;color:#1b5e20;font-family:monospace}
            .details{background:#f8f9fa;border-radius:8px;padding:20px;margin:20px 0}
            .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e9ecef}
            .footer{background:#2c3e50;color:#ecf0f1;padding:20px;text-align:center}
        </style></head><body>
            <div class="container">
                <div class="header"><h1>Payment Received</h1><p>NSSHAI HOA</p></div>
                <div class="content">
                    <p>Hello ' . htmlspecialchars($name) . ',</p>
                    <p>Thank you for your payment for <strong>' . $category . '</strong>.</p>
                    <div class="banner"><h3>Invoice</h3><div class="invoice">' . $invoice . '</div></div>
                    <div class="details">
                        <div class="row"><span>Date</span><span>' . $date . '</span></div>
                        <div class="row"><span>Amount Paid</span><span>₱' . number_format($details['amount_paid'], 2) . '</span></div>
                        <div class="row"><span>Balance</span><span>₱' . number_format($details['remaining_balance'], 2) . '</span></div>
                    </div>
                </div>
                <div class="footer"><p>NSSHAI | 8-2457647 | admin@nsshai.com</p></div>
            </div>
        </body></html>';
    }

    // Validate request
    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['action']) || $_POST['action'] !== 'process_payment') {
        throw new Exception('Invalid request');
    }
    $debug[] = "Request validated";

    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }
    $debug[] = "Database connected";

    // Get form data
    $category = $_POST['category'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    $invoice_number = $_POST['invoice_number'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = ($_POST['payment_method'] === 'Bank Transfer') ? 'bank' : 'cash';
    $reference_number = $_POST['reference_number'] ?? '';
    
    $debug[] = "Data received: Cat=$category, User=$user_id, Invoice=$invoice_number, Amt=$amount";
    
    if (empty($category) || empty($user_type) || empty($user_id) || empty($invoice_number) || $amount <= 0) {
        throw new Exception('Missing required fields');
    }
    
    // Handle file
    $proof_filename = null;
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $proof_filename = handleFileUpload($_FILES['proof_of_payment']);
    }
    
    // Get user
    $db_user_type = ($user_type === 'Homeowner/Resident') ? 'homeowner' : 'visitor';
    $userDetails = getUserDetails($conn, $db_user_type, $user_id);
    
    if (!$userDetails) {
        throw new Exception('User not found');
    }
    
    $debug[] = "Starting transaction";
    $conn->begin_transaction();
    
    $reference_id = null;
    $household_id = ($user_type === 'Homeowner/Resident') ? $user_id : null;
    $visitor_id = ($user_type === 'Visitor') ? $user_id : null;
    $paymentDetails = null;
    
    if ($category === 'Amenity Fee') {
        $debug[] = "Processing Amenity Fee";
        
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
        $debug[] = "Processing Monthly Dues";
        
        if ($user_type !== 'Homeowner/Resident') {
            throw new Exception('Monthly dues only for homeowners');
        }
        
        $stmt = $conn->prepare("SELECT * FROM monthly_dues WHERE household_id = ? AND invoice_number = ?");
        $stmt->bind_param("ss", $user_id, $invoice_number);
        $stmt->execute();
        $dues = $stmt->get_result()->fetch_assoc();
        
        if (!$dues) {
            throw new Exception('Monthly dues not found');
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
    
    // Insert payment
    $db_category = ($category === 'Amenity Fee') ? 'amenity' : 'monthly_dues';
    $stmt = $conn->prepare("INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssdssss", $db_category, $reference_id, $invoice_number, $db_user_type, $household_id, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
    $stmt->execute();
    
    $payment_id = $conn->insert_id;
    $debug[] = "Payment inserted: ID $payment_id";
    
    $conn->commit();
    $debug[] = "Transaction committed";
    
    // Send email
    $emailSent = false;
    $recipientName = trim($userDetails['first_name'] . ' ' . $userDetails['last_name']);
    $recipientEmail = $userDetails['email_address'];

    if (!empty($recipientEmail) && $paymentDetails) {
        $emailSent = sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails);
    } else {
        $debug[] = "Email skipped - no email address";
    }
    
    // Clear output buffer and send response
    ob_end_clean();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment processed successfully',
        'payment_id' => $payment_id,
        'email_sent' => $emailSent,
        'debug' => $debug
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
        $debug[] = "Transaction rolled back";
    }
    
    $debug[] = "ERROR: " . $e->getMessage();
    
    ob_end_clean();
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => $debug
    ]);
}

exit;