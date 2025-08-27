<?php
session_start();
require '../../rfid-api/db.php';

// Show errors during dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_invoice.php");
    exit;
}

$billing_date_value = '';
if (isset($_POST['billing_month']) && !empty($_POST['billing_month'])) {
    // Append day 28 to the selected month (YYYY-MM → YYYY-MM-28)
    $billing_date_value = $_POST['billing_month'] . '-28';
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
    $billing_month      = $_POST['billing_month'];
    $balance_remaining  = $_POST['balance_remaining'];
    $payment_date       = $_POST['payment_date'];

    // Handle proof_of_payment (optional)
    $proof_of_payment = null;
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
        $proof_of_payment = file_get_contents($_FILES['proof_of_payment']['tmp_name']);
    }

    // Insert
    $stmt = $conn->prepare("INSERT INTO monthly_dues 
        (invoice_number, household_id, billing_month, balance_remaining, proof_of_payment, payment_date) 
        VALUES (?, ?, ?, ?, ?, ?)");

    // Use "b" for blob
    $stmt->bind_param(
        "sssdss",
        $invoice_number,     // string
        $household_id,       // int
        $billing_month,      // string (YYYY-MM)
        $balance_remaining,  // double
        $proof_of_payment,   // string/blob
        $payment_date        // string
    );

    if ($proof_of_payment !== null) {
        $stmt->send_long_data(4, $proof_of_payment); // bind blob
    }

    if ($stmt->execute()) {
        $_SESSION['modal'] = 'success';
        header("Location: add_invoice.php"); // redirect back to form so modal shows
        exit;
    } else {
        $_SESSION['modal'] = 'error';
        $_SESSION['error_message'] = "Database insert failed.";
        header("Location: add_invoice.php");
        exit;
    }

} catch (Exception $e) {
    $_SESSION['modal'] = 'error';
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: add_invoice.php");
    exit;
}
