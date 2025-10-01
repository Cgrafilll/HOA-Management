<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "No action provided", "gate" => "ERROR"]);
    exit;
}

$action = strtoupper($_POST['action']);
$gate = isset($_POST['gate']) ? $_POST['gate'] : '1';

$valid_actions = ["OPEN", "CLOSE"];

if (!in_array($action, $valid_actions)) {
    echo json_encode(["status" => "error", "message" => "Invalid action", "gate" => "ERROR"]);
    exit;
}

if (!in_array($gate, ['1', '2'])) {
    echo json_encode(["status" => "error", "message" => "Invalid gate number", "gate" => "ERROR"]);
    exit;
}

// Replace with YOUR ngrok URL
$local_server = "https://evon-unscalable-berserkly.ngrok-free.dev"; // UPDATE THIS!

$ch = curl_init($local_server . "/gate-control");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => $action,
    'gate' => $gate
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        "status" => "error",
        "message" => "Cannot connect to local server: " . $error,
        "gate" => "ERROR"
    ]);
} else {
    echo $response;
}
?>