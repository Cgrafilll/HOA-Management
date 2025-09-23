<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "No action provided"]);
    exit;
}

$action = strtoupper($_POST['action']);
$gate = isset($_POST['gate']) ? $_POST['gate'] : '1'; // Default to gate 1 for backward compatibility

$valid_actions = ["OPEN", "CLOSE"];

if (!in_array($action, $valid_actions)) {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit;
}

// Validate gate number
if (!in_array($gate, ['1', '2'])) {
    echo json_encode(["status" => "error", "message" => "Invalid gate number"]);
    exit;
}

// 🔹 Adjust this to your Arduino COM port
$port = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "COM6" : "/dev/ttyUSB0";

// Build Arduino command
$arduino_command = $action . $gate; // OPEN1, OPEN2, CLOSE1, CLOSE2

try {
    $fp = fopen($port, "r+"); // Open for read/write
    if (!$fp) {
        throw new Exception("Unable to open port $port");
    }

    fwrite($fp, $arduino_command . "\n");

    // Read Arduino response (optional)
    $response = fgets($fp);
    fclose($fp);

    echo json_encode([
        "status" => "success",
        "gate" => $action,
        "gate_number" => $gate,
        "arduino_command" => $arduino_command,
        "arduino_response" => trim($response)
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>