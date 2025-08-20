<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "No action provided"]);
    exit;
}

$action = strtoupper($_POST['action']);
$valid_actions = ["OPEN", "CLOSE"];

if (!in_array($action, $valid_actions)) {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit;
}

// 🔹 Adjust this to your Arduino COM port
$port = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "COM6" : "/dev/ttyUSB0";

try {
    $fp = fopen($port, "r+"); // Open for read/write
    if (!$fp) {
        throw new Exception("Unable to open port $port");
    }

    fwrite($fp, $action . "\n");

    // Read Arduino response (optional)
    $response = fgets($fp);
    fclose($fp);

    echo json_encode([
        "status" => "success",
        "gate" => $action,
        "arduino_response" => trim($response)
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}