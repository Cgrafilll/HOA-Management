<?php
session_start();
header('Content-Type: application/json');
require '../../rfid-api/db.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("DEBUG: activate_announcements.php loaded");

if (!isset($_SESSION['email_address'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE announcements SET status = 'published', created_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Announcement activated"]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }
    exit;
}

// Fallback for invalid request
echo json_encode(["success" => false, "message" => "Invalid request"]);
