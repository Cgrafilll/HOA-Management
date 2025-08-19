<?php
require '../../rfid-api/db.php';

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate input
$admin_id = isset($_POST['activate_id']) ? trim($_POST['activate_id']) : '';
if ($admin_id === '') {
    echo json_encode(['success' => false, 'message' => 'No ID provided.']);
    exit;
}

try {
    // Check if admin exists
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID provided.']);
        exit;
    }

    // Update account status to 'active'
    $update = $conn->prepare("UPDATE admin_accounts SET status = 'active' WHERE admin_id = ?");
    $update->bind_param("s", $admin_id);
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
