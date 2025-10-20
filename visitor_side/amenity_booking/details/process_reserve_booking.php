<?php
// process_reserve_booking.php
// Start session if needed
session_start();
require '../..rfid-api/db.php';

// For testing: display errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect posted data safely
    $amenity = $_GET['reserve'] ?? 'Unknown Amenity';
    $userType = $_POST['userType'] ?? '';
    $firstName = $_POST['firstName'] ?? '';
    $middleName = $_POST['middleName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['emailAddress'] ?? '';
    $reservationDate = $_POST['reservationDate'] ?? '';
    $guests = $_POST['guests'] ?? 0;
    $rate = $_POST['rate'] ?? '';
    $payment = $_POST['payment'] ?? '';
    $exclusive = $_POST['exclusiveBooking'] ?? 'no';
    $chairs = $_POST['chairs'] ?? 0;
    $tables = $_POST['tables'] ?? 0;
    $referenceNumber = $_POST['referenceNumber'] ?? '';
    $total = $_POST['total'] ?? 0;
    $amountPaid = $_POST['amountPaid'] ?? 0;

    // Handle file upload if exists
    $fileName = '';
    if (isset($_FILES['proofOfPayment']) && $_FILES['proofOfPayment']['error'] === 0) {
        $fileName = $_FILES['proofOfPayment']['name'];
        $fileTmp = $_FILES['proofOfPayment']['tmp_name'];
        // For testing, we won't move the file yet
    }

    // Generate a reservation code (simple example)
    $reservationCode = strtoupper(substr($amenity, 0, 3)) . '-' . rand(1000, 9999);

    // Output reservation code to browser console for testing
    echo "<script>console.log('Reservation Code: $reservationCode');</script>";

    // Optional: For debugging, print all POST data in console
    $jsData = json_encode([
        'amenity' => $amenity,
        'userType' => $userType,
        'firstName' => $firstName,
        'middleName' => $middleName,
        'lastName' => $lastName,
        'email' => $email,
        'reservationDate' => $reservationDate,
        'guests' => $guests,
        'rate' => $rate,
        'payment' => $payment,
        'exclusive' => $exclusive,
        'chairs' => $chairs,
        'tables' => $tables,
        'referenceNumber' => $referenceNumber,
        'total' => $total,
        'amountPaid' => $amountPaid,
        'fileName' => $fileName
    ]);
    echo "<script>console.log('Reservation Data:', $jsData);</script>";

    // For now, redirect back to the form page (optional)
    // header("Location: add_booking.php?amenity=" . urlencode($amenity));
    // exit;
} else {
    echo "Invalid access method.";
}
?>
 