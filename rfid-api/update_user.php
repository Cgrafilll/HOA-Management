<?php
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$uid = $conn->real_escape_string($data['uid']);
$first_name = $conn->real_escape_string($data['firstName']);
$last_name = $conn->real_escape_string($data['lastName']);
$contact = $conn->real_escape_string($data['contact']);
$address = $conn->real_escape_string($data['address']);

$sql = "UPDATE users SET 
            first_name = '$first_name', 
            last_name = '$last_name', 
            contact = '$contact', 
            address = '$address' 
        WHERE uid = '$uid'";

if ($conn->query($sql) === TRUE) {
    // Get user type so frontend can update rfidUsers correctly
    $typeRes = $conn->query("SELECT type FROM users WHERE uid = '$uid'");
    $type = $typeRes && $typeRes->num_rows > 0 ? $typeRes->fetch_assoc()['type'] : '';

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