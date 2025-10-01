<?php
header('Content-Type: application/json');

if (!isset($_POST['gate'])) {
    echo json_encode(["status" => "error", "message" => "No gate specified", "gate" => "ERROR"]);
    exit;
}

$gate = $_POST['gate'];

// Validate gate number
if (!in_array($gate, ['1', '2'])) {
    echo json_encode(["status" => "error", "message" => "Invalid gate number", "gate" => "ERROR"]);
    exit;
}

// Replace with YOUR ngrok URL
$local_server = "https://evon-unscalable-berserkly.ngrok-free.dev"; // UPDATE THIS!

// Send request to local Node.js server
$ch = curl_init($local_server . "/gate-status");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'gate' => $gate
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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