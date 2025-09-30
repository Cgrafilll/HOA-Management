<?php
// Add at the very top, right after opening PHP tag
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_email_debug.log');

session_start();
require '../../rfid-api/db.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files (adjust path based on your installation)
require_once '../../admin_side/amenity_booking/PHPMailer/src/Exception.php';
require_once '../../admin_side/amenity_booking/PHPMailer/src/PHPMailer.php';
require_once '../../admin_side/amenity_booking/PHPMailer/src/SMTP.php';

// Email configuration - UPDATE THESE WITH YOUR DETAILS
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
function handleFileUpload($file, $uploadDir = 'payment_proofs') {
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
    error_log("📧 Inside sendPaymentReceipt function");
    error_log("   Email: " . $recipientEmail);
    error_log("   Name: " . $recipientName);
    
    $mail = new PHPMailer(true);

    try {
        error_log("   Setting up SMTP...");
        
        // Enable verbose debug output
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: " . $str);
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EmailConfig::SMTP_USERNAME;
        $mail->Password = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = EmailConfig::SMTP_PORT;

        error_log("   SMTP configured: " . EmailConfig::SMTP_HOST);
        
        // Recipients
        $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo(EmailConfig::REPLY_TO, 'NSSHAI Admin');

        error_log("   Recipients configured");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Payment Receipt - NSSHAI [' . $paymentDetails['invoice_number'] . ']';

        error_log("   Generating email template...");
        $mail->Body = generatePaymentEmailTemplate($recipientName, $paymentDetails);
        $mail->AltBody = generatePaymentPlainTextEmail($recipientName, $paymentDetails);

        error_log("   Sending email...");
        $result = $mail->send();

        error_log("✅ PHPMailer: Payment receipt sent successfully to " . $recipientEmail);
        return true;

    } catch (Exception $e) {
        error_log("❌ PHPMailer Error: {$mail->ErrorInfo}");
        error_log("❌ Exception: {$e->getMessage()}");
        error_log("❌ Exception trace: " . $e->getTraceAsString());
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
    if ($paymentDetails['payment_status'] === 'Completed' || $paymentDetails['payment_status'] === 'paid') {
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
            
            .payment-banner { background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border: 2px solid #4caf50; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; position: relative; overflow: hidden; }
            .payment-banner::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(76,175,80,0.1) 0%, transparent 70%); }
            .payment-banner .icon { font-size: 48px; margin-bottom: 15px; position: relative; z-index: 2; }
            .payment-banner h3 { font-size: 18px; color: #2e7d32; margin-bottom: 10px; position: relative; z-index: 2; }
            .payment-banner .invoice { font-size: 32px; font-weight: bold; color: #1b5e20; letter-spacing: 2px; font-family: "Courier New", monospace; position: relative; z-index: 2; }
            
            .payment-details { background-color: #f8f9fa; border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 5px solid #198754; }
            .payment-details h3 { color: #198754; font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; }
            .payment-details h3::before { content: "💳"; margin-right: 10px; }
            
            .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: 600; color: #495057; font-size: 14px; flex: 0 0 auto; margin-right: 20px; }
            .detail-value { color: #212529; font-size: 14px; text-align: right; flex: 0 0 auto; }
            .detail-value.highlight { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
            .detail-value.amount { font-size: 16px; font-weight: bold; }

            .balance-section { background: linear-gradient(135deg, #e3f2fd 0%, #f0f9ff 100%); border: 1px solid #2196f3; border-radius: 12px; padding: 25px; margin: 30px 0; }
            .balance-section h4 { color: #1976d2; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; }
            .balance-section h4::before { content: "💰"; margin-right: 10px; }
            .balance-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
            .balance-label { font-weight: 600; color: #1976d2; font-size: 16px; }
            .balance-amount { font-size: 18px; font-weight: bold; }
            .balance-remaining { color: #d32f2f; }
            .balance-paid { color: #2e7d32; }

            .status-section { background-color: ' . $statusColor . '; color: white; border-radius: 12px; padding: 20px; margin: 30px 0; text-align: center; }
            .status-section .status-icon { font-size: 48px; margin-bottom: 10px; }
            .status-section .status-text { font-size: 24px; font-weight: bold; letter-spacing: 2px; }
            
            .important-section { background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 25px; margin: 30px 0; }
            .important-section h4 { color: #856404; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; }
            .important-section h4::before { content: "ℹ️"; margin-right: 10px; }
            .important-section ul { list-style: none; padding: 0; }
            .important-section li { color: #856404; margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px; line-height: 1.5; }
            .important-section li::before { content: "•"; color: #ffc107; font-weight: bold; position: absolute; left: 0; }
            
            .contact-section { background-color: #e8f5e8; border-radius: 12px; padding: 20px; margin: 30px 0; text-align: center; }
            .contact-section h4 { color: #198754; margin-bottom: 10px; }
            .contact-section p { color: #2d5a2d; margin: 5px 0; font-size: 14px; }
            .contact-section .phone { font-size: 18px; font-weight: bold; color: #198754; }
            
            .footer { background-color: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; }
            .footer h4 { margin-bottom: 15px; color: #3498db; }
            .footer p { margin: 5px 0; font-size: 13px; opacity: 0.8; }
            
            @media only screen and (max-width: 600px) {
                .email-container { width: 100% !important; }
                .header, .content, .footer { padding: 20px !important; }
                .payment-banner .invoice { font-size: 24px; }
                .detail-row, .balance-row { flex-direction: column; align-items: flex-start; }
                .detail-value, .balance-amount { text-align: left; margin-top: 5px; }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <h1>Payment Received!</h1>
                <p>Neopolitan Sitio Seville Homeowners Association</p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <div class="greeting">Hello ' . htmlspecialchars($recipientName) . '!</div>
                
                <div class="intro-text">
                    Thank you for your payment! We have successfully received and processed your payment for <strong>' . $category . '</strong>.
                </div>
                
                <!-- Invoice Number Banner -->
                <div class="payment-banner">
                    <div class="icon">🧾</div>
                    <h3>Invoice Number</h3>
                    <div class="invoice">' . $invoiceNumber . '</div>
                </div>
                
                <!-- Payment Details -->
                <div class="payment-details">
                    <h3>Payment Details</h3>';

    // Add payment details
    $html .= '
                    <div class="detail-row">
                        <span class="detail-label">📋 Category</span>
                        <span class="detail-value highlight">' . $category . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📅 Payment Date</span>
                        <span class="detail-value">' . $paymentDate . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">💳 Payment Method</span>
                        <span class="detail-value">' . ucfirst($paymentDetails['payment_method']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">💵 Amount Paid</span>
                        <span class="detail-value amount">₱' . number_format($paymentDetails['amount_paid'], 2) . '</span>
                    </div>';

    // Add reference number if provided
    if (!empty($paymentDetails['reference_number'])) {
        $html .= '
                    <div class="detail-row">
                        <span class="detail-label">🔢 Reference Number</span>
                        <span class="detail-value">' . htmlspecialchars($paymentDetails['reference_number']) . '</span>
                    </div>';
    }

    $html .= '
                </div>';

    // Add specific details based on category
    if ($paymentDetails['category'] === 'Amenity Fee' && !empty($paymentDetails['amenity_details'])) {
        $amenity = $paymentDetails['amenity_details'];
        $html .= '
                <!-- Amenity Details -->
                <div class="payment-details">
                    <h3>Amenity Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">🏢 Amenity</span>
                        <span class="detail-value">' . htmlspecialchars($amenity['amenity']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📅 Reservation Date</span>
                        <span class="detail-value">' . date('F j, Y', strtotime($amenity['reservation_date'])) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">🎫 Reservation Code</span>
                        <span class="detail-value">' . htmlspecialchars($amenity['reservation_code']) . '</span>
                    </div>
                </div>';
    } elseif ($paymentDetails['category'] === 'Monthly Dues' && !empty($paymentDetails['dues_details'])) {
        $dues = $paymentDetails['dues_details'];
        $html .= '
                <!-- Monthly Dues Details -->
                <div class="payment-details">
                    <h3>Monthly Dues Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">📅 Billing Month</span>
                        <span class="detail-value">' . date('F Y', strtotime($dues['billing_month'])) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📋 Household ID</span>
                        <span class="detail-value">' . htmlspecialchars($dues['household_id']) . '</span>
                    </div>
                </div>';
    }

    // Balance Section
    $html .= '
                <!-- Balance Information -->
                <div class="balance-section">
                    <h4>Balance Summary</h4>
                    <div class="balance-row">
                        <span class="balance-label">Total Amount</span>
                        <span class="balance-amount">₱' . number_format($paymentDetails['total_amount'], 2) . '</span>
                    </div>
                    <div class="balance-row">
                        <span class="balance-label">Total Paid</span>
                        <span class="balance-amount balance-paid">₱' . number_format($paymentDetails['total_paid'], 2) . '</span>
                    </div>
                    <div class="balance-row">
                        <span class="balance-label">Remaining Balance</span>
                        <span class="balance-amount balance-remaining">₱' . number_format($paymentDetails['remaining_balance'], 2) . '</span>
                    </div>
                </div>
                
                <!-- Payment Status -->
                <div class="status-section">
                    <div class="status-icon">' . ($paymentDetails['remaining_balance'] <= 0 ? '✅' : '⏳') . '</div>
                    <div class="status-text">' . $statusText . '</div>
                </div>';

    // Important Information
    $html .= '
                <!-- Important Information -->
                <div class="important-section">
                    <h4>Important Information</h4>
                    <ul>
                        <li>This payment receipt serves as proof of your transaction.</li>
                        <li>Please keep this email for your records.</li>';
    
    if ($paymentDetails['remaining_balance'] > 0) {
        $html .= '
                        <li><strong>Outstanding balance:</strong> ₱' . number_format($paymentDetails['remaining_balance'], 2) . ' - Please settle this amount at your earliest convenience.</li>';
    } else {
        $html .= '
                        <li><strong>Account Status:</strong> Your payment is now complete. Thank you!</li>';
    }
    
    $html .= '
                        <li>For any payment inquiries, please contact our office with your invoice number.</li>
                        <li>Payment processing may take 1-2 business days to reflect in your account.</li>
                    </ul>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-section">
                    <h4>Need Help?</h4>
                    <p>For questions about your payment or account:</p>
                    <p class="phone">📞 8-2457647</p>
                    <p>📧 admin@nsshai.com</p>
                </div>
                
                <div style="text-align: center; margin-top: 30px; color: #666;">
                    <p>Thank you for your prompt payment!</p>
                    <p style="margin-top: 15px;"><strong>Best regards,<br>NSSHAI Administration Team</strong></p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <h4>Neopolitan Sitio Seville Homeowners Association, Inc.</h4>
                <p>This is an automated payment confirmation email. Please do not reply directly to this message.</p>
                <p>For support and inquiries, please contact our office at 8-2457647</p>
                <p style="margin-top: 15px; font-size: 12px;">© 2025 NSSHAI. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

// Generate plain text version for email clients that don't support HTML
function generatePaymentPlainTextEmail($recipientName, $paymentDetails)
{
    $text = "PAYMENT RECEIPT - NSSHAI\n";
    $text .= "========================\n\n";
    $text .= "Hello " . $recipientName . "!\n\n";
    $text .= "Thank you for your payment! We have successfully received and processed your payment.\n\n";
    $text .= "INVOICE NUMBER: " . $paymentDetails['invoice_number'] . "\n\n";

    $text .= "PAYMENT DETAILS:\n";
    $text .= "- Category: " . $paymentDetails['category'] . "\n";
    $text .= "- Payment Date: " . date('F j, Y', strtotime($paymentDetails['payment_date'])) . "\n";
    $text .= "- Payment Method: " . ucfirst($paymentDetails['payment_method']) . "\n";
    $text .= "- Amount Paid: ₱" . number_format($paymentDetails['amount_paid'], 2) . "\n";

    if (!empty($paymentDetails['reference_number'])) {
        $text .= "- Reference Number: " . $paymentDetails['reference_number'] . "\n";
    }

    $text .= "\nBALANCE SUMMARY:\n";
    $text .= "- Total Amount: ₱" . number_format($paymentDetails['total_amount'], 2) . "\n";
    $text .= "- Total Paid: ₱" . number_format($paymentDetails['total_paid'], 2) . "\n";
    $text .= "- Remaining Balance: ₱" . number_format($paymentDetails['remaining_balance'], 2) . "\n";

    $text .= "\nSTATUS: " . ($paymentDetails['remaining_balance'] <= 0 ? 'PAID IN FULL' : 'PARTIALLY PAID') . "\n";

    $text .= "\nIMPORTANT REMINDERS:\n";
    $text .= "- Keep this receipt for your records\n";
    if ($paymentDetails['remaining_balance'] > 0) {
        $text .= "- Outstanding balance: ₱" . number_format($paymentDetails['remaining_balance'], 2) . "\n";
    }
    $text .= "- Contact us at 8-2457647 for questions\n";
    
    $text .= "\nBest regards,\nNSSHAI Administration Team";

    return $text;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    
    try {
        // Get admin_id from session
        $admin_id = $_SESSION['admin_id'] ?? "system";
        
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
        
        $conn->begin_transaction();
        
        $reference_id = null;
        $household_id = ($user_type === 'Homeowner/Resident') ? $user_id : null;
        $visitor_id = ($user_type === 'Visitor') ? $user_id : null;
        $paymentDetails = null;
        
        if ($category === 'Amenity Fee') {
            // Get amenity booking details
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
            
            // Determine new status
            if ($balance <= 0) {
                $new_status = 'paid';
            } elseif ($new_amount_paid > 0) {
                $new_status = 'partial';
            } else {
                $new_status = 'pending';
            }
            
            // Update amenity booking
            $stmt = $conn->prepare("UPDATE amenity_bookings SET amount_paid = ?, status = ? WHERE id = ?");
            $stmt->bind_param("dsi", $new_amount_paid, $new_status, $reference_id);
            $stmt->execute();
            
            // Prepare payment details for email
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
                'payment_status' => $new_status,
                'amenity_details' => [
                    'amenity' => $booking['amenity'],
                    'reservation_date' => $booking['reservation_date'],
                    'reservation_code' => $booking['reservation_code']
                ]
            ];
            
        } elseif ($category === 'Monthly Dues') {
            if ($user_type !== 'Homeowner/Resident') {
                throw new Exception('Monthly dues only apply to homeowners/residents');
            }
            
            // Get monthly dues details
            $stmt = $conn->prepare("SELECT * FROM monthly_dues WHERE household_id = ? AND invoice_number = ?");
            $stmt->bind_param("ss", $user_id, $invoice_number);
            $stmt->execute();
            $dues = $stmt->get_result()->fetch_assoc();
            
            if (!$dues) {
                throw new Exception('Monthly dues record not found');
            }
            
            $reference_id = $dues['id'];
            $new_amount_paid = $dues['amount_paid'] + $amount;
            $new_balance = $dues['balance_remaining'] - $amount;
            
            // Ensure balance doesn't go negative
            if ($new_balance < 0) {
                $new_balance = 0;
            }
            
            // Determine new status
            if ($new_balance <= 0) {
                $new_status = 'Completed';
            } elseif ($new_amount_paid > 0) {
                $new_status = 'Partial';
            } else {
                $new_status = 'Pending';
            }
            
            // Update monthly dues
            $stmt = $conn->prepare("UPDATE monthly_dues SET amount_paid = ?, balance_remaining = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ddsi", $new_amount_paid, $new_balance, $new_status, $reference_id);
            $stmt->execute();
            
            // Prepare payment details for email
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
                'payment_status' => $new_status,
                'dues_details' => [
                    'billing_month' => $dues['billing_month'],
                    'household_id' => $dues['household_id']
                ]
            ];
        }
        
        // Insert payment record
        $db_category = ($category === 'Amenity Fee') ? 'amenity' : 'monthly_dues';
        $stmt = $conn->prepare("
            INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sisssdssss", $db_category, $reference_id, $invoice_number, $db_user_type, $household_id, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
        $stmt->execute();
        
        $payment_id = $conn->insert_id;
        
        $conn->commit();
        
        // Send payment receipt email
        $recipientName = trim($userDetails['first_name'] . ' ' . $userDetails['last_name']);
        $recipientEmail = $userDetails['email_address'];

        // Log everything before attempting to send
        error_log("========== EMAIL SENDING ATTEMPT ==========");
        error_log("Recipient Name: " . $recipientName);
        error_log("Recipient Email: " . ($recipientEmail ?? 'NULL/EMPTY'));
        error_log("Email is empty: " . (empty($recipientEmail) ? 'YES' : 'NO'));
        error_log("Payment details exist: " . ($paymentDetails ? 'YES' : 'NO'));
        error_log("Payment details: " . print_r($paymentDetails, true));

        if (!empty($recipientEmail) && $paymentDetails) {
            error_log("Calling sendPaymentReceipt function...");
            $emailSent = sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails);
            
            if ($emailSent) {
                error_log("✅ Email sent successfully");
            } else {
                error_log("❌ Email sending returned FALSE");
            }
        } else {
            error_log("❌ Email NOT sent - conditions not met:");
            if (empty($recipientEmail)) {
                error_log("   - Recipient email is empty");
            }
            if (!$paymentDetails) {
                error_log("   - Payment details is NULL");
            }
            $emailSent = false;
        }
        error_log("========== END EMAIL ATTEMPT ==========");
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Payment processed successfully',
            'payment_id' => $payment_id,
            'email_sent' => isset($emailSent) ? $emailSent : false
        ]);
        
    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        
        error_log("❌ Payment processing error: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

// If not a POST request or missing action, redirect back to payment page
header("Location: ../payment.php");
exit;
?>