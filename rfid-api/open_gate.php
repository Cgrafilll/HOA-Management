<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "No action provided", "gate" => "ERROR"]);
    exit;
}

$action = strtoupper($_POST['action']);
$gate = isset($_POST['gate']) ? $_POST['gate'] : '1';

// Your ngrok URL - UPDATE THIS!
$local_server = "https://abc123def456.ngrok.io"; // Replace with YOUR ngrok URL

$ch = curl_init($local_server . "/gate-control");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => $action,
    'gate' => $gate
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        "status" => "error",
        "message" => "Cannot connect to local server",
        "gate" => "ERROR"
    ]);
} else {
    echo $response;
}

curl_close($ch);
?>