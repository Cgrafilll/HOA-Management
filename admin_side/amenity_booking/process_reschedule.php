<?php
session_start();
require '../../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Get parameters
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Validate inputs
if ($booking_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header("Location: ../amenity_booking.php?error=" . urlencode("Invalid request."));
    exit;
}

try {
    if ($action === 'approve') {
        // Get the requested date and rate
        $stmt = $conn->prepare("SELECT requested_date, requested_rate FROM amenity_bookings WHERE id = ? AND reschedule_status = 'pending'");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header("Location: ../amenity_booking.php?error=" . urlencode("Reschedule request not found."));
            exit;
        }
        
        $booking = $result->fetch_assoc();
        
        // Update the booking with new date and rate, and set reschedule_status to 'approved'
        $update_stmt = $conn->prepare("UPDATE amenity_bookings 
            SET reservation_date = ?, 
                rate = ?, 
                reschedule_status = 'approved' 
            WHERE id = ?");
        $update_stmt->bind_param("ssi", $booking['requested_date'], $booking['requested_rate'], $booking_id);
        
        if ($update_stmt->execute()) {
            header("Location: ../amenity_booking.php?success=" . urlencode("Reschedule request approved successfully."));
        } else {
            header("Location: ../amenity_booking.php?error=" . urlencode("Failed to approve reschedule request."));
        }
        
    } else if ($action === 'reject') {
        // Update reschedule_status to 'rejected'
        $stmt = $conn->prepare("UPDATE amenity_bookings 
            SET reschedule_status = 'rejected' 
            WHERE id = ? AND reschedule_status = 'pending'");
        $stmt->bind_param("i", $booking_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: ../amenity_booking.php?success=" . urlencode("Reschedule request rejected successfully."));
        } else {
            header("Location: ../amenity_booking.php?error=" . urlencode("Failed to reject reschedule request or request not found."));
        }
    }
    
} catch (Exception $e) {
    header("Location: ../amenity_booking.php?error=" . urlencode("An error occurred: " . $e->getMessage()));
}

exit;
?>