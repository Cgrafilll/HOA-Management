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
    $stmt1 = $conn->prepare("SELECT first_name, middle_name, last_name, rfid, profile_picture 
                             FROM household_accounts WHERE rfid = ?");
    $stmt1->bind_param("s", $uid);
    $stmt1->execute();
    $result1 = $stmt1->get_result();

    if ($result1 && $result1->num_rows > 0) {
        $row = $result1->fetch_assoc();
        $fname = $row['first_name'];
        $mname = $row['middle_name'];
        $lname = $row['last_name'];
        $full_name = trim($fname . ' ' . $mname . ' ' . $lname);
        // Convert LONGBLOB to base64 data URL
        $profile_picture = '';
        if (!empty($row['profile_picture'])) {
            $profile_picture = 'data:image/jpeg;base64,' . base64_encode($row['profile_picture']);
        }

        // ✅ Generate new entry_id for logging
        $result = $conn->query("SELECT entry_id FROM entry_logs WHERE entry_id LIKE 'ENT-%' ORDER BY entry_id DESC LIMIT 1");
        if ($result && $entryRow = $result->fetch_assoc()) {
            $last_id = intval(substr($entryRow['entry_id'], 4)); // extract numeric part after 'ENT-'
            $new_id_number = $last_id + 1;
        } else {
            $new_id_number = 1; // first entry
        }
        $entry_id = 'ENT-' . str_pad($new_id_number, 4, '0', STR_PAD_LEFT);

        // ✅ Log entry to entry_logs table
        $logStmt = $conn->prepare("INSERT INTO entry_logs (uid, entry_id, first_name, middle_name, last_name, type, date_created) VALUES (?, ?, ?, ?, ?, 'household', NOW())");
        $logStmt->bind_param("sssss", $row['rfid'], $entry_id, $fname, $mname, $lname);
        $logStmt->execute();

        echo json_encode([
            "status" => "success",
            "type" => "household",
            "rfid" => $row['rfid'],
            "first_name" => $row['first_name'],
            "middle_name" => $row['middle_name'],
            "last_name" => $row['last_name'],
            "full_name" => $full_name,
            "profile_picture" => $profile_picture
        ]);
        exit;
    }

    // ✅ Check visitor_details
    $stmt2 = $conn->prepare("SELECT first_name, middle_name, last_name, rfid, profile_picture 
                             FROM visitor_details WHERE rfid = ?");
    $stmt2->bind_param("s", $uid);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2 && $result2->num_rows > 0) {
        $row = $result2->fetch_assoc();
        $fname = $row['first_name'];
        $mname = $row['middle_name'];
        $lname = $row['last_name'];
        $full_name = trim($fname . ' ' . $mname . ' ' . $lname);
        // Convert LONGBLOB to base64 data URL
        $profile_picture = '';
        if (!empty($row['profile_picture'])) {
            $profile_picture = 'data:image/jpeg;base64,' . base64_encode($row['profile_picture']);
        }

        // ✅ Generate new entry_id for logging
        $result = $conn->query("SELECT entry_id FROM entry_logs WHERE entry_id LIKE 'ENT-%' ORDER BY entry_id DESC LIMIT 1");
        if ($result && $entryRow = $result->fetch_assoc()) {
            $last_id = intval(substr($entryRow['entry_id'], 4)); // extract numeric part after 'ENT-'
            $new_id_number = $last_id + 1;
        } else {
            $new_id_number = 1; // first entry
        }
        $entry_id = 'ENT-' . str_pad($new_id_number, 4, '0', STR_PAD_LEFT);

        // ✅ Log entry to entry_logs table
        $logStmt = $conn->prepare("INSERT INTO entry_logs (uid, entry_id, first_name, middle_name, last_name, type, date_created) VALUES (?, ?, ?, ?, ?, 'visitor', NOW())");
        $logStmt->bind_param("sssss", $row['rfid'], $entry_id, $fname, $mname, $lname);
        $logStmt->execute();

        echo json_encode([
            "status" => "success",
            "type" => "visitor",
            "rfid" => $row['rfid'],
            "first_name" => $row['first_name'],
            "middle_name" => $row['middle_name'],
            "last_name" => $row['last_name'],
            "full_name" => $full_name,
            "profile_picture" => $profile_picture
        ]);
        exit;
    }

    // ✅ If not found in both tables - do not log unauthorized attempts
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
?>