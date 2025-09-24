<?php
header('Content-Type: application/json');

$gate = isset($_POST['gate']) ? $_POST['gate'] : '2'; // Default to gate 2

// Validate gate number
if (!in_array($gate, ['1', '2'])) {
    echo json_encode(["status" => "error", "message" => "Invalid gate number"]);
    exit;
}

// Arduino COM port
$port = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "COM6" : "/dev/ttyUSB0";

try {
    $handle = fopen($port, "r+b");

    if (!$handle) {
        throw new Exception("Unable to open serial port $port");
    }

    // Send STATUS command to Arduino
    fwrite($handle, "STATUS\r\n");
    fflush($handle);

    // Wait for Arduino response
    usleep(200000); // 200ms

    $response = "";
    $timeout = time() + 2; // 2 second timeout

    while (time() < $timeout) {
        $line = fgets($handle);
        if ($line !== false && trim($line) !== "") {
            $response .= trim($line) . " ";
            // Look for STATUS response
            if (strpos($response, "STATUS:") !== false) {
                break;
            }
        }
        usleep(50000); // 50ms
    }

    fclose($handle);

    // Parse the status response
    // Expected format: "STATUS: Gate1=OPEN Gate2=CLOSED"
    $gateStatus = "UNKNOWN";
    if (strpos($response, "STATUS:") !== false) {
        if (strpos($response, "Gate{$gate}=OPEN") !== false) {
            $gateStatus = "OPEN";
        } elseif (strpos($response, "Gate{$gate}=CLOSED") !== false) {
            $gateStatus = "CLOSED";
        }
    }

    echo json_encode([
        "status" => "success",
        "gate" => $gateStatus,
        "gate_number" => $gate,
        "arduino_command" => "STATUS",
        "arduino_response" => trim($response),
        "port" => $port
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "port" => $port
    ]);
}
?>