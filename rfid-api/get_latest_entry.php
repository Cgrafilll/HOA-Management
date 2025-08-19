<?php
require 'db.php';
header('Content-Type: application/json');

// Insert a new scan (when called with POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uid'])) {
    $uid = trim($_POST['uid']);

    // Default values
    $name = "Unknown";
    $type = "Invalid";

    // Check household_accounts
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM household_accounts WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $name = trim($row['first_name'] . " " . $row['middle_name'] . " " . $row['last_name']);
        $type = "Resident";
    } else {
        // Check visitor_details
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM visitor_details WHERE rfid_uid = ?");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $name = trim($row['first_name'] . " " . $row['middle_name'] . " " . $row['last_name']);
            $type = "Visitor";
        }
    }

    // Insert into entry_logs
    $stmt = $conn->prepare("INSERT INTO entry_logs (entry_id, uid, type, name, date_created) VALUES (UUID(), ?, ?, ?, NOW())");
    $stmt->bind_param("sss", $uid, $type, $name);
    $stmt->execute();

    echo json_encode(["status" => "ok", "uid" => $uid, "type" => $type, "name" => $name, "date_created" => date("Y-m-d H:i:s")]);
    exit;
}

// Get latest entry (for polling)
$result = $conn->query("SELECT * FROM entry_logs ORDER BY date_created DESC LIMIT 1");
if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}
?>