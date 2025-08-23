<?php
session_start();
require '../../rfid-api/db.php';

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
            $numericPart = (int)substr($lastCode, strlen($prefix));
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

    // Get admin_id from session
    $admin_id = $_SESSION['admin_id'] ?? "system";

    // Debug: Print POST data to see what's being received
    error_log("POST data received: " . print_r($_POST, true));
    error_log("FILES data received: " . print_r($_FILES, true));
    error_log("Amenity from GET: " . ($amenity ?? 'null'));

    // Convert and assign all form fields to variables (required for bind_param reference)
    $userType = $_POST['userType'] ?? '';
    $firstName = $_POST['firstName'] ?? '';
    $middleName = $_POST['middleName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
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

    // Validate required fields - FIXED field names to match HTML form
    $requiredFields = ['userType', 'firstName', 'lastName', 'emailAddress', 'reservationDate', 'rate', 'payment'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        die("❌ Missing required fields: " . implode(', ', $missingFields) . "<br>Received POST data: " . print_r(array_keys($_POST), true));
    }

    // Handle file upload (⚠️ NOTE: match form field name!)
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

    // Prepare statement
    $stmt = $conn->prepare("
        INSERT INTO amenity_bookings 
        (reservation_code, admin_id, amenity, user_type, first_name, middle_name, last_name, email_address, reservation_date, guests, rate, payment_method, exclusive_booking, chairs, tables, reference_number, total_amount, amount_paid, proof_of_payment, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters (20 placeholders → 20 types → 20 vars)
    // Type mapping based on database schema - ALL VARIABLES NOW PROPERLY ASSIGNED
    $stmt->bind_param(
        "sssssssssisssisddsss",    // Corrected type string for all 20 parameters
        $reservation_code,             // 1  - varchar(20)
        $admin_id,                     // 2  - varchar(50)
        $amenity,                      // 3  - varchar(100) - amenity
        $userType,                     // 4  - enum - user_type
        $firstName,                    // 5  - varchar(100)
        $middleName,                   // 6  - varchar(100)
        $lastName,                     // 7  - varchar(100)
        $emailAddress,                 // 8  - varchar(255)
        $reservationDate,              // 9  - date
        $guests,                       // 10 - int(11)
        $rate,                         // 11 - varchar(50) - rate
        $payment,                      // 12 - enum - payment_method
        $exclusiveBooking,             // 13 - enum - exclusive_booking
        $chairs,                       // 14 - int(11)
        $tables,                       // 15 - int(11)
        $referenceNumber,              // 16 - varchar(255)
        $total,                        // 17 - decimal(10,2)
        $amountPaid,                   // 18 - decimal(10,2)
        $proof_of_payment,             // 19 - varchar(255)
        $status                        // 20 - enum - status
    );

    // // Debug: Print the values being bound
    // error_log("Binding values:");
    // error_log("1. reservation_code: " . $reservation_code);
    // error_log("2. admin_id: " . $admin_id);
    // error_log("3. amenity: " . $amenity);
    // error_log("4. userType: " . $userType);
    // error_log("5. firstName: " . $firstName);
    // error_log("6. middleName: " . $middleName);
    // error_log("7. lastName: " . $lastName);
    // error_log("8. emailAddress: " . $emailAddress);
    // error_log("9. reservationDate: " . $reservationDate);
    // error_log("10. guests: " . $guests);
    // error_log("11. rate: " . $rate);
    // error_log("12. payment: " . $payment);
    // error_log("13. exclusiveBooking: " . $exclusiveBooking);
    // error_log("14. chairs: " . $chairs);
    // error_log("15. tables: " . $tables);
    // error_log("16. referenceNumber: " . $referenceNumber);
    // error_log("17. total: " . $total);
    // error_log("18. amountPaid: " . $amountPaid);
    // error_log("19. proof_of_payment: " . ($proof_of_payment ?? 'null'));
    // error_log("20. status: " . $status);

    if ($stmt->execute()) {
        // Success - redirect with success parameter and reservation code
        header("Location: reserve_booking.php?reserve=" . urlencode($amenity) . "&success=1&code=" . urlencode($reservation_code));
        exit();
    } else {
        // Error - redirect with error parameter
        header("Location: reserve_booking.php?reserve=" . urlencode($amenity) . "&error=1&message=" . urlencode($stmt->error));
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>