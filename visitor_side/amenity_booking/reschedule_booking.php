<?php
// ✅ Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);

session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

require '../../rfid-api/db.php';

// Check if admin is logged in
if (!isset($_SESSION['visitor_id'])) {
    header("Location: ../login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: amenity_booking.php?error=" . urlencode("Invalid request method."));
    exit;
}

// Get form data
$booking_id = $_POST['booking_id'] ?? '';
$amenity = $_POST['amenity'] ?? '';
$new_date = $_POST['new_date'] ?? '';
$new_rate = $_POST['new_rate'] ?? '';
$reason = $_POST['reason'] ?? '';
$visitor_id = $_SESSION['visitor_id'];

// Validate required fields
if (empty($booking_id) || empty($amenity) || empty($new_date) || empty($new_rate) || empty($reason)) {
    header("Location: amenity_booking.php?error=" . urlencode("All fields are required."));
    exit;
}

// Validate date is not in the past
$today = date('Y-m-d');
if ($new_date < $today) {
    header("Location: amenity_booking.php?error=" . urlencode("Cannot reschedule to a past date."));
    exit;
}

// Validate rate
if (!in_array($new_rate, ['day', 'night'])) {
    header("Location: amenity_booking.php?error=" . urlencode("Invalid rate selected."));
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if the booking exists
    $check_stmt = $conn->prepare("SELECT * FROM amenity_bookings WHERE id = ?");
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $booking_result = $check_stmt->get_result();

    if ($booking_result->num_rows === 0) {
        throw new Exception("Booking not found.");
    }

    $booking = $booking_result->fetch_assoc();

    // Check if there's already a pending reschedule request
    if ($booking['reschedule_status'] === 'pending') {
        throw new Exception("There is already a pending reschedule request for this booking.");
    }

    // Check if the new date/rate combination is available
    $availability_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM amenity_bookings 
        WHERE amenity = ? 
        AND reservation_date = ? 
        AND rate = ? 
        AND id != ?
        AND status IN ('pending', 'partial', 'paid')
    ");
    $availability_stmt->bind_param("sssi", $amenity, $new_date, $new_rate, $booking_id);
    $availability_stmt->execute();
    $availability_result = $availability_stmt->get_result();
    $availability = $availability_result->fetch_assoc();

    if ($availability['count'] > 0) {
        throw new Exception("The selected date and time slot is no longer available. Please choose another date or time slot.");
    }

    // Store the reschedule request (not update the actual booking yet)
    $update_stmt = $conn->prepare("
        UPDATE amenity_bookings 
        SET requested_date = ?, 
            requested_rate = ?,
            reschedule_reason = ?,
            reschedule_status = 'pending',
            reschedule_requested_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $update_stmt->bind_param("sssi", $new_date, $new_rate, $reason, $booking_id);

    if (!$update_stmt->execute()) {
        throw new Exception("Failed to submit reschedule request.");
    }

    // Commit transaction
    $conn->commit();

    // Redirect with success message
    header("Location: amenity_booking.php?success=" . urlencode("Reschedule request submitted successfully and is pending approval."));
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    header("Location: amenity_booking.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>