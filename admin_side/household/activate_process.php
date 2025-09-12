<?php
require '../../rfid-api/db.php';

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate input
$household_id = isset($_POST['activate_id']) ? trim($_POST['activate_id']) : '';
if ($household_id === '') {
    echo json_encode(['success' => false, 'message' => 'No ID provided.']);
    exit;
}

try {
    // Check if admin exists
    $stmt = $conn->prepare("SELECT * FROM household_accounts WHERE household_id = ?");
    $stmt->bind_param("s", $household_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID provided.']);
        exit;
    }

    // Update account status to 'active'
    $update = $conn->prepare("UPDATE household_accounts SET status = 'active' WHERE household_id = ?");
    $update->bind_param("s", $household_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Account activated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No matching account found or already active.']);
    }

    $update->close();
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
