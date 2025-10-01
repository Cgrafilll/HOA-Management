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

$port = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "COM6" : "/dev/ttyUSB0";
$arduino_command = $action . $gate;

// Log for debugging
error_log("Attempting to send command: $arduino_command to port: $port");

try {
    // Configure serial port BEFORE opening
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("mode $port BAUD=9600 PARITY=N DATA=8 STOP=1 2>&1", $output, $return_code);
        error_log("Mode command output: " . print_r($output, true));
        if ($return_code !== 0) {
            throw new Exception("Failed to configure port. Is COM6 correct? Check Device Manager.");
        }
    } else {
        exec("stty -F $port 9600 cs8 -cstopb -parenb 2>&1", $output, $return_code);
        if ($return_code !== 0) {
            throw new Exception("Failed to configure port");
        }
    }

    // Try to open the port
    $fp = @fopen($port, "r+");
    if (!$fp) {
        $error = error_get_last();
        throw new Exception("Cannot open $port. Error: " . ($error['message'] ?? 'Unknown'));
    }

    // Set stream timeout
    stream_set_timeout($fp, 2);

    // Flush old data
    stream_set_blocking($fp, false);
    $flushed = 0;
    while (($line = fgets($fp)) !== false) {
        $flushed++;
    }
    stream_set_blocking($fp, true);
    error_log("Flushed $flushed old lines from buffer");

    // Send command
    $bytes_written = fwrite($fp, $arduino_command . "\n");
    fflush($fp);
    error_log("Sent command, wrote $bytes_written bytes");

    // Wait for Arduino to process
    usleep(300000); // 300ms - increased for reliability

    // Read response
    $response = "";
    $attempts = 0;
    $max_attempts = 10;

    while ($attempts < $max_attempts) {
        $line = fgets($fp);
        if ($line !== false && trim($line) !== "") {
            $response .= trim($line) . " | ";
            error_log("Arduino response line: " . trim($line));
        }
        $attempts++;
        if (strlen($response) > 10)
            break; // Got enough response
        usleep(50000); // 50ms between reads
    }

    fclose($fp);

    if (empty($response)) {
        throw new Exception("Arduino not responding. Check connections and Serial Monitor is closed.");
    }

    echo json_encode([
        "status" => "success",
        "gate" => $action,
        "gate_number" => $gate,
        "arduino_command" => $arduino_command,
        "arduino_response" => trim($response)
    ]);

} catch (Exception $e) {
    error_log("Gate control error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "gate" => "ERROR",
        "debug_info" => [
            "port" => $port,
            "command" => $arduino_command,
            "php_user" => get_current_user()
        ]
    ]);
}
?>