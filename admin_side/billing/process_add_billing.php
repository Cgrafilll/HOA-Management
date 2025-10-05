<?php
// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Start output buffering to catch any accidental output
ob_start();

// Set JSON header FIRST (before any output)
header('Content-Type: application/json');

// Wrap everything in try-catch
try {
    // Start session (only once!)
    session_start();
    
    // Check and require database connection
    $dbPath = '../../rfid-api/db.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database configuration file not found at: ' . $dbPath);
    }
    require $dbPath;
    
    // Check database connection
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not established');
    }
    
    // Check and require PHPMailer files
    $phpmailerPath = '../amenity_booking/PHPMailer/src/';
    
    $requiredFiles = [
        $phpmailerPath . 'Exception.php',
        $phpmailerPath . 'PHPMailer.php',
        $phpmailerPath . 'SMTP.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception('PHPMailer file not found: ' . $file);
        }
    }
    
    require_once $phpmailerPath . 'Exception.php';
    require_once $phpmailerPath . 'PHPMailer.php';
    require_once $phpmailerPath . 'SMTP.php';

    // Clear any accidental output before proceeding
    ob_clean();

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

    // Enable mysqli error reporting
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Function to get household details
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

    // Function to get all active households
    function getAllHouseholds($conn)
    {
        $stmt = $conn->prepare("SELECT household_id, first_name, middle_name, last_name, email_address FROM household_accounts ORDER BY household_id");
        $stmt->execute();
        $result = $stmt->get_result();
        $households = [];
        while ($row = $result->fetch_assoc()) {
            $households[] = $row;
        }
        $stmt->close();
        return $households;
    }

    // Email sending function
    function sendInvoiceEmail($recipientEmail, $recipientName, $invoiceDetails)
    {
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
            $mail->addReplyTo(EmailConfig::REPLY_TO, 'NSSHAI Admin');

            $mail->isHTML(true);
            
            // Set subject based on category
            $category = $invoiceDetails['category'];
            $categoryName = ucwords(str_replace('_', ' ', $category));
            $mail->Subject = $categoryName . ' Invoice - NSSHAI [' . $invoiceDetails['invoice_number'] . ']';
            
            // Generate email template based on category
            $mail->Body = generateInvoiceEmailTemplate($recipientName, $invoiceDetails);
            $mail->AltBody = generateInvoicePlainTextEmail($recipientName, $invoiceDetails);

            $result = $mail->send();
            error_log("✅ Invoice email sent to: " . $recipientEmail);
            return true;

        } catch (Exception $e) {
            error_log("❌ PHPMailer Error: {$mail->ErrorInfo}");
            error_log("❌ Exception: {$e->getMessage()}");
            return false;
        }
    }

    // HTML email template function
    function generateInvoiceEmailTemplate($recipientName, $invoiceDetails)
    {
        $invoiceNumber = htmlspecialchars($invoiceDetails['invoice_number']);
        $category = $invoiceDetails['category'];
        $categoryName = ucwords(str_replace('_', ' ', $category));
        $dueDate = date('F j, Y', strtotime($invoiceDetails['due_date']));
        $amount = number_format($invoiceDetails['balance_remaining'], 2);

        // Category-specific content
        $categoryIcon = '🧾';
        $categoryColor = '#198754';
        $categoryBg = '#e8f5e8';
        $billingPeriodRow = '';
        $descriptionSection = '';

        if ($category === 'monthly_dues') {
            $categoryIcon = '🏠';
            $billingMonth = date('F Y', strtotime($invoiceDetails['billing_month']));
            $billingPeriodRow = '
                <div class="detail-row">
                    <span class="detail-label">📋 Billing Period</span>
                    <span class="detail-value highlight">' . $billingMonth . '</span>
                </div>';
        } elseif ($category === 'penalty_fees') {
            $categoryIcon = '⚠️';
            $categoryColor = '#dc3545';
            $categoryBg = '#f8d7da';
            $descriptionSection = '
                <div class="description-section">
                    <h4>Fee Description</h4>
                    <p>' . nl2br(htmlspecialchars($invoiceDetails['description'])) . '</p>
                </div>';
        } elseif ($category === 'other_fees') {
            $categoryIcon = '📝';
            $categoryColor = '#0dcaf0';
            $categoryBg = '#cff4fc';
            $descriptionSection = '
                <div class="description-section">
                    <h4>Fee Description</h4>
                    <p>' . nl2br(htmlspecialchars($invoiceDetails['description'])) . '</p>
                </div>';
        }

        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $categoryName . ' Invoice</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f6f9fc; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                
                .header { background: linear-gradient(135deg, ' . $categoryColor . ' 0%, #20c997 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
                .header p { font-size: 16px; opacity: 0.9; margin: 0; }
                
                .content { padding: 40px 30px; }
                .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 20px; }
                .intro-text { font-size: 16px; color: #34495e; line-height: 1.6; margin-bottom: 30px; }
                
                .invoice-banner { background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%); border: 2px solid #ffc107; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; position: relative; overflow: hidden; }
                .invoice-banner .icon { font-size: 48px; margin-bottom: 15px; }
                .invoice-banner h3 { font-size: 18px; color: #856404; margin-bottom: 10px; }
                .invoice-banner .invoice { font-size: 32px; font-weight: bold; color: #856404; letter-spacing: 2px; font-family: "Courier New", monospace; }
                
                .invoice-details { background-color: #f8f9fa; border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 5px solid ' . $categoryColor . '; }
                .invoice-details h3 { color: ' . $categoryColor . '; font-size: 20px; margin-bottom: 20px; }
                
                .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #495057; font-size: 14px; }
                .detail-value { color: #212529; font-size: 14px; text-align: right; }
                .detail-value.highlight { background-color: ' . $categoryBg . '; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
                .detail-value.amount { font-size: 18px; font-weight: bold; color: #dc3545; }

                .description-section { background-color: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid ' . $categoryColor . '; }
                .description-section h4 { color: ' . $categoryColor . '; margin-bottom: 10px; }
                .description-section p { color: #495057; line-height: 1.6; }

                .amount-section { background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 25px; margin: 30px 0; text-align: center; }
                .amount-section h4 { color: #856404; font-size: 18px; margin-bottom: 15px; }
                .amount-section .amount { font-size: 36px; font-weight: bold; color: #dc3545; margin: 15px 0; }

                .due-notice { background: linear-gradient(135deg, #f8d7da 0%, #fdecea 100%); border: 1px solid #dc3545; border-radius: 12px; padding: 25px; margin: 30px 0; text-align: center; }
                .due-notice .icon { font-size: 48px; margin-bottom: 10px; }
                .due-notice h4 { color: #721c24; font-size: 18px; margin-bottom: 10px; }
                .due-notice .date { font-size: 24px; font-weight: bold; color: #dc3545; }
                
                .payment-methods { background-color: #e8f5e8; border-radius: 12px; padding: 25px; margin: 30px 0; }
                .payment-methods h4 { color: #198754; font-size: 18px; margin-bottom: 15px; }
                .payment-methods ul { list-style: none; padding: 0; }
                .payment-methods li { color: #2d5a2d; margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px; }
                .payment-methods li::before { content: "•"; color: #198754; font-weight: bold; position: absolute; left: 0; }
                
                .contact-section { background-color: #e8f5e8; border-radius: 12px; padding: 20px; margin: 30px 0; text-align: center; }
                .contact-section h4 { color: #198754; margin-bottom: 10px; }
                .contact-section p { color: #2d5a2d; margin: 5px 0; font-size: 14px; }
                
                .footer { background-color: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; }
                .footer p { margin: 5px 0; font-size: 13px; opacity: 0.8; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h1>' . $categoryName . ' Invoice</h1>
                    <p>Neopolitan Sitio Seville Homeowners Association</p>
                </div>
                
                <div class="content">
                    <div class="greeting">Hello ' . htmlspecialchars($recipientName) . '!</div>
                    
                    <div class="intro-text">
                        A new ' . strtolower($categoryName) . ' invoice has been generated for your household. Please review the details below and ensure payment is made by the due date.
                    </div>
                    
                    <div class="invoice-banner">
                        <div class="icon">' . $categoryIcon . '</div>
                        <h3>Invoice Number</h3>
                        <div class="invoice">' . $invoiceNumber . '</div>
                    </div>
                    
                    <div class="invoice-details">
                        <h3>Invoice Details</h3>
                        ' . $billingPeriodRow . '
                        <div class="detail-row">
                            <span class="detail-label">🏠 Household ID</span>
                            <span class="detail-value">' . htmlspecialchars($invoiceDetails['household_id']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">📂 Category</span>
                            <span class="detail-value">' . $categoryName . '</span>
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
                    
                    ' . $descriptionSection . '
                    
                    <div class="amount-section">
                        <h4>Amount Due</h4>
                        <div class="amount">₱' . $amount . '</div>
                    </div>
                    
                    <div class="due-notice">
                        <div class="icon">⏰</div>
                        <h4>Payment Due Date</h4>
                        <div class="date">' . $dueDate . '</div>
                        <p style="color: #721c24; margin: 10px 0 0 0; font-size: 14px;">Please ensure payment is made by this date to avoid late fees.</p>
                    </div>
                    
                    <div class="payment-methods">
                        <h4>Payment Options</h4>
                        <ul>
                            <li><strong>Cash Payment:</strong> Visit the NSSHAI office during business hours</li>
                            <li><strong>Bank Transfer:</strong> Contact the office for bank account details</li>
                            <li><strong>Online Payment:</strong> Use our online payment portal (if available)</li>
                            <li><strong>Check Payment:</strong> Make checks payable to "NSSHAI"</li>
                        </ul>
                    </div>
                    
                    <div class="contact-section">
                        <h4>Questions or Concerns?</h4>
                        <p>Contact the NSSHAI office:</p>
                        <p>📞 8-2457647 | 📧 admin@nsshai.com</p>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px; color: #666;">
                        <p>Thank you for your continued support of our community!</p>
                        <p style="margin-top: 15px;"><strong>Best regards,<br>NSSHAI Administration Team</strong></p>
                    </div>
                </div>
                
                <div class="footer">
                    <p>© 2025 NSSHAI. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }

    // Plain text email function
    function generateInvoicePlainTextEmail($recipientName, $invoiceDetails)
    {
        $category = $invoiceDetails['category'];
        $categoryName = ucwords(str_replace('_', ' ', $category));
        $dueDate = date('F j, Y', strtotime($invoiceDetails['due_date']));
        $amount = number_format($invoiceDetails['balance_remaining'], 2);

        $text = strtoupper($categoryName) . " INVOICE - NSSHAI\n";
        $text .= "=============================\n\n";
        $text .= "Hello " . $recipientName . "!\n\n";
        $text .= "A new " . strtolower($categoryName) . " invoice has been generated for your household.\n\n";
        $text .= "INVOICE NUMBER: " . $invoiceDetails['invoice_number'] . "\n\n";

        $text .= "INVOICE DETAILS:\n";
        if ($category === 'monthly_dues') {
            $billingMonth = date('F Y', strtotime($invoiceDetails['billing_month']));
            $text .= "- Billing Period: " . $billingMonth . "\n";
        }
        $text .= "- Household ID: " . $invoiceDetails['household_id'] . "\n";
        $text .= "- Category: " . $categoryName . "\n";
        $text .= "- Invoice Date: " . date('F j, Y') . "\n";
        $text .= "- Due Date: " . $dueDate . "\n";
        $text .= "- Amount Due: ₱" . $amount . "\n\n";

        if (!empty($invoiceDetails['description'])) {
            $text .= "DESCRIPTION:\n" . $invoiceDetails['description'] . "\n\n";
        }

        $text .= "PAYMENT DUE: " . $dueDate . "\n\n";

        $text .= "PAYMENT OPTIONS:\n";
        $text .= "- Cash Payment: Visit the NSSHAI office\n";
        $text .= "- Bank Transfer: Contact office for details\n";
        $text .= "- Online Payment: Use our portal\n";
        $text .= "- Check Payment: Payable to \"NSSHAI\"\n\n";

        $text .= "Contact: 8-2457647 | admin@nsshai.com\n\n";
        $text .= "Best regards,\nNSSHAI Administration Team";

        return $text;
    }

    // Generate invoice number
    function generateInvoiceNumber($conn, $category)
    {
        $date = date('Ymd');
        $categoryPrefix = strtoupper(substr($category, 0, 3)); // MON, PEN, OTH
        $prefix = "$categoryPrefix-$date";
        $searchPattern = $prefix . '%';

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_dues WHERE invoice_number LIKE ?");
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $count = $result['count'] + 1;
        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
        return "$prefix-$sequence";
    }

    // Function to create single invoice
    function createInvoice($conn, $household_id, $category, $billing_month, $description, $balance_remaining, $due_date)
    {
        // Check for duplicate invoice (only for monthly_dues with same billing_month)
        if ($category === 'monthly_dues') {
            $stmt = $conn->prepare("SELECT invoice_number FROM monthly_dues WHERE household_id = ? AND billing_month = ? AND category = 'monthly_dues'");
            $stmt->bind_param("ss", $household_id, $billing_month);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $existingInvoice = $result->fetch_assoc();
                $stmt->close();
                return [
                    'success' => false,
                    'error' => 'Invoice already exists for this household for ' . date('F Y', strtotime($billing_month)) . ' (Invoice: ' . $existingInvoice['invoice_number'] . ')'
                ];
            }
            $stmt->close();
        }

        // Generate invoice number
        $invoice_number = generateInvoiceNumber($conn, $category);
        $amount_paid = 0.00;
        $status = 'Pending';

        // Prepare SQL based on category
        if ($category === 'monthly_dues') {
            $stmt = $conn->prepare("INSERT INTO monthly_dues 
                (invoice_number, household_id, category, billing_month, amount_paid, balance_remaining, due_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssddss", $invoice_number, $household_id, $category, $billing_month, 
                              $amount_paid, $balance_remaining, $due_date, $status);
        } else {
            // For penalty_fees and other_fees
            $stmt = $conn->prepare("INSERT INTO monthly_dues 
                (invoice_number, household_id, category, description, amount_paid, balance_remaining, due_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssddss", $invoice_number, $household_id, $category, $description, 
                              $amount_paid, $balance_remaining, $due_date, $status);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'error' => 'Database insert failed: ' . $error];
        }
        
        $stmt->close();

        return [
            'success' => true,
            'invoice_number' => $invoice_number,
            'household_id' => $household_id
        ];
    }

    // === MAIN PROCESSING LOGIC ===
    
    // Validate POST data
    if (!isset($_POST['category']) || !isset($_POST['balance_remaining']) || !isset($_POST['due_date'])) {
        throw new Exception('Missing required fields', 400);
    }

    $category = trim($_POST['category']);
    $balance_remaining = floatval($_POST['balance_remaining']);
    $due_date = trim($_POST['due_date']);
    $isBulk = isset($_POST['bulkInvoice']) && $_POST['bulkInvoice'] === 'on';

    // Validate category
    $validCategories = ['monthly_dues', 'penalty_fees', 'other_fees'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category selected', 400);
    }

    // Validate balance
    if ($balance_remaining <= 0) {
        throw new Exception('Balance amount must be greater than zero', 400);
    }

    // Validate due date
    $due_date_obj = DateTime::createFromFormat('Y-m-d', $due_date);
    if (!$due_date_obj || $due_date_obj->format('Y-m-d') !== $due_date) {
        throw new Exception('Invalid due date format', 400);
    }

    if ($due_date <= date('Y-m-d')) {
        throw new Exception('Cannot create invoice with a due date that has already passed (' . date('F j, Y', strtotime($due_date)) . ')', 400);
    }

    // Category-specific validation
    $billing_month = null;
    $description = null;

    if ($category === 'monthly_dues') {
        if (!isset($_POST['billing_month']) || empty($_POST['billing_month'])) {
            throw new Exception('Billing month is required for monthly dues', 400);
        }
        $billing_month_input = trim($_POST['billing_month']);
        $billing_month = $billing_month_input . '-01';
    } else {
        // penalty_fees or other_fees
        if (!isset($_POST['description']) || empty(trim($_POST['description']))) {
            throw new Exception('Description is required for ' . str_replace('_', ' ', $category), 400);
        }
        $description = trim($_POST['description']);
    }

    // Process bulk or single invoice
    if ($isBulk && $category === 'monthly_dues') {
        // BULK INVOICE CREATION
        $households = getAllHouseholds($conn);
        
        if (empty($households)) {
            throw new Exception('No households found in the system', 400);
        }

        $successCount = 0;
        $failCount = 0;
        $errors = [];
        $createdInvoices = [];

        foreach ($households as $household) {
            $result = createInvoice($conn, $household['household_id'], $category, $billing_month, 
                                   $description, $balance_remaining, $due_date);
            
            if ($result['success']) {
                $successCount++;
                $createdInvoices[] = $result['invoice_number'];
                
                // Send email notification
                $invoiceDetails = [
                    'invoice_number' => $result['invoice_number'],
                    'household_id' => $household['household_id'],
                    'category' => $category,
                    'billing_month' => $billing_month,
                    'balance_remaining' => $balance_remaining,
                    'due_date' => $due_date,
                    'status' => 'Pending'
                ];

                $recipientName = trim($household['first_name'] . ' ' . 
                                     ($household['middle_name'] ? $household['middle_name'] . ' ' : '') . 
                                     $household['last_name']);
                $recipientEmail = $household['email_address'];

                if (!empty($recipientEmail)) {
                    sendInvoiceEmail($recipientEmail, $recipientName, $invoiceDetails);
                }
            } else {
                $failCount++;
                $errors[] = $household['household_id'] . ': ' . $result['error'];
            }
        }

        $message = "Bulk invoice creation completed:<br>";
        $message .= "- Successfully created: <strong>$successCount</strong> invoice(s)<br>";
        if ($failCount > 0) {
            $message .= "- Failed: <strong>$failCount</strong> invoice(s)<br>";
            $message .= "<small>Some invoices may already exist for this billing period.</small>";
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $message,
            'bulk' => true,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'created_invoices' => $createdInvoices
        ]);

    } else {
        // SINGLE INVOICE CREATION
        if (!isset($_POST['household_id']) || empty($_POST['household_id'])) {
            throw new Exception('Household selection is required', 400);
        }

        $household_id = trim($_POST['household_id']);

        // Get household details
        $householdDetails = getHouseholdDetails($conn, $household_id);
        if (!$householdDetails) {
            throw new Exception('Household not found', 400);
        }

        // Create invoice
        $result = createInvoice($conn, $household_id, $category, $billing_month, 
                               $description, $balance_remaining, $due_date);

        if (!$result['success']) {
            throw new Exception($result['error'], 400);
        }

        // Send email notification
        $invoiceDetails = [
            'invoice_number' => $result['invoice_number'],
            'household_id' => $household_id,
            'category' => $category,
            'billing_month' => $billing_month,
            'description' => $description,
            'balance_remaining' => $balance_remaining,
            'due_date' => $due_date,
            'status' => 'Pending'
        ];

        $recipientName = trim($householdDetails['first_name'] . ' ' . 
                             ($householdDetails['middle_name'] ? $householdDetails['middle_name'] . ' ' : '') . 
                             $householdDetails['last_name']);
        $recipientEmail = $householdDetails['email_address'];

        $emailSent = false;
        if (!empty($recipientEmail)) {
            $emailSent = sendInvoiceEmail($recipientEmail, $recipientName, $invoiceDetails);
        }

        $categoryName = ucwords(str_replace('_', ' ', $category));
        $emailStatus = $emailSent ? 
            "Invoice $result[invoice_number] created successfully! Email notification sent to $recipientEmail." :
            "Invoice $result[invoice_number] created successfully! (Email notification could not be sent)";

        // Success response
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $emailStatus,
            'invoice_number' => $result['invoice_number'],
            'category' => $category,
            'email_sent' => $emailSent,
            'recipient_email' => $recipientEmail
        ]);
    }

} catch (Throwable $e) {
    // Catch all errors
    ob_clean();
    
    $statusCode = 500;
    $errorType = 'server_error';
    
    // Check if exception has a specific code
    if (method_exists($e, 'getCode') && $e->getCode() > 0) {
        $statusCode = $e->getCode();
    }
    
    if ($statusCode == 400) {
        $errorType = 'validation';
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error_type' => $errorType,
        'error' => $e->getMessage()
    ]);
    
    error_log('Invoice creation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

ob_end_flush();