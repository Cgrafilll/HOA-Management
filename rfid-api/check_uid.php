<?php
header('Content-Type: application/json');
require 'db.php'; // make sure this connects to your MySQL

if (!isset($_POST['uid'])) {
    echo json_encode(["status" => "error", "message" => "No UID provided"]);
    exit;
}

$uid = trim($_POST['uid']);

try {
    // Prepare and check in household_accounts
    $stmt1 = $conn->prepare("SELECT * FROM household_accounts WHERE rfid = ?");
    $stmt1->bind_param("s", $uid);
    $stmt1->execute();
    $result1 = $stmt1->get_result();

    if ($result1->num_rows > 0) {
        $row = $result1->fetch_assoc();
        echo json_encode([
            "status" => "success",
            "type" => "household",
            "data" => $row
        ]);
        exit;
    }

    // Prepare and check in visitor_details
    $stmt2 = $conn->prepare("SELECT * FROM visitor_details WHERE rfid = ?");
    $stmt2->bind_param("s", $uid);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2->num_rows > 0) {
        $row = $result2->fetch_assoc();
        echo json_encode([
            "status" => "success",
            "type" => "visitor",
            "data" => $row
        ]);
        exit;
    }

    // If not found in both tables
    echo json_encode([
        "status" => "not_found",
        "message" => "UID not registered"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>