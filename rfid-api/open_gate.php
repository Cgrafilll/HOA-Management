<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "No action provided"]);
    exit;
}

$action = strtoupper($_POST['action']);
$gate = isset($_POST['gate']) ? $_POST['gate'] : '1';

$valid_actions = ["OPEN", "CLOSE"];

if (!in_array($action, $valid_actions)) {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit;
}

if (!in_array($gate, ['1', '2'])) {
    echo json_encode(["status" => "error", "message" => "Invalid gate number"]);
    exit;
}

$port = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "COM6" : "/dev/ttyUSB0";
$arduino_command = $action . $gate;

try {
    // Configure serial port BEFORE opening
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("mode $port BAUD=9600 PARITY=N DATA=8 STOP=1");
    } else {
        exec("stty -F $port 9600 cs8 -cstopb -parenb");
    }

    $fp = fopen($port, "r+");
    if (!$fp) {
        throw new Exception("Unable to open port $port");
    }

    // Set stream timeout (2 seconds)
    stream_set_timeout($fp, 2);

    // Flush any old data
    stream_set_blocking($fp, false);
    while (fgets($fp) !== false) {
    }
    stream_set_blocking($fp, true);

    // Send command
    fwrite($fp, $arduino_command . "\n");
    fflush($fp);

    // Wait a bit for Arduino to process
    usleep(200000); // 200ms

    // Read multiple lines to get the full response
    $response = "";
    $lines_read = 0;
    while ($lines_read < 5 && !feof($fp)) {
        $line = fgets($fp);
        if ($line !== false && trim($line) != "") {
            $response .= trim($line) . " ";
            $lines_read++;
        }
    }

    fclose($fp);

    echo json_encode([
        "status" => "success",
        "gate" => $action,
        "gate_number" => $gate,
        "arduino_command" => $arduino_command,
        "arduino_response" => trim($response)
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "gate" => "ERROR"
    ]);
}
?>