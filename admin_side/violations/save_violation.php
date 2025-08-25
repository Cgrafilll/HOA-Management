<?php
session_start();
require '../../rfid-api/db.php';

if (!isset($_SESSION['email_address'])) {
    echo "unauthorized";
    exit;
}

try {
    // Check if form submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // Collect form values
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'];
        $last_name = $_POST['last_name'];
        $cellphone_number = $_POST['cellphone_number'];
        $date_incident = $_POST['date_incident'];
        $time_incident = $_POST['time_incident'];
        $location = $_POST['location'];
        $violation_type = $_POST['violation_type'];
        $description_of_incident = $_POST['description_of_incident'];
        $homeowner_involved = $_POST['homeowner_involved'] ?? null;
        $address_lot_number = $_POST['address_lot_number'] ?? null;
        $other_parties = $_POST['other_parties'] ?? null;
        $action_taken = $_POST['action_taken'];
        $remarks = $_POST['remarks'] ?? null;

        // Handle evidence upload
        $evidence_file = null;
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] == 0) {
            $targetDir = "evidences/";
            
            // Create folder if not exists
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Generate unique file name
            $fileName = time() . "_" . basename($_FILES['evidence']['name']);
            $targetFile = $targetDir . $fileName;

            // Move file to folder
            if (move_uploaded_file($_FILES['evidence']['tmp_name'], $targetFile)) {
                $evidence_file = $fileName; // Save only file name in DB
            }
        }

        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO violations 
            (first_name, middle_name, last_name, cellphone_number, date_incident, time_incident, location, violation_type, description_of_incident, homeowner_involved, address_lot_number, other_parties, evidence, action_taken, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("sssssssssssssss",
            $first_name, $middle_name, $last_name, $cellphone_number,
            $date_incident, $time_incident, $location, $violation_type,
            $description_of_incident, $homeowner_involved, $address_lot_number,
            $other_parties, $evidence_file, $action_taken, $remarks
        );

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
