<?php
require 'db.php';

// Fetch latest entry from entry_logs
$sql = "SELECT * FROM entry_logs ORDER BY date_created DESC LIMIT 1";
$result = $conn->query($sql);

$response = null;

if ($result->num_rows > 0) {
    $entry = $result->fetch_assoc();
    $uid = $entry['uid'];

    // 1. Check in household_accounts
    $stmt = $conn->prepare("SELECT household_id AS id, first_name, middle_name, last_name FROM household_accounts WHERE rfid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $res1 = $stmt->get_result();

    if ($res1->num_rows > 0) {
        $user = $res1->fetch_assoc();
        $response = [
            "uid" => $uid,
            "name" => $user['first_name'] . " " . $user['middle_name'] . " " . $user['last_name'],
            "extra" => "Resident",
            "type" => "Resident",
            "date_created" => $entry['date_created']
        ];
    } else {
        // 2. Check in visitor_details
        $stmt = $conn->prepare("SELECT visitor_id AS id, first_name, middle_name, last_name FROM visitor_details WHERE rfid = ?");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $res2 = $stmt->get_result();

        if ($res2->num_rows > 0) {
            $visitor = $res2->fetch_assoc();
            $response = [
                "uid" => $uid,
                "name" => $visitor['first_name'] . " " . $visitor['middle_name'] . " " . $visitor['last_name'],
                "extra" => "Visitor",
                "type" => "Visitor",
                "date_created" => $entry['date_created']
            ];
        } else {
            // Not found in either table
            $response = [
                "uid" => $uid,
                "name" => null,
                "extra" => "Unknown Card",
                "type" => "unknown",
                "date_created" => $entry['date_created']
            ];
        }
    }
}

echo json_encode($response);
