<?php
// ✅ FIX: Set session configuration BEFORE session_start()
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.cookie_lifetime', 7200); // 2 hours

// Set session cookie parameters before starting session
session_set_cookie_params([
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Use secure cookies on HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);

// NOW start the session
session_start();

require '../rfid-api/db.php';

// Check if user is logged in
if (!isset($_SESSION['household_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$household_id = $_SESSION['household_id'];
$sql = "SELECT * FROM household_accounts WHERE household_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $household_id);
$stmt->execute();
$result = $stmt->get_result();
$resident = $result->fetch_assoc();

if (!$resident) {
    echo "Resident not found.";
    exit;
}

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // Collect form values
        $date_incident = $_POST['date_incident'];
        $time_incident = $_POST['time_incident'];
        $location = $_POST['location'];
        $violation_type = $_POST['violation_type'];
        $description_of_incident = $_POST['description_of_incident'];
        $homeowner_involved = $_POST['homeowner_involved'] ?? null;
        $address_lot_number = $_POST['address_lot_number'] ?? null;
        $other_parties = $_POST['other_parties'] ?? null;

        // Convert time to 12-hour format (if provided)
        if (!empty($time_incident)) {
            $time_incident = date("g:i A", strtotime($time_incident));
        }

        // Anonymous checkbox (default "No")
        $anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] == "1" ? "Yes" : "No";

        // Handle evidence upload - store directly in database
        $evidence_data = null;
        $has_evidence = false;

        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['evidence']['type'];

            if (!in_array($file_type, $allowed_types)) {
                echo "error: Invalid file type. Please upload JPEG, PNG, or GIF images only.";
                exit;
            }

            // Validate file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB
            if ($_FILES['evidence']['size'] > $max_size) {
                echo "error: File size too large. Maximum allowed size is 10MB.";
                exit;
            }

            // Read the file content
            $evidence_data = file_get_contents($_FILES['evidence']['tmp_name']);

            if ($evidence_data === false) {
                echo "error: Failed to read evidence file";
                exit;
            }

            $has_evidence = true;
            error_log("Evidence file uploaded successfully. Size: " . strlen($evidence_data) . " bytes");
        } else {
            // Log the upload error for debugging
            if (isset($_FILES['evidence'])) {
                $upload_error = $_FILES['evidence']['error'];
                error_log("File upload error code: " . $upload_error);

                switch ($upload_error) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        echo "error: File size too large";
                        exit;
                    case UPLOAD_ERR_PARTIAL:
                        echo "error: File upload was incomplete";
                        exit;
                    case UPLOAD_ERR_NO_FILE:
                        echo "error: No evidence file was uploaded";
                        exit;
                    default:
                        echo "error: File upload failed with error code: " . $upload_error;
                        exit;
                }
            } else {
                echo "error: No evidence file was provided";
                exit;
            }
        }

        // Debug: Log the household_id being inserted (remove in production)
        error_log("Inserting violation for household_id: " . $household_id);

        // Prepare the INSERT statement
        $stmt = $conn->prepare("INSERT INTO violations 
            (household_id, date_incident, time_incident, location, violation_type, description_of_incident, homeowner_involved, address_lot_number, other_parties, evidence, anonymous, action_taken) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");

        if (!$stmt) {
            echo "error: Failed to prepare statement - " . $conn->error;
            exit;
        }

        // Bind parameters - 'b' for BLOB data
        $stmt->bind_param(
            "sssssssssbs",
            $household_id,
            $date_incident,
            $time_incident,
            $location,
            $violation_type,
            $description_of_incident,
            $homeowner_involved,
            $address_lot_number,
            $other_parties,
            $evidence_data,
            $anonymous
        );

        // Send BLOB data if we have evidence
        if ($has_evidence && $evidence_data !== null) {
            // Parameter index 9 (0-based) is the evidence BLOB field
            $stmt->send_long_data(9, $evidence_data);
            error_log("BLOB data sent successfully. Size: " . strlen($evidence_data));
        }

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage();
}
?>