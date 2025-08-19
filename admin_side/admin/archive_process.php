
<?php
require '../../rfid-api/db.php';

// Parse raw input if $_POST is empty (for AJAX/fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    parse_str(file_get_contents('php://input'), $_POST);
}

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}


$admin_id = $_POST['admin_id'];

try {
    // Prepare query (admin_id is VARCHAR, so "s" not "i")
    $stmt = $conn->prepare("SELECT * FROM admin_accounts WHERE admin_id = ?");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID provided.']);
        exit;
    }

    // Update account status
    $update = $conn->prepare("UPDATE admin_accounts SET status = 'Inactive' WHERE admin_id = ?");
    $update->bind_param("s", $admin_id);
    if ($update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account activated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to activate account.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
