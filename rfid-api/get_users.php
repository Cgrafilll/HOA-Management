<?php
header('Content-Type: application/json');
require 'db.php';

$sql = "SELECT * FROM entry_logs";
$result = $conn->query($sql);

$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = [
        'uid' => $row['uid'],
        'date_created' => $row['date_created']
    ];
}

echo json_encode($users);
$conn->close();
?>