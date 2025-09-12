<?php
session_start();
require '../../rfid-api/db.php';

// Show errors during dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Set content type for AJAX response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Generate invoice number
function generateInvoiceNumber($conn) {
    $date = date('Ymd'); 
    $prefix = "INV-$date";

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_dues WHERE invoice_number LIKE CONCAT(?, '%')");
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = $result['count'] + 1;

    $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
    return "$prefix-$sequence";
}

try {
    $invoice_number     = generateInvoiceNumber($conn);
    $household_id       = $_POST['household_id'];
    $billing_month_input = $_POST['billing_month']; // This is YYYY-MM format
    $balance_remaining  = $_POST['balance_remaining'];
    $due_date           = $_POST['due_date'];

    // Convert billing_month from YYYY-MM to YYYY-MM-01 for DATE field
    $billing_month = $billing_month_input . '-01';

    // Validate required fields
    if (empty($household_id) || empty($billing_month_input) || empty($balance_remaining) || empty($due_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required.']);
        exit;
    }

    // Insert with amount_paid defaulting to 0.00 and status as 'Pending'
    $amount_paid = 0.00;
    $status = 'Pending';
    
    $stmt = $conn->prepare("INSERT INTO monthly_dues 
        (invoice_number, household_id, billing_month, amount_paid, balance_remaining, due_date, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

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
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Invoice created successfully',
            'invoice_number' => $invoice_number
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database insert failed: ' . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>