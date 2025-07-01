<?php
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$uid = $conn->real_escape_string($data['uid']);
$first_name = $conn->real_escape_string($data['firstName']);
$last_name = $conn->real_escape_string($data['lastName']);
$contact = $conn->real_escape_string($data['contact']);
$address = $conn->real_escape_string($data['address']);
$type = $conn->real_escape_string($data['type']);

$sql = "INSERT INTO users (uid, first_name, last_name, contact, address, type) 
        VALUES ('$uid', '$first_name', '$last_name', '$contact', '$address', '$type')";

if ($conn->query($sql) === TRUE) {
    echo json_encode([
        'status' => 'success',
        'user' => [
            'uid' => $uid,
            'firstName' => $first_name,
            'lastName' => $last_name,
            'contact' => $contact,
            'address' => $address,
            'type' => $type
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

$conn->close();
?>