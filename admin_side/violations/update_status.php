<?php
session_start();
require '../../rfid-api/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['violation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing violation ID']);
    exit;
}

$violation_id = $input['violation_id'];

// Check if this is an archive request
if (isset($input['action']) && $input['action'] === 'archive') {
    // Handle archive request
    try {
        $sql = "UPDATE violations SET status = 'Inactive' WHERE violation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $violation_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Violation archived successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Violation not found or already archived']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else if (isset($input['action']) && $input['action'] === 'activate') {
    // Handle activate request
    try {
        $sql = "UPDATE violations SET status = 'Active' WHERE violation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $violation_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Violation activated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Violation not found or already active']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // Handle status update request
    if (!isset($input['action_taken'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $action_taken = $input['action_taken'];

    // Validate action_taken value
    $valid_statuses = ['Pending', 'Under Review', 'Resolved', 'Dismissed'];
    if (!in_array($action_taken, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    try {
        $sql = "UPDATE violations SET action_taken = ? WHERE violation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $action_taken, $violation_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>