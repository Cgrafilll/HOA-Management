<?php
// Remove the ini_set for error_log path - let server handle it
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

session_start();
require '../../rfid-api/db.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require_once '../../admin_side/amenity_booking/PHPMailer/src/Exception.php';
require_once '../../admin_side/amenity_booking/PHPMailer/src/PHPMailer.php';
require_once '../../admin_side/amenity_booking/PHPMailer/src/SMTP.php';

// Email configuration
class EmailConfig
{
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
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'payment_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return null;
}

// Function to get user details and email
function getUserDetails($conn, $userType, $userId)
{
    if ($userType === 'homeowner') {
        $stmt = $conn->prepare("SELECT household_id, first_name, last_name, email_address FROM household_accounts WHERE household_id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
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
            $stmt->close();
            return $row;
        }
        $stmt->close();
    }
    return null;
}

// Robust email sending function using PHPMailer
function sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings - DISABLE DEBUG in production
        $mail->SMTPDebug = 0; // Set to 0 for production
        $mail->isSMTP();
        $mail->Host = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EmailConfig::SMTP_USERNAME;
        $mail->Password = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = EmailConfig::SMTP_PORT;
        
        // Set timeout for shared hosting
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

// Generate HTML email template for payment receipt
function generatePaymentEmailTemplate($recipientName, $paymentDetails)
{
    $invoiceNumber = htmlspecialchars($paymentDetails['invoice_number']);
    $category = htmlspecialchars($paymentDetails['category']);
    $paymentDate = date('F j, Y', strtotime($paymentDetails['payment_date']));
    $statusColor = '';
    $statusText = '';
    
    // Determine status styling
    if ($paymentDetails['payment_status'] === 'Completed' || $paymentDetails['payment_status'] === 'Paid' || $paymentDetails['payment_status'] === 'paid') {
        $statusColor = '#28a745';
        $statusText = 'PAID IN FULL';
    } else {
        $statusColor = '#ffc107';
        $statusText = 'PARTIALLY PAID';
    }

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Receipt</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f6f9fc; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
            
            .header { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
            .header p { font-size: 16px; opacity: 0.9; margin: 0; }
            
            .content { padding: 40px 30px; }
            .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 20px; }
            .intro-text { font-size: 16px; color: #34495e; line-height: 1.6; margin-bottom: 30px; }
            
            .payment-banner { background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border: 2px solid #4caf50; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; }
            .payment-banner h3 { font-size: 18px; color: #2e7d32; margin-bottom: 10px; }
            .payment-banner .invoice { font-size: 32px; font-weight: bold; color: #1b5e20; letter-spacing: 2px; font-family: "Courier New", monospace; }
            
            .payment-details { background-color: #f8f9fa; border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 5px solid #198754; }
            .payment-details h3 { color: #198754; font-size: 20px; margin-bottom: 20px; }
            
            .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: 600; color: #495057; font-size: 14px; }
            .detail-value { color: #212529; font-size: 14px; text-align: right; }
            .detail-value.amount { font-size: 16px; font-weight: bold; }

            .balance-section { background: linear-gradient(135deg, #e3f2fd 0%, #f0f9ff 100%); border: 1px solid #2196f3; border-radius: 12px; padding: 25px; margin: 30px 0; }
            .balance-section h4 { color: #1976d2; font-size: 18px; margin-bottom: 15px; }
            .balance-row { display: flex; justify-content: space-between; padding: 8px 0; }
            .balance-label { font-weight: 600; color: #1976d2; font-size: 16px; }
            .balance-amount { font-size: 18px; font-weight: bold; }

            .status-section { background-color: ' . $statusColor . '; color: white; border-radius: 12px; padding: 20px; margin: 30px 0; text-align: center; }
            .status-section .status-text { font-size: 24px; font-weight: bold; letter-spacing: 2px; }
            
            .footer { background-color: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; }
            .footer p { margin: 5px 0; font-size: 13px; opacity: 0.8; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>Payment Received!</h1>
                <p>Neopolitan Sitio Seville Homeowners Association</p>
            </div>
            
            <div class="content">
                <div class="greeting">Hello ' . htmlspecialchars($recipientName) . '!</div>
                
                <div class="intro-text">
                    Thank you for your payment! We have successfully received and processed your payment for <strong>' . $category . '</strong>.
                </div>
                
                <div class="payment-banner">
                    <h3>Invoice Number</h3>
                    <div class="invoice">' . $invoiceNumber . '</div>
                </div>
                
                <div class="payment-details">
                    <h3>Payment Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Category</span>
                        <span class="detail-value">' . $category . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Date</span>
                        <span class="detail-value">' . $paymentDate . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method</span>
                        <span class="detail-value">' . ucfirst($paymentDetails['payment_method']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount Paid</span>
                        <span class="detail-value amount">₱' . number_format($paymentDetails['amount_paid'], 2) . '</span>
                    </div>';

    if (!empty($paymentDetails['reference_number'])) {
        $html .= '
                    <div class="detail-row">
                        <span class="detail-label">Reference Number</span>
                        <span class="detail-value">' . htmlspecialchars($paymentDetails['reference_number']) . '</span>
                    </div>';
    }

    $html .= '
                </div>
                
                <div class="balance-section">
                    <h4>Balance Summary</h4>
                    <div class="balance-row">
                        <span class="balance-label">Total Amount</span>
                        <span class="balance-amount">₱' . number_format($paymentDetails['total_amount'], 2) . '</span>
                    </div>
                    <div class="balance-row">
                        <span class="balance-label">Total Paid</span>
                        <span class="balance-amount">₱' . number_format($paymentDetails['total_paid'], 2) . '</span>
                    </div>
                    <div class="balance-row">
                        <span class="balance-label">Remaining Balance</span>
                        <span class="balance-amount">₱' . number_format($paymentDetails['remaining_balance'], 2) . '</span>
                    </div>
                </div>
                
                <div class="status-section">
                    <div class="status-text">' . $statusText . '</div>
                </div>
                
                <div style="text-align: center; margin-top: 30px; color: #666;">
                    <p>Thank you for your prompt payment!</p>
                    <p style="margin-top: 15px;"><strong>Best regards,<br>NSSHAI Administration Team</strong></p>
                </div>
            </div>
            
            <div class="footer">
                <h4>Neopolitan Sitio Seville Homeowners Association, Inc.</h4>
                <p>This is an automated payment confirmation email.</p>
                <p>For support, contact us at 8-2457647</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

// Generate plain text version
function generatePaymentPlainTextEmail($recipientName, $paymentDetails)
{
    $text = "PAYMENT RECEIPT - NSSHAI\n";
    $text .= "========================\n\n";
    $text .= "Hello " . $recipientName . "!\n\n";
    $text .= "Thank you for your payment!\n\n";
    $text .= "INVOICE NUMBER: " . $paymentDetails['invoice_number'] . "\n\n";
    $text .= "Amount Paid: ₱" . number_format($paymentDetails['amount_paid'], 2) . "\n";
    $text .= "Remaining Balance: ₱" . number_format($paymentDetails['remaining_balance'], 2) . "\n\n";
    $text .= "Best regards,\nNSSHAI Administration Team";
    return $text;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    
    try {
        $admin_id = $_SESSION['household_id'] ?? "system"; // Use household_id for resident
        
        // Get form data
        $category = $_POST['category'] ?? '';
        $user_type = $_POST['user_type'] ?? '';
        $user_id = $_POST['user_id'] ?? '';
        $invoice_number = $_POST['invoice_number'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = ($_POST['payment_method'] === 'Bank Transfer') ? 'bank' : 'cash';
        $reference_number = $_POST['reference_number'] ?? '';
        
        // Validate required fields
        if (empty($category) || empty($user_type) || empty($user_id) || empty($invoice_number) || $amount <= 0) {
            throw new Exception('Missing required fields');
        }
        
        // Handle file upload
        $proof_filename = null;
        if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
            $proof_filename = handleFileUpload($_FILES['proof_of_payment']);
            if (!$proof_filename) {
                throw new Exception('Failed to upload proof of payment');
            }
        }
        
        // Get user details
        $db_user_type = ($user_type === 'Homeowner/Resident') ? 'homeowner' : 'visitor';
        $userDetails = getUserDetails($conn, $db_user_type, $user_id);
        
        if (!$userDetails) {
            throw new Exception('User not found');
        }
        
        // Check if email exists
        if (empty($userDetails['email_address'])) {
            error_log("WARNING: No email address found for user " . $user_id);
        }
        
        $conn->begin_transaction();
        
        $reference_id = null;
        $household_id = ($user_type === 'Homeowner/Resident') ? $user_id : null;
        $visitor_id = ($user_type === 'Visitor') ? $user_id : null;
        $paymentDetails = null;
        
        if ($category === 'Amenity Fee') {
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
            
            if ($balance <= 0) {
                $new_status = 'paid';
            } elseif ($new_amount_paid > 0) {
                $new_status = 'partial';
            } else {
                $new_status = 'pending';
            }
            
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
            
        } elseif (in_array($category, ['Monthly Dues', 'Penalty Fees', 'Other Fees'])) {
            if ($user_type !== 'Homeowner/Resident') {
                throw new Exception($category . ' only apply to homeowners/residents');
            }
            
            // Map category to database value
            $db_billing_category = '';
            if ($category === 'Monthly Dues') {
                $db_billing_category = 'monthly_dues';
            } elseif ($category === 'Penalty Fees') {
                $db_billing_category = 'penalty_fees';
            } elseif ($category === 'Other Fees') {
                $db_billing_category = 'other_fees';
            }
            
            $stmt = $conn->prepare("SELECT * FROM monthly_dues WHERE household_id = ? AND invoice_number = ? AND category = ?");
            $stmt->bind_param("sss", $user_id, $invoice_number, $db_billing_category);
            $stmt->execute();
            $billing = $stmt->get_result()->fetch_assoc();
            
            if (!$billing) {
                throw new Exception($category . ' record not found');
            }
            
            $reference_id = $billing['id'];
            $new_amount_paid = round($billing['amount_paid'] + $amount, 2);
            $new_balance = round($billing['balance_remaining'] - $amount, 2);
            
            if ($new_balance < 0) {
                $new_balance = 0;
            }
            
            if ($new_balance <= 0.01) {
                $new_status = 'Paid';
            } elseif ($new_amount_paid > 0) {
                $new_status = 'Partial';
            } else {
                $new_status = 'Pending';
            }
            
            $stmt = $conn->prepare("UPDATE monthly_dues SET amount_paid = ?, balance_remaining = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ddsi", $new_amount_paid, $new_balance, $new_status, $reference_id);
            $stmt->execute();
            
            $total_amount = $billing['amount_paid'] + $billing['balance_remaining'];
            $paymentDetails = [
                'invoice_number' => $invoice_number,
                'category' => $category,
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
        $db_category_payment = '';
        if ($category === 'Amenity Fee') {
            $db_category_payment = 'amenity';
        } elseif ($category === 'Monthly Dues') {
            $db_category_payment = 'monthly_dues';
        } elseif ($category === 'Penalty Fees') {
            $db_category_payment = 'penalty_fees';
        } elseif ($category === 'Other Fees') {
            $db_category_payment = 'other_fees';
        }
        
        $stmt = $conn->prepare("
            INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sisssdssss", $db_category_payment, $reference_id, $invoice_number, $db_user_type, $household_id, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
        $stmt->execute();
        
        $payment_id = $conn->insert_id;
        
        $conn->commit();
        
        // Send email
        $emailSent = false;
        $recipientEmail = trim($userDetails['email_address'] ?? '');
        
        if (!empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) && $paymentDetails) {
            $recipientName = trim($userDetails['first_name'] . ' ' . $userDetails['last_name']);
            $emailSent = sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails);
        }
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Payment processed successfully',
            'payment_id' => $payment_id,
            'email_sent' => $emailSent
        ]);
        
    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        
        error_log("Payment processing error: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

// Redirect if not POST request
header("Location: ../payment.php");
exit;
?>