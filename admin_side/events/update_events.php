<?php
session_start();
require '../../rfid-api/db.php'; // adjust path if needed

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if POST data exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['title'], $_POST['event_date'])) {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $body = trim($_POST['body'] ?? '');
    $event_date = $_POST['event_date'];
    $status = 'published'; // default status

    try {
        $stmt = $conn->prepare("
            UPDATE events 
            SET title = ?, body = ?, event_date = ?, status = ?, created_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $title, $body, $event_date, $status, $id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Event updated successfully';
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

echo json_encode($response);
exit;
