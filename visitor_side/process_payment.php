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
    $db_path = '../rfid-api/db.php';
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
    $phpmailer_base = '../admin_side/amenity_booking/PHPMailer/src/';
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
        $statusBadge = '<span class="status-badge status-paid">PAID IN FULL</span>';
    } else {
        $statusColor = '#ffc107';
        $statusText = 'PARTIALLY PAID';
        $statusBadge = '<span class="status-badge status-partial">PARTIALLY PAID</span>';
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
            
            .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
            .status-badge.status-paid { background-color: #28a745; color: white; }
            .status-badge.status-partial { background-color: #ffc107; color: #212529; }
            
            .important-section { background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 25px; margin: 30px 0; }
            .important-section h4 { color: #856404; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; }
            .important-section h4::before { content: "⚠️"; margin-right: 10px; }
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
                .detail-row { flex-direction: column; align-items: flex-start; }
                .detail-value { text-align: left; margin-top: 5px; }
            }
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
                    Thank you for your payment! We have successfully received and processed your payment for <strong>' . $category . '</strong>. Your current payment status is ' . $statusBadge . '.
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
                </div>';

    // Add important reminders based on payment status
    if ($paymentDetails['remaining_balance'] > 0) {
        $html .= '
                <div class="important-section">
                    <h4>Important Reminders</h4>
                    <ul>
                        <li>Your payment has been received and recorded in our system.</li>
                        <li>Remaining balance: <strong>₱' . number_format($paymentDetails['remaining_balance'], 2) . '</strong></li>
                        <li>Please ensure full payment before your scheduled date.</li>
                        <li>Keep this email and your invoice number for future reference.</li>
                        <li>Contact us if you have any questions about your payment.</li>
                    </ul>
                </div>';
    } else {
        $html .= '
                <div class="important-section">
                    <h4>Important Reminders</h4>
                    <ul>
                        <li>Your payment has been received and recorded in our system.</li>
                        <li>Your balance is now <strong>FULLY PAID</strong>. Thank you!</li>
                        <li>Keep this email and your invoice number for your records.</li>
                        <li>Contact us if you need a detailed receipt or have any questions.</li>
                    </ul>
                </div>';
    }

    $html .= '
                <div class="contact-section">
                    <h4>Need Help?</h4>
                    <p>For questions or concerns about your payment:</p>
                    <p class="phone">📞 8-2457647</p>
                    <p>📧 admin@nsshai.com</p>
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
                <p style="margin-top: 15px; font-size: 12px;">© 2025 NSSHAI. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}
    // ============================================
    // MAIN PAYMENT PROCESSING
    // ============================================

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
        
        try {
            // Create output buffer to catch any stray output
            ob_start();
            
            $admin_id = $_SESSION['admin_id'] ?? "visitor_payment";
            
            // Get and validate form data
            $category = trim($_POST['category'] ?? '');
            $user_type = trim($_POST['user_type'] ?? '');
            $user_id = trim($_POST['user_id'] ?? '');
            $invoice_number = trim($_POST['invoice_number'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_method = ($_POST['payment_method'] === 'Bank Transfer') ? 'bank' : 'cash';
            $reference_number = trim($_POST['reference_number'] ?? '');
            
            $debug_info = [
                'step' => 'Initial data received',
                'category' => $category,
                'user_type' => $user_type,
                'user_id' => $user_id,
                'invoice_number' => $invoice_number,
                'amount' => $amount
            ];
            
            // Validate required fields
            if (empty($category)) throw new Exception('Category is required');
            if (empty($user_type)) throw new Exception('User type is required');
            if (empty($user_id)) throw new Exception('User ID is required');
            if (empty($invoice_number)) throw new Exception('Invoice number is required');
            if ($amount <= 0) throw new Exception('Amount must be greater than 0');
            
            $debug_info['step'] = 'Validation passed';
            
            // Handle file upload
            $proof_filename = null;
            if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_NO_FILE) {
                $proof_filename = handleFileUpload($_FILES['proof_of_payment']);
            }
            
            $debug_info['step'] = 'File upload handled';
            $debug_info['proof_filename'] = $proof_filename ?? 'none';
            
            // Get user details
            $db_user_type = ($user_type === 'Homeowner/Resident') ? 'homeowner' : 'visitor';
            $debug_info['db_user_type'] = $db_user_type;
            
            $userDetails = getUserDetails($conn, $db_user_type, $user_id);
            
            $debug_info['step'] = 'User details retrieved';
            $debug_info['userDetails'] = $userDetails;
            
            // THE CRITICAL FIX: Check the actual column name returned
            $household_id = null;
            $visitor_id = null;
            
            if ($db_user_type === 'homeowner') {
                $household_id = $userDetails['household_id'];
                $debug_info['household_id'] = $household_id;
            } else {
                // THIS IS THE KEY: visitor_details table returns 'visitor_id'
                $visitor_id = $userDetails['visitor_id'] ?? null;
                $debug_info['visitor_id'] = $visitor_id;
                
                if (empty($visitor_id)) {
                    throw new Exception("Critical: visitor_id is empty after query. Retrieved data: " . json_encode($userDetails));
                }
                
                // Verify it exists
                $check_stmt = $conn->prepare("SELECT visitor_id FROM visitor_details WHERE visitor_id = ?");
                $check_stmt->bind_param("s", $visitor_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $check_stmt->close();
                    throw new Exception("Visitor ID '$visitor_id' not found in visitor_details table");
                }
                $check_stmt->close();
                
                $debug_info['visitor_verified'] = true;
            }
            
            $debug_info['step'] = 'IDs prepared';
            
            // Start transaction
            if (!$conn->begin_transaction()) {
                throw new Exception("Failed to start transaction: " . $conn->error);
            }
            
            $reference_id = null;
            $paymentDetails = null;
            
            // Process Amenity Fee payment
            if ($category === 'Amenity Fee') {
                $booking_id_field = ($user_type === 'Homeowner/Resident') ? 'homeowner_id' : 'visitor_id';
                
                $debug_info['booking_id_field'] = $booking_id_field;
                
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
                    throw new Exception("Amenity booking not found for invoice: $invoice_number");
                }
                
                $debug_info['booking_found'] = true;
                $debug_info['booking_id'] = $booking['id'];
                
                $reference_id = $booking['id'];
                $new_amount_paid = floatval($booking['amount_paid']) + $amount;
                $balance = floatval($booking['total_amount']) - $new_amount_paid;
                
                if ($balance <= 0) {
                    $new_status = 'paid';
                } elseif ($new_amount_paid > 0) {
                    $new_status = 'partial';
                } else {
                    $new_status = 'pending';
                }
                
                $stmt = $conn->prepare("UPDATE amenity_bookings SET amount_paid = ?, status = ? WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed (update amenity_bookings): " . $conn->error);
                }
                
                $stmt->bind_param("dsi", $new_amount_paid, $new_status, $reference_id);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed (update amenity_bookings): " . $stmt->error);
                }
                $stmt->close();
                
                $debug_info['booking_updated'] = true;
                
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
            
            // Insert payment record
    $db_category = ($category === 'Amenity Fee') ? 'amenity' : 'monthly_dues';

    $debug_info['step'] = 'About to insert payment';
    $debug_info['insert_data'] = [
        'category' => $db_category,
        'reference_id' => $reference_id,
        'user_type' => $db_user_type,
        'household_id' => $household_id,
        'visitor_id' => $visitor_id
    ];

    if ($db_user_type === 'visitor') {
        // For visitors: 9 parameters (household_id is NULL in query)
        // Types: s i s s s d s s s
        $stmt = $conn->prepare("
            INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // FIXED: Changed "sissdsss" to "sisssdsss" (9 chars for 9 vars)
        $stmt->bind_param("sisssdsss", $db_category, $reference_id, $invoice_number, $db_user_type, $visitor_id, $amount, $payment_method, $reference_number, $proof_filename);
        
    } else {
        // For homeowners: 9 parameters (visitor_id is NULL in query)
        // Types: s i s s s d s s s
        $stmt = $conn->prepare("
            INSERT INTO payments (category, reference_id, invoice_number, user_type, household_id, visitor_id, amount, payment_method, reference_number, proof_of_payment) 
            VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // FIXED: Changed "sisssdsss" to "sisssdsss" (9 chars for 9 vars)
        $stmt->bind_param("sisssdsss", $db_category, $reference_id, $invoice_number, $db_user_type, $household_id, $amount, $payment_method, $reference_number, $proof_filename);
    }

    if (!$stmt->execute()) {
        $debug_info['insert_error'] = $stmt->error;
        throw new Exception("Execute failed (insert payments): " . $stmt->error . " | Debug: " . json_encode($debug_info));
    }

    $payment_id = $conn->insert_id;
    $stmt->close();

    $conn->commit();

    // Clear output buffer
    ob_end_clean();

    // Send email (non-critical, don't fail if this errors)
    $emailSent = false;
    $recipientEmail = trim($userDetails['email_address'] ?? '');

    $debug_info['email_attempt'] = [
        'recipient_email' => $recipientEmail,
        'has_payment_details' => !empty($paymentDetails)
    ];

    if (!empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) && $paymentDetails) {
        $recipientName = trim($userDetails['first_name'] . ' ' . $userDetails['last_name']);
        
        try {
            $emailSent = sendPaymentReceipt($recipientEmail, $recipientName, $paymentDetails);
            $debug_info['email_sent'] = $emailSent;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            $debug_info['email_error'] = $e->getMessage();
        }
    } else {
        $debug_info['email_skipped'] = 'Missing email or payment details';
    }

    // Return success
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_id' => $payment_id,
        'email_sent' => $emailSent,
        'debug' => $debug_info
    ]);
    exit;
            
        } catch (Exception $e) {
            if (isset($conn) && $conn->ping()) {
                $conn->rollback();
            }
            
            // Clear output buffer if it exists
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'debug' => $debug_info ?? []
            ]);
            exit;
        }
    }

    // Redirect if not POST request
    header("Location: ../visitor_payment.php");
    exit;