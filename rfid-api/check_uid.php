<?php
header('Content-Type: application/json');
require 'db.php'; // DB connection

if (!isset($_POST['uid'])) {
    echo json_encode(["status" => "error", "message" => "No UID provided"]);
    exit;
}

$uid = strtoupper(trim($_POST['uid'])); // normalize UID

try {
    // ✅ Check household_accounts
    $stmt1 = $conn->prepare("SELECT first_name, middle_name, last_name, rfid 
                             FROM household_accounts WHERE rfid = ?");
    $stmt1->bind_param("s", $uid);
    $stmt1->execute();
    $result1 = $stmt1->get_result();

    if ($result1 && $result1->num_rows > 0) {
        $row = $result1->fetch_assoc();
        $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
        echo json_encode([
            "status" => "success",
            "type" => "household",
            "rfid" => $row['rfid'],
            "full_name" => $full_name
        ]);
        exit;
    }

    // ✅ Check visitor_details
    $stmt2 = $conn->prepare("SELECT first_name, middle_name, last_name, rfid 
                             FROM visitor_details WHERE rfid = ?");
    $stmt2->bind_param("s", $uid);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2 && $result2->num_rows > 0) {
        $row = $result2->fetch_assoc();
        $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
        echo json_encode([
            "status" => "success",
            "type" => "visitor",
            "rfid" => $row['rfid'],
            "full_name" => $full_name
        ]);
        exit;
    }

    // ✅ If not found in both tables
    echo json_encode([
        "status" => "error",
        "message" => "UID not registered"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
