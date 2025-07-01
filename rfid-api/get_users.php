<?php
header('Content-Type: application/json');
require 'db.php';

$sql = "SELECT * FROM users";
$result = $conn->query($sql);

$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = [
        'uid' => $row['uid'],
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'contact' => $row['contact'],
        'address' => $row['address'],
        'type' => $row['type']
    ];
}

echo json_encode($users);
$conn->close();
?>