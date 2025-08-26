<?php
session_start();
require '../../rfid-api/db.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files (adjust path based on your installation)
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

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

// Generate auto-incrementing invoice number in format: YYYYMMDD-000n
function generateInvoiceNumber($conn)
{
    $today = date('Ymd'); // YYYYMMDD format
    $pattern = $today . '-%';

    try {
        $stmt = $conn->prepare("SELECT invoice_number FROM amenity_bookings WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Extract the last 4 digits and increment
            $lastInvoice = $row['invoice_number'];
            $lastNumber = (int) substr($lastInvoice, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            // First invoice for this date
            $nextNumber = 1;
        }

        // Format: YYYYMMDD-0001, YYYYMMDD-0002, etc.
        $invoiceNumber = $today . '-' . sprintf('%04d', $nextNumber);
        $stmt->close();

        return $invoiceNumber;

    } catch (Exception $e) {
        // Fallback if there's an error
        return $today . '-' . sprintf('%04d', rand(1, 9999));
    }
}

// Function to get homeowner_id or visitor_id based on user type and email
function getUserId($conn, $userType, $emailAddress)
{
    if ($userType === 'homeowner') {
        $stmt = $conn->prepare("SELECT household_id FROM household_accounts WHERE email_address = ?");
        $stmt->bind_param("s", $emailAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['household_id'];
        }
        $stmt->close();
    } elseif ($userType === 'visitor') {
        $stmt = $conn->prepare("SELECT visitor_id FROM visitor_details WHERE email_address = ?");
        $stmt->bind_param("s", $emailAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['visitor_id'];
        }
        $stmt->close();
    }
    return null;
}

// Robust email sending function using PHPMailer
function sendBookingReceipt($recipientEmail, $recipientName, $bookingDetails)
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
        $mail->Subject = 'Amenity Booking Confirmation - NSSHAI [' . $bookingDetails['reservation_code'] . ']';

        // Create beautiful HTML email content
        $mail->Body = generateEmailTemplate($recipientName, $bookingDetails);

        // Alternative plain text version
        $mail->AltBody = generatePlainTextEmail($recipientName, $bookingDetails);

        // Send the email
        $result = $mail->send();

        // Log success
        error_log("✅ PHPMailer: Email sent successfully to " . $recipientEmail);
        return true;

    } catch (Exception $e) {
        // Log the error
        error_log("❌ PHPMailer Error: {$mail->ErrorInfo}");
        error_log("❌ Exception: {$e->getMessage()}");
        return false;
    }
}

// Generate HTML email template (keeping your existing function)
function generateEmailTemplate($recipientName, $bookingDetails)
{
    $reservationCode = htmlspecialchars($bookingDetails['reservation_code']);
    $amenity = htmlspecialchars($bookingDetails['amenity']);
    $reservationDate = date('F j, Y', strtotime($bookingDetails['reservation_date']));

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Confirmation</title>
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
            
            .reservation-banner { background: linear-gradient(135deg, #e3f2fd 0%, #f0f9ff 100%); border: 2px solid #2196f3; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; position: relative; overflow: hidden; }
            .reservation-banner::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(33,150,243,0.1) 0%, transparent 70%); }
            .reservation-banner .icon { font-size: 48px; margin-bottom: 15px; position: relative; z-index: 2; }
            .reservation-banner h3 { font-size: 18px; color: #1976d2; margin-bottom: 10px; position: relative; z-index: 2; }
            .reservation-banner .code { font-size: 32px; font-weight: bold; color: #0d47a1; letter-spacing: 3px; font-family: "Courier New", monospace; position: relative; z-index: 2; }
            
            .booking-details { background-color: #f8f9fa; border-radius: 12px; padding: 25px; margin: 30px 0; border-left: 5px solid #198754; }
            .booking-details h3 { color: #198754; font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; }
            .booking-details h3::before { content: "📋"; margin-right: 10px; }
            
            .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: 600; color: #495057; font-size: 14px; flex: 0 0 auto; margin-right: 20px; }
            .detail-value { color: #212529; font-size: 14px; text-align: right; flex: 0 0 auto; }
            .detail-value.highlight { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: 600; }

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
            
            .status-badge { display: inline-block; background-color: #ffc107; color: #212529; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
            
            @media only screen and (max-width: 600px) {
                .email-container { width: 100% !important; }
                .header, .content, .footer { padding: 20px !important; }
                .reservation-banner .code { font-size: 24px; }
                .detail-row { flex-direction: column; align-items: flex-start; }
                .detail-value { text-align: left; margin-top: 5px; }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <h1>Booking Confirmed!</h1>
                <p>Neopolitan Sitio Seville Homeowners Association</p>
            </div>
            
            <!-- Content -->
            <div class="content">
                <div class="greeting">Hello ' . htmlspecialchars($recipientName) . '!</div>
                
                <div class="intro-text">
                    Thank you for your amenity reservation! Your booking has been successfully submitted and is currently <span class="status-badge">Pending Approval</span>.
                </div>
                
                <!-- Reservation Code Banner -->
                <div class="reservation-banner">
                    <div class="icon">🎫</div>
                    <h3>Your Reservation Code</h3>
                    <div class="code">' . $reservationCode . '</div>
                </div>
                
                <!-- Booking Details -->
                <div class="booking-details">
                    <h3>Booking Summary</h3>';

    // Add all booking details
    $html .= '
                    <div class="detail-row">
                        <span class="detail-label">🏢 Amenity</span>
                        <span class="detail-value highlight">' . $amenity . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📅 Date</span>
                        <span class="detail-value">' . $reservationDate . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">⏰ Time Slot</span>
                        <span class="detail-value">' . ucfirst($bookingDetails['rate']) . ' Session</span>
                    </div>';

    // Add guests if applicable
    if ($bookingDetails['guests'] > 0) {
        $html .= '
                    <div class="detail-row">
                        <span class="detail-label">👥 Guests</span>
                        <span class="detail-value">' . $bookingDetails['guests'] . ' person(s)</span>
                    </div>';
    }

    // Add exclusive booking
    $html .= '
                    <div class="detail-row">
                        <span class="detail-label">⭐ Exclusive Booking</span>
                        <span class="detail-value">' . ucfirst($bookingDetails['exclusive_booking']) . '</span>
                    </div>';

    // Add add-ons if any
    if ($bookingDetails['chairs'] > 0 || $bookingDetails['tables'] > 0) {
        $addOns = [];
        if ($bookingDetails['chairs'] > 0) {
            $addOns[] = $bookingDetails['chairs'] . ' Chair(s) - ₱' . number_format($bookingDetails['chairs'] * 12, 2);
        }
        if ($bookingDetails['tables'] > 0) {
            $addOns[] = $bookingDetails['tables'] . ' Table(s) - ₱' . number_format($bookingDetails['tables'] * 20, 2);
        }
        $html .= '
                    <div class="detail-row">
                        <span class="detail-label">🪑 Add-ons</span>
                        <span class="detail-value">' . implode('<br>', $addOns) . '</span>
                    </div>';
    }

    // Payment information
    $html .= '
                    <div class="detail-row">
                        <span class="detail-label">💳 Payment Method</span>
                        <span class="detail-value">' . ucfirst($bookingDetails['payment_method']) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">💰 Total Amount</span>
                        <span class="detail-value highlight">₱' . number_format($bookingDetails['total_amount'], 2) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">💵 Amount Paid</span>
                        <span class="detail-value">₱' . number_format($bookingDetails['amount_paid'], 2) . '</span>
                    </div>';

    // Add reference number if provided
    if (!empty($bookingDetails['reference_number'])) {
        $html .= '
                    <div class="detail-row">
                        <span class="detail-label">🔢 Reference Number</span>
                        <span class="detail-value">' . htmlspecialchars($bookingDetails['reference_number']) . '</span>
                    </div>';
    }

    // Add invoice number
    if (!empty($bookingDetails['invoice_number'])) {
        $html .= '
                    <div class="detail-row">
                        <span class="detail-label">📋 Invoice Number</span>
                        <span class="detail-value">' . htmlspecialchars($bookingDetails['invoice_number']) . '</span>
                    </div>';
    }

    $html .= '
                </div>
                
                <!-- Important Information -->
                <div class="important-section">
                    <h4>Important Reminders</h4>
                    <ul>
                        <li>Your booking status is currently <strong>PENDING</strong> and requires HOA approval.</li>
                        <li>Please save your reservation code <strong>' . $reservationCode . '</strong> for future reference.</li>
                        <li>You will receive another email once your booking is approved or if additional information is needed.</li>
                        <li>Minimum 50% down payment is required. Payment must be received before your scheduled date.</li>
                        <li>Rescheduling is allowed but must be requested at least 24 hours in advance.</li>
                    </ul>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-section">
                    <h4>Need Help?</h4>
                    <p>For questions or concerns about your booking:</p>
                    <p class="phone">📞 8-2457647</p>
                    <p>📧 admin@nsshai.com</p>
                </div>
                
                <div style="text-align: center; margin-top: 30px; color: #666;">
                    <p>Thank you for choosing NSSHAI amenities!</p>
                    <p style="margin-top: 15px;"><strong>Best regards,<br>NSSHAI Administration Team</strong></p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <h4>Neopolitan Sitio Seville Homeowners Association, Inc.</h4>
                <p>This is an automated confirmation email. Please do not reply directly to this message.</p>
                <p>For support and inquiries, please contact our office at 8-2457647</p>
                <p style="margin-top: 15px; font-size: 12px;">© 2025 NSSHAI. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

// Generate plain text version for email clients that don't support HTML
function generatePlainTextEmail($recipientName, $bookingDetails)
{
    $text = "AMENITY BOOKING CONFIRMATION - NSSHAI\n";
    $text .= "=====================================\n\n";
    $text .= "Hello " . $recipientName . "!\n\n";
    $text .= "Thank you for your amenity reservation. Your booking has been successfully submitted and is currently pending approval.\n\n";
    $text .= "RESERVATION CODE: " . $bookingDetails['reservation_code'] . "\n";

    if (!empty($bookingDetails['invoice_number'])) {
        $text .= "INVOICE NUMBER: " . $bookingDetails['invoice_number'] . "\n";
    }

    $text .= "\nBOOKING DETAILS:\n";
    $text .= "- Amenity: " . $bookingDetails['amenity'] . "\n";
    $text .= "- Date: " . date('F j, Y', strtotime($bookingDetails['reservation_date'])) . "\n";
    $text .= "- Time Slot: " . ucfirst($bookingDetails['rate']) . "\n";

    if ($bookingDetails['guests'] > 0) {
        $text .= "- Guests: " . $bookingDetails['guests'] . "\n";
    }

    $text .= "- Exclusive Booking: " . ucfirst($bookingDetails['exclusive_booking']) . "\n";
    $text .= "- Payment Method: " . ucfirst($bookingDetails['payment_method']) . "\n";
    $text .= "- Total Amount: ₱" . number_format($bookingDetails['total_amount'], 2) . "\n";
    $text .= "- Amount Paid: ₱" . number_format($bookingDetails['amount_paid'], 2) . "\n";

    if (!empty($bookingDetails['reference_number'])) {
        $text .= "- Reference Number: " . $bookingDetails['reference_number'] . "\n";
    }

    $text .= "\nIMPORTANT REMINDERS:\n";
    $text .= "- Your booking is currently PENDING approval\n";
    $text .= "- Keep your reservation code safe\n";
    $text .= "- You will receive updates via email\n";
    $text .= "- Contact us at 8-2457647 for questions\n\n";
    $text .= "Best regards,\nNSSHAI Administration Team";

    return $text;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Generate amenity-specific reservation code with auto-increment
    $amenity = $_GET['reserve'] ?? '';

    // Define amenity prefixes
    $amenityPrefixes = [
        'Gazebo' => 'GZB',
        'Swimming Pool' => 'SWP',
        'Basketball Court' => 'BBC',
        'Clubhouse' => 'CLB'
    ];

    // Get the prefix for the current amenity
    $prefix = $amenityPrefixes[$amenity] ?? 'RSV'; // Default fallback

    // Get the next sequential number for this amenity
    try {
        $stmt = $conn->prepare("SELECT reservation_code FROM amenity_bookings WHERE amenity = ? AND reservation_code LIKE ? ORDER BY reservation_code DESC LIMIT 1");
        $likePattern = $prefix . '%';
        $stmt->bind_param("ss", $amenity, $likePattern);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Extract the numeric part and increment
            $lastCode = $row['reservation_code'];
            $numericPart = (int) substr($lastCode, strlen($prefix));
            $nextNumber = $numericPart + 1;
        } else {
            // First booking for this amenity
            $nextNumber = 1;
        }

        // Format with leading zeros (5 digits)
        $reservation_code = $prefix . sprintf('%05d', $nextNumber);

        $stmt->close();
    } catch (Exception $e) {
        // Fallback to random if there's an error
        $reservation_code = $prefix . rand(10000, 99999);
    }

    // Generate invoice number
    $invoice_number = generateInvoiceNumber($conn);

    // Get admin_id from session
    $admin_id = $_SESSION['admin_id'] ?? "system";

    // Convert and assign all form fields to variables
    $userType = $_POST['userType'] ?? '';
    $emailAddress = $_POST['emailAddress'] ?? '';
    $reservationDate = $_POST['reservationDate'] ?? '';
    $rate = $_POST['rate'] ?? '';
    $payment = $_POST['payment'] ?? '';
    $exclusiveBooking = $_POST['exclusiveBooking'] ?? '';
    $referenceNumber = $_POST['referenceNumber'] ?? '';

    // Convert numeric fields safely
    $guests = isset($_POST['guests']) ? (int) $_POST['guests'] : 0;
    $chairs = isset($_POST['chairs']) ? (int) $_POST['chairs'] : 0;
    $tables = isset($_POST['tables']) ? (int) $_POST['tables'] : 0;

    // Handle total amount - remove commas and convert to float
    $total = 0.0;
    if (isset($_POST['total']) && !empty($_POST['total'])) {
        $totalStr = str_replace(',', '', $_POST['total']); // Remove commas
        $total = (float) $totalStr;
    }

    $amountPaid = isset($_POST['amountPaid']) ? (float) $_POST['amountPaid'] : 0.0;

    // Always default to pending
    $status = "pending";

    // Get the appropriate user ID based on user type
    $homeowner_id = null;
    $visitor_id = null;

    if ($userType === 'homeowner') {
        $homeowner_id = getUserId($conn, 'homeowner', $emailAddress);
        if (!$homeowner_id) {
            die("❌ Error: Homeowner not found with email: " . $emailAddress);
        }
    } elseif ($userType === 'visitor') {
        $visitor_id = getUserId($conn, 'visitor', $emailAddress);
        if (!$visitor_id) {
            die("❌ Error: Visitor not found with email: " . $emailAddress);
        }
    }

    // Validate required fields
    $requiredFields = ['userType', 'emailAddress', 'reservationDate', 'rate', 'payment'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        die("❌ Missing required fields: " . implode(', ', $missingFields) . "<br>Received POST data: " . print_r(array_keys($_POST), true));
    }

    // Handle file upload
    $proof_of_payment = null;
    if (isset($_FILES['proofOfPayment']) && $_FILES['proofOfPayment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = uniqid() . "_" . basename($_FILES['proofOfPayment']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['proofOfPayment']['tmp_name'], $target_file)) {
            $proof_of_payment = $target_file;
        }
    }

    // Prepare database statement - updated to match your actual table structure
    $stmt = $conn->prepare("
        INSERT INTO amenity_bookings 
        (reservation_code, admin_id, homeowner_id, visitor_id, amenity, user_type, reservation_date, guests, rate, payment_method, exclusive_booking, chairs, tables, reference_number, total_amount, amount_paid, proof_of_payment, invoice_number, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters - updated parameter types
    $stmt->bind_param(
        "ssssssssissiissdsss",
        $reservation_code,   // s
        $admin_id,          // s                
        $homeowner_id,             // s
        $visitor_id,               // s
        $amenity,                  // s
        $userType,                 // s
        $reservationDate,          // s 
        $guests,                   // i
        $rate,                     // s
        $payment,                  // s
        $exclusiveBooking,         // s 
        $chairs,                   // i
        $tables,                   // i
        $referenceNumber,          // s     
        $total,                    // d
        $amountPaid,               // d 
        $proof_of_payment,         // s 
        $invoice_number,           // s 
        $status                    // s
    );

    if ($stmt->execute()) {
        // Get recipient name from the appropriate table
        $recipientName = '';
        if ($userType === 'homeowner' && $homeowner_id) {
            $nameStmt = $conn->prepare("SELECT first_name, last_name FROM household_accounts WHERE household_id = ?");
            $nameStmt->bind_param("s", $homeowner_id);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();
            if ($nameRow = $nameResult->fetch_assoc()) {
                $recipientName = trim($nameRow['first_name'] . ' ' . $nameRow['last_name']);
            }
            $nameStmt->close();
        } elseif ($userType === 'visitor' && $visitor_id) {
            $nameStmt = $conn->prepare("SELECT first_name, last_name FROM visitor_details WHERE visitor_id = ?");
            $nameStmt->bind_param("s", $visitor_id);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();
            if ($nameRow = $nameResult->fetch_assoc()) {
                $recipientName = trim($nameRow['first_name'] . ' ' . $nameRow['last_name']);
            }
            $nameStmt->close();
        }

        // Prepare booking details for email
        $bookingDetails = [
            'reservation_code' => $reservation_code,
            'invoice_number' => $invoice_number,
            'amenity' => $amenity,
            'reservation_date' => $reservationDate,
            'rate' => $rate,
            'guests' => $guests,
            'exclusive_booking' => $exclusiveBooking,
            'chairs' => $chairs,
            'tables' => $tables,
            'payment_method' => $payment,
            'total_amount' => $total,
            'amount_paid' => $amountPaid,
            'reference_number' => $referenceNumber
        ];

        // Send the email receipt with PHPMailer
        $emailSent = sendBookingReceipt($emailAddress, $recipientName, $bookingDetails);

        // Log email status
        if ($emailSent) {
            error_log("✅ PHPMailer: Email receipt sent successfully to: " . $emailAddress . " [Code: " . $reservation_code . ", Invoice: " . $invoice_number . "]");
        } else {
            error_log("❌ PHPMailer: Failed to send email receipt to: " . $emailAddress . " [Code: " . $reservation_code . ", Invoice: " . $invoice_number . "]");
        }

        // Success - redirect regardless of email status
        header("Location: reserve_booking.php?reserve=" . urlencode($amenity) . "&success=1&code=" . urlencode($reservation_code) . "&invoice=" . urlencode($invoice_number));
        $stmt->close();
        $conn->close();
        exit();
    } else {
        // Database error - redirect with error
        header("Location: reserve_booking.php?reserve=" . urlencode($amenity) . "&error=1&message=" . urlencode($stmt->error));
        $stmt->close();
        $conn->close();
        exit();
    }
}
?>