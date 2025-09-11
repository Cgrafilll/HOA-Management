<?php
session_start();
require '../../rfid-api/db.php';

// Show errors during dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_invoice.php");
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

    // Debug: Let's see what we're getting
    error_log("Debug - billing_month_input: " . var_export($billing_month_input, true));
    error_log("Debug - billing_month (converted): " . var_export($billing_month, true));
    error_log("Debug - household_id: " . var_export($household_id, true));
    error_log("Debug - balance_remaining: " . var_export($balance_remaining, true));
    error_log("Debug - due_date: " . var_export($due_date, true));

    // Validate required fields
    if (empty($household_id) || empty($billing_month_input) || empty($balance_remaining) || empty($due_date)) {
        $_SESSION['modal'] = 'error';
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: add_invoice.php");
        exit;
    }

    // Insert with amount_paid defaulting to 0.00
    $amount_paid = 0.00;
    
    $stmt = $conn->prepare("INSERT INTO monthly_dues 
        (invoice_number, household_id, billing_month, amount_paid, balance_remaining, due_date) 
        VALUES (?, ?, ?, ?, ?, ?)");

    // Bind parameters - all 6 fields
    $stmt->bind_param(
        "sssdds",
        $invoice_number,     // string
        $household_id,       // string
        $billing_month,      // string (DATE format YYYY-MM-DD)
        $amount_paid,        // decimal (defaulting to 0.00)
        $balance_remaining,  // decimal
        $due_date           // string (DATE format YYYY-MM-DD)
    );

    if ($stmt->execute()) {
        $_SESSION['modal'] = 'success';
        header("Location: add_invoice.php");
        exit;
    } else {
        $_SESSION['modal'] = 'error';
        $_SESSION['error_message'] = "Database insert failed: " . $stmt->error;
        header("Location: add_invoice.php");
        exit;
    }

} catch (Exception $e) {
    $_SESSION['modal'] = 'error';
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: add_invoice.php");
    exit;
}
?>