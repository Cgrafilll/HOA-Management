<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    echo "unauthorized";
    exit;
}

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