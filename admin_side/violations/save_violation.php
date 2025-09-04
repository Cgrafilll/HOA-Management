<?php
// ✅ Set session configuration BEFORE session_start()
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

require '../../rfid-api/db.php'; // Adjust path as needed

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// Check session timeout (2 hours = 7200 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login/login.php?error=" . urlencode("Your session has expired. Please log in again."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

$admin_id = $_SESSION['admin_id'];

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // Get household_id from POST data (hidden input field)
        if (!isset($_POST['household_id']) || empty($_POST['household_id'])) {
            echo "error: household_id not provided";
            exit;
        }

        $household_id = $_POST['household_id'];

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

        // Handle evidence upload
        $evidence_file = null;
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] == 0) {
            $targetDir = "../admin_side/violations/evidences/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $fileName = time() . "_" . basename($_FILES['evidence']['name']);
            $targetFile = $targetDir . $fileName;
            if (move_uploaded_file($_FILES['evidence']['tmp_name'], $targetFile)) {
                $evidence_file = $fileName;
            } else {
                echo "error: Failed to upload evidence file";
                exit;
            }
        }

        // Validate required fields
        if (
            empty($household_id) || empty($date_incident) || empty($time_incident) ||
            empty($location) || empty($violation_type) || empty($description_of_incident)
        ) {
            echo "error: Please fill in all required fields";
            exit;
        }

        // Debug: Log the household_id being inserted (remove in production)
        error_log("Inserting violation for household_id: " . $household_id);

        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO violations 
            (household_id, date_incident, time_incident, location, violation_type, description_of_incident, homeowner_involved, address_lot_number, other_parties, evidence, anonymous) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            echo "error: Failed to prepare statement - " . $conn->error;
            exit;
        }

        $stmt->bind_param(
            "sssssssssss",
            $household_id,
            $date_incident,
            $time_incident,
            $location,
            $violation_type,
            $description_of_incident,
            $homeowner_involved,
            $address_lot_number,
            $other_parties,
            $evidence_file,
            $anonymous
        );

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    } else {
        echo "error: Invalid request method";
    }
} catch (Exception $e) {
    echo "error: Exception caught - " . $e->getMessage();
}
?>