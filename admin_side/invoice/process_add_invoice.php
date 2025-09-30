<?php
session_start();
require '../../rfid-api/db.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files - adjust path based on your structure
require_once '../amenity_booking/PHPMailer/src/Exception.php';
require_once '../amenity_booking/PHPMailer/src/PHPMailer.php';
require_once '../amenity_booking/PHPMailer/src/SMTP.php';

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

// Show errors during dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Set content type for AJAX response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Function to get household details and email
function getHouseholdDetails($conn, $household_id)
{
    $stmt = $conn->prepare("SELECT household_id, first_name, middle_name, last_name, email_address FROM household_accounts WHERE household_id = ?");
    $stmt->bind_param("s", $household_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row;
    }
    $stmt->close();
    return null;
}

// Robust email sending function using PHPMailer
function sendMonthlyDuesInvoice($recipientEmail, $recipientName, $invoiceDetails)
{
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
        $mail->Subject = 'Monthly Dues Invoice - NSSHAI [' . $invoiceDetails['invoice_number'] . ']';

        // Create beautiful HTML email content
        $mail->Body = generateInvoiceEmailTemplate($recipientName, $invoiceDetails);

        // Alternative plain text version
        $mail->AltBody = generateInvoicePlainTextEmail($recipientName, $invoiceDetails);

        // Send the email
        $result = $mail->send();

        // Log success
        error_log("✅ PHPMailer: Monthly dues invoice sent successfully to " . $recipientEmail);
        return true;

    } catch (Exception $e) {
        // Log the error
        error_log("❌ PHPMailer Error: {$mail->ErrorInfo}");
        error_log("❌ Exception: {$e->getMessage()}");
        return false;
    }
}

// Generate HTML email template for monthly dues invoice
function generateInvoiceEmailTemplate($recipientName, $invoiceDetails)
{
    $invoiceNumber = htmlspecialchars($invoiceDetails['invoice_number']);
    $billingMonth = date('F Y', strtotime($invoiceDetails['billing_month']));
    $dueDate = date('F j, Y', strtotime($invoiceDetails['due_date']));
    $amount = number_format($invoiceDetails['balance_remaining'], 2);

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Monthly Dues Invoice</title>
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
            
            .invoice-banner { background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%); border: 2px solid #ffc107; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; position: relative; overflow: hidden; }
            .invoice-banner::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,193,7,0.1) 0%, transparent 70%); }
            .invoice-banner .icon { font-size: 48px; margin-bottom: 15px; position: relative; z-index: 2; }
            .invoice-banner h3 { font-size: 18px; color: #856404; margin-bottom: 10px; position: relative; z-index: 2; }
            .invoice-banner .invoice { font-size: 32px; font-weight: bold; color: #856404; letter-spacing: 2px; font-family: "Courier New", monospace; position: relative; z-index: 2; }
            
            .invoice-details { background-color: #f8f9fa; border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 5px solid #198754; }
            .invoice-details h3 { color: #198754; font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; }
            .invoice-details h3::before { content: "📋"; margin-right: 10px; }
            
            .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: 600; color: #495057; font-size: 14px; flex: 0 0 auto; margin-right: 20px; }
            .detail-value { color: #212529; font-size: 14px; text-align: right; flex: 0 0 auto; }
            .detail-value.highlight { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
            .detail-value.amount { font-size: 18px; font-weight: bold; color: #dc3545; }

            .amount-section { background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 25px; margin: 30px 0; text-align: center; }
            .amount-section h4 { color: #856404; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; }
            .amount-section h4::before { content: "💰"; margin-right: 10px; }
            .amount-section .amount { font-size: 36px; font-weight: bold; color: #dc3545; margin: 15px 0; }
            .amount-section .currency { font-size: 20px; }

            .due-notice { background: linear-gradient(135deg, #f8d7da 0%, #fdecea 100%); border: 1px solid #dc3545; border-radius: 12px; padding: 25px; margin: 30px 0; text-align: center; }
            .due-notice .icon { font-size: 48px; margin-bottom: 10px; }
            .due-notice h4 { color: #721c24; font-size: 18px; margin-bottom: 10px; }
            .due-notice .date { font-size: 24px; font-weight: bold; color: #dc3545; }
            
            .payment-methods { background-color: #e8f5e8; border-radius: 12px; padding: 25px; margin: 30px 0; }
            .payment-methods h4 { color: #198754; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; }
            .payment-methods h4::before { content: "💳"; margin-right: 10px; }
            .payment-methods ul { list-style: none; padding: 0; }
            .payment-methods li { color: #2d5a2d; margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px; line-height: 1.5; }
            .payment-methods li::before { content: "•"; color: #198754; font-weight: bold; position: absolute; left: 0; }
            
            .important-section { background: linear-gradient(135deg, #d1ecf1 0%, #e7f3ff 100%); border: 1px solid #17a2b8; border-radius: 12px; padding: 25px; margin: 30px 0; }
            .important-section h4 { color: #0c5460; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; }
            .important-section h4::before { content: "ℹ️"; margin-right: 10px; }
            .important-section ul { list-style: none; padding: 0; }
            .important-section li { color: #0c5460; margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px; line-height: 1.5; }
            .important-section li::before { content: "•"; color: #17a2b8; font-weight: bold; position: absolute; left: 0; }
            
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
                .invoice-banner .invoice { font-size: 24px; }
                .detail-row { flex-direction: column; align-items: flex-start; }
                .detail-value { text-align: left; margin-top: 5px; }
                .amount-section .amount { font-size: 28px; }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <h1>Monthly Dues Invoice</h1>
                <p>Neopolitan Sitio Seville Homeowners Association</p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <div class="greeting">Hello ' . htmlspecialchars($recipientName) . '!</div>
                
                <div class="intro-text">
                    Your monthly HOA dues invoice for <strong>' . $billingMonth . '</strong> has been generated. Please review the details below and ensure payment is made by the due date to avoid any late fees.
                </div>
                
                <!-- Invoice Number Banner -->
                <div class="invoice-banner">
                    <div class="icon">🧾</div>
                    <h3>Invoice Number</h3>
                    <div class="invoice">' . $invoiceNumber . '</div>
                </div>
                
                <!-- Invoice Details -->
                <div class="invoice-details">
                    <h3>Invoice Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">📋 Billing Period</span>
                        <span class="detail-value highlight">' . $billingMonth . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">🏠 Household ID</span>
                        <span class="detail-value">' . htmlspecialchars($invoiceDetails['household_id']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📅 Invoice Date</span>
                        <span class="detail-value">' . date('F j, Y') . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">⚠️ Due Date</span>
                        <span class="detail-value amount">' . $dueDate . '</span>
                    </div>
                </div>
                
                <!-- Amount Due -->
                <div class="amount-section">
                    <h4>Amount Due</h4>
                    <div class="amount">
                        <span class="currency">₱</span>' . $amount . '
                    </div>
                    <p style="color: #856404; margin: 0;">Monthly HOA Dues</p>
                </div>
                
                <!-- Due Date Notice -->
                <div class="due-notice">
                    <div class="icon">⏰</div>
                    <h4>Payment Due Date</h4>
                    <div class="date">' . $dueDate . '</div>
                    <p style="color: #721c24; margin: 10px 0 0 0; font-size: 14px;">Please ensure payment is made by this date to avoid late fees.</p>
                </div>
                
                <!-- Payment Methods -->
                <div class="payment-methods">
                    <h4>Payment Options</h4>
                    <ul>
                        <li><strong>Cash Payment:</strong> Visit the NSSHAI office during business hours</li>
                        <li><strong>Bank Transfer:</strong> Contact the office for bank account details</li>
                        <li><strong>Online Payment:</strong> Use our online payment portal (if available)</li>
                        <li><strong>Check Payment:</strong> Make checks payable to "NSSHAI"</li>
                    </ul>
                </div>
                
                <!-- Important Information -->
                <div class="important-section">
                    <h4>Important Reminders</h4>
                    <ul>
                        <li>Keep this invoice for your records</li>
                        <li>Late payments may incur additional charges as per HOA bylaws</li>
                        <li>If you have questions about your dues, contact our office immediately</li>
                        <li>Payment confirmations will be sent via email upon processing</li>
                        <li>For disputes or payment arrangements, please contact us before the due date</li>
                    </ul>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-section">
                    <h4>Questions or Concerns?</h4>
                    <p>Contact the NSSHAI office:</p>
                    <p class="phone">📞 8-2457647</p>
                    <p>📧 admin@nsshai.com</p>
                    <p>🏢 NSSHAI Clubhouse, Narra St.<br>Neopolitan Sitio Seville, North Fairview</p>
                </div>
                
                <div style="text-align: center; margin-top: 30px; color: #666;">
                    <p>Thank you for your continued support of our community!</p>
                    <p style="margin-top: 15px;"><strong>Best regards,<br>NSSHAI Administration Team</strong></p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <h4>Neopolitan Sitio Seville Homeowners Association, Inc.</h4>
                <p>This is an automated invoice notification. Please do not reply directly to this message.</p>
                <p>For payment inquiries and support, please contact our office at 8-2457647</p>
                <p style="margin-top: 15px; font-size: 12px;">© 2025 NSSHAI. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

// Generate plain text version for email clients that don't support HTML
function generateInvoicePlainTextEmail($recipientName, $invoiceDetails)
{
    $billingMonth = date('F Y', strtotime($invoiceDetails['billing_month']));
    $dueDate = date('F j, Y', strtotime($invoiceDetails['due_date']));
    $amount = number_format($invoiceDetails['balance_remaining'], 2);

    $text = "MONTHLY DUES INVOICE - NSSHAI\n";
    $text .= "=============================\n\n";
    $text .= "Hello " . $recipientName . "!\n\n";
    $text .= "Your monthly HOA dues invoice for " . $billingMonth . " has been generated.\n\n";
    $text .= "INVOICE NUMBER: " . $invoiceDetails['invoice_number'] . "\n\n";

    $text .= "INVOICE DETAILS:\n";
    $text .= "- Billing Period: " . $billingMonth . "\n";
    $text .= "- Household ID: " . $invoiceDetails['household_id'] . "\n";
    $text .= "- Invoice Date: " . date('F j, Y') . "\n";
    $text .= "- Due Date: " . $dueDate . "\n";
    $text .= "- Amount Due: ₱" . $amount . "\n\n";

    $text .= "PAYMENT DUE: " . $dueDate . "\n";
    $text .= "Please ensure payment is made by this date to avoid late fees.\n\n";

    $text .= "PAYMENT OPTIONS:\n";
    $text .= "- Cash Payment: Visit the NSSHAI office during business hours\n";
    $text .= "- Bank Transfer: Contact the office for bank account details\n";
    $text .= "- Online Payment: Use our online payment portal (if available)\n";
    $text .= "- Check Payment: Make checks payable to \"NSSHAI\"\n\n";

    $text .= "IMPORTANT REMINDERS:\n";
    $text .= "- Keep this invoice for your records\n";
    $text .= "- Late payments may incur additional charges\n";
    $text .= "- Contact us at 8-2457647 for questions\n";
    $text .= "- Payment confirmations will be sent via email\n\n";

    $text .= "Contact Information:\n";
    $text .= "Phone: 8-2457647\n";
    $text .= "Email: admin@nsshai.com\n";
    $text .= "Address: NSSHAI Clubhouse, Narra St., Neopolitan Sitio Seville\n\n";

    $text .= "Best regards,\nNSSHAI Administration Team";

    return $text;
}

// Generate invoice number
function generateInvoiceNumber($conn)
{
    $date = date('Ymd');
    $prefix = "INV-$date";

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_dues WHERE invoice_number LIKE CONCAT(?, '%')");
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $count = $result['count'] + 1;
    $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
    return "$prefix-$sequence";
}

try {
    // Validate POST data exists
    if (
        !isset($_POST['household_id']) || !isset($_POST['billing_month']) ||
        !isset($_POST['balance_remaining']) || !isset($_POST['due_date'])
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'Missing required fields.'
        ]);
        exit;
    }

    $household_id = trim($_POST['household_id']);
    $billing_month_input = trim($_POST['billing_month']); // This is YYYY-MM format
    $balance_remaining = floatval($_POST['balance_remaining']);
    $due_date = trim($_POST['due_date']);

    // Convert billing_month from YYYY-MM to YYYY-MM-01 for DATE field
    $billing_month = $billing_month_input . '-01';

    // Validate required fields are not empty
    if (empty($household_id) || empty($billing_month_input) || empty($due_date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'All fields are required.'
        ]);
        exit;
    }

    // Validate balance_remaining is positive
    if ($balance_remaining <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'Balance amount must be greater than zero.'
        ]);
        exit;
    }

    // Validate date format
    $due_date_obj = DateTime::createFromFormat('Y-m-d', $due_date);
    if (!$due_date_obj || $due_date_obj->format('Y-m-d') !== $due_date) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'Invalid due date format.'
        ]);
        exit;
    }

    // ✅ VALIDATION 1: Check if due date has already passed
    $today = date('Y-m-d');

    if ($due_date <= $today) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'Cannot create invoice with a due date that has already passed (' . date('F j, Y', strtotime($due_date)) . '). Due date must be in the future.'
        ]);
        exit;
    }

    // ✅ VALIDATION 2: Check for duplicate monthly dues (same household + same billing month)
    // Changed to check billing_month instead of due_date for better duplicate detection
    $stmt = $conn->prepare("SELECT invoice_number FROM monthly_dues WHERE household_id = ? AND billing_month = ?");
    $stmt->bind_param("ss", $household_id, $billing_month);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existingInvoice = $result->fetch_assoc();
        $stmt->close();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'An invoice for this household already exists for ' . date('F Y', strtotime($billing_month)) . '. Existing invoice: ' . $existingInvoice['invoice_number']
        ]);
        exit;
    }
    $stmt->close();

    // Get household details
    $householdDetails = getHouseholdDetails($conn, $household_id);
    if (!$householdDetails) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_type' => 'validation',
            'error' => 'Household not found.'
        ]);
        exit;
    }

    // Generate invoice number
    $invoice_number = generateInvoiceNumber($conn);

    // Insert with amount_paid defaulting to 0.00 and status as 'Pending'
    $amount_paid = 0.00;
    $status = 'Pending';

    $stmt = $conn->prepare("INSERT INTO monthly_dues 
        (invoice_number, household_id, billing_month, amount_paid, balance_remaining, due_date, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters - all 7 fields
    $stmt->bind_param(
        "sssddss",
        $invoice_number,     // string
        $household_id,       // string
        $billing_month,      // string (DATE format YYYY-MM-DD)
        $amount_paid,        // decimal (defaulting to 0.00)
        $balance_remaining,  // decimal
        $due_date,          // string (DATE format YYYY-MM-DD)
        $status             // string (ENUM: 'Pending')
    );

    if ($stmt->execute()) {
        $stmt->close();

        // Prepare invoice details for email
        $invoiceDetails = [
            'invoice_number' => $invoice_number,
            'household_id' => $household_id,
            'billing_month' => $billing_month,
            'balance_remaining' => $balance_remaining,
            'due_date' => $due_date,
            'status' => $status
        ];

        // Send invoice notification email
        $recipientName = trim($householdDetails['first_name'] . ' ' . ($householdDetails['middle_name'] ? $householdDetails['middle_name'] . ' ' : '') . $householdDetails['last_name']);
        $recipientEmail = $householdDetails['email_address'];

        $emailSent = false;
        if (!empty($recipientEmail)) {
            $emailSent = sendMonthlyDuesInvoice($recipientEmail, $recipientName, $invoiceDetails);

            if ($emailSent) {
                error_log("✅ Monthly dues invoice email sent successfully to: " . $recipientEmail . " [Invoice: " . $invoice_number . "]");
            } else {
                error_log("❌ Failed to send monthly dues invoice email to: " . $recipientEmail . " [Invoice: " . $invoice_number . "]");
            }
        } else {
            error_log("⚠️ No email address found for household: " . $household_id . " [Invoice: " . $invoice_number . "]");
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Invoice created successfully',
            'invoice_number' => $invoice_number,
            'email_sent' => $emailSent,
            'recipient_email' => $recipientEmail
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error_type' => 'database',
            'error' => 'Database insert failed: ' . $error
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error_type' => 'exception',
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>