<?php
session_start();
require '../../rfid-api/db.php'; // adjust path

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if POST data exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['title'])) {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $body = trim($_POST['body'] ?? '');
    $status = 'published'; // default status

    // Update the announcement
    try {
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, body = ?, status = ?, created_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssi", $title, $body, $status, $id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Announcement updated successfully';
        } else {
            $response['message'] = 'Database execution failed';
        }
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request';
}

// Make sure nothing else is output
echo json_encode($response);
exit;
