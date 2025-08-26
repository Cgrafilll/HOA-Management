<?php
session_start();
require '../../rfid-api/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_invoice.php");
    exit;
}

// Helper: Generate invoice number (INV-YYYYMMDD-XXX)
function generateInvoiceNumber($conn) {
    $date = date('Ymd'); // YYYYMMDD
    $prefix = "INV-$date";

    // Count how many invoices already created today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_dues WHERE invoice_number LIKE CONCAT(?, '%')");
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = $result['count'] + 1;

    // Format as 3-digit sequence
    $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
    return "$prefix-$sequence";
}

try {
    // Generate new invoice number
    $invoice_number = generateInvoiceNumber($conn);

    // Collect form inputs
    $household_id       = $_POST['household_id'];
    $billing_month      = $_POST['billing_month'];
    $amount_paid        = $_POST['amount_paid'];
    $balance_remaining  = $_POST['balance_remaining'];
    $reference_number   = $_POST['reference_number'];
    $payment_date       = $_POST['payment_date'];

    // Handle proof_of_payment (optional)
    $proof_of_payment = null;
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $proof_of_payment = file_get_contents($_FILES['proof_of_payment']['tmp_name']);
    }

    // Insert into monthly_dues
    $stmt = $conn->prepare("INSERT INTO monthly_dues 
        (invoice_number, household_id, billing_month, amount_paid, balance_remaining, reference_number, proof_of_payment, payment_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sissdsss",
        $invoice_number,
        $household_id,
        $billing_month,
        $amount_paid,
        $balance_remaining,
        $reference_number,
        $proof_of_payment,
        $payment_date
    );

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Invoice <strong>$invoice_number</strong> created successfully!";
        header("Location: ../invoice.php");
        exit;
    } else {
        throw new Exception("Database insert failed: " . $stmt->error);
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: add_invoice.php");
    exit;
}
