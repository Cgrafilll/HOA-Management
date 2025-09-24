<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "No action provided"]);
    exit;
}

$action = strtoupper(trim($_POST['action']));
$gate = isset($_POST['gate']) ? trim($_POST['gate']) : '1'; // Default to gate 1

$valid_actions = ["OPEN", "CLOSE", "STATUS", "RESET"];

if (!in_array($action, $valid_actions)) {
    echo json_encode(["status" => "error", "message" => "Invalid action. Use: OPEN, CLOSE, STATUS, or RESET"]);
    exit;
}

// Validate gate number (only for OPEN/CLOSE actions)
if (($action == "OPEN" || $action == "CLOSE") && !in_array($gate, ['1', '2'])) {
    echo json_encode(["status" => "error", "message" => "Invalid gate number. Use 1 or 2"]);
    exit;
}

// Determine COM port based on operating system
$port = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? "COM6" : "/dev/ttyUSB0";

// Build Arduino command
if ($action == "STATUS" || $action == "RESET") {
    $arduino_command = $action;
} else {
    $arduino_command = $action . $gate; // OPEN1, OPEN2, CLOSE1, CLOSE2
}

try {
    // For Windows, use different approach
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows serial communication
        $output = [];
        $return_var = 0;

        // Create a temporary batch file to send command
        $batch_content = "@echo off\necho {$arduino_command} > {$port}\n";
        file_put_contents('temp_arduino_cmd.bat', $batch_content);

        exec('temp_arduino_cmd.bat', $output, $return_var);
        unlink('temp_arduino_cmd.bat'); // Clean up

        if ($return_var === 0) {
            $response = "Command sent successfully";
        } else {
            throw new Exception("Failed to send command to Arduino");
        }
    } else {
        // Linux/Unix serial communication
        $handle = fopen($port, "r+");

        if (!$handle) {
            throw new Exception("Unable to open serial port $port. Check if port exists and permissions are correct.");
        }

        // Configure serial port settings
        system("stty -F $port cs8 9600 ignbrk -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke noflsh -ixon -crtscts");

        // Send command to Arduino
        fwrite($handle, $arduino_command . "\n");
        fflush($handle);

        // Wait a moment for Arduino to process
        usleep(100000); // 100ms

        // Try to read response
        $response = "";
        $timeout = time() + 2; // 2 second timeout

        while (time() < $timeout) {
            if (($line = fgets($handle)) !== false) {
                $response .= trim($line) . " ";
                break;
            }
            usleep(50000); // 50ms
        }

        fclose($handle);

        if (empty($response)) {
            $response = "Command sent (no response received)";
        }
    }

    // Parse the gate status for response
    $gate_status = "UNKNOWN";
    if (strpos($response, "opened") !== false) {
        $gate_status = "OPEN";
    } elseif (strpos($response, "closed") !== false) {
        $gate_status = "CLOSED";
    } elseif ($action == "OPEN") {
        $gate_status = "OPEN"; // Assume success if no error
    } elseif ($action == "CLOSE") {
        $gate_status = "CLOSED"; // Assume success if no error
    }

    echo json_encode([
        "status" => "success",
        "action" => $action,
        "gate" => $gate_status,
        "gate_number" => $gate,
        "arduino_command" => $arduino_command,
        "arduino_response" => trim($response),
        "port" => $port
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "port" => $port,
        "command" => $arduino_command
    ]);
}

// Function to test Arduino connection
function testArduinoConnection($port)
{
    try {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return file_exists($port);
        } else {
            return file_exists($port) && is_readable($port) && is_writable($port);
        }
    } catch (Exception $e) {
        return false;
    }
}

// Add connection test if requested
if (isset($_GET['test'])) {
    $connection_ok = testArduinoConnection($port);
    echo json_encode([
        "port" => $port,
        "connection" => $connection_ok ? "OK" : "FAILED",
        "os" => PHP_OS
    ]);
    exit;
}
?>