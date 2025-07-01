<?php
$serialPort = fopen("COM6", "w");
if (!$serialPort) {
    http_response_code(500);
    die("❌ Failed to open COM port.");
}

fwrite($serialPort, "OPEN\n");
fclose($serialPort);
echo "✅ Command sent to Arduino.";
?>
