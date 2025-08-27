#include <Servo.h>

Servo myservo;

#define trigPin 2
#define echoPin 3

const int objectThreshold = 20;          // cm - distance to detect object
const unsigned long autoCloseTimeout = 15000; // 15 seconds max open time
const unsigned long detectDelay = 2000;  // 2 seconds wait before closing

bool gateOpen = false;
unsigned long gateOpenTime = 0;
unsigned long lastHeartbeat = 0;
unsigned long detectStartTime = 0;
bool closingScheduled = false;

void setup() {
  Serial.begin(9600);
  myservo.attach(9);
  myservo.write(140); // closed position (avoids twitch)

  pinMode(trigPin, OUTPUT);
  pinMode(echoPin, INPUT);

  Serial.println("READY"); // Signal to PHP that Arduino is ready
  Serial.println("Gate initialized in CLOSED position");
  Serial.println("Object threshold: " + String(objectThreshold) + "cm");
}

void loop() {
  // 🔹 Listen for Serial commands from PHP
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    command.toUpperCase();

    if (command == "OPEN") {
      openGate();
    }
    else if (command == "CLOSE") {
      closeGate();
    }
    else if (command == "STATUS") {
      reportStatus();
    }
    else if (command == "PING") {
      Serial.println("PONG"); // Heartbeat response
    }
    else if (command == "DEBUG") {
      debugSensor();
    }
    else {
      Serial.println("ERROR: Unknown command: " + command);
    }
  }

  // 🔹 Ultrasonic sensor logic
  if (gateOpen) {
    int distance = getDistance();

    // Debug output every second
    static unsigned long lastDebug = 0;
    if (millis() - lastDebug > 1000) {
      Serial.println("DEBUG: Distance = " + String(distance) + "cm, Gate open for " + 
                     String((millis() - gateOpenTime)/1000) + "s");
      lastDebug = millis();
    }

    if (distance <= objectThreshold && distance > 0) {
      if (!closingScheduled) {
        closingScheduled = true;
        detectStartTime = millis();
        Serial.println("DETECT: Object detected at " + String(distance) + "cm → Closing in 2s...");
      }
    }

    // Check if delay passed and close gate
    if (closingScheduled && (millis() - detectStartTime >= detectDelay)) {
      closeGate();
      closingScheduled = false;
    }

    // 🔹 Auto-close timeout (safety feature)
    if ((millis() - gateOpenTime) > autoCloseTimeout && gateOpen) {
      Serial.println("TIMEOUT: Force closing gate after " + String(autoCloseTimeout/1000) + " seconds");
      closeGate();
      closingScheduled = false;
    }
  }

  // 🔹 Heartbeat every 30 seconds
  if (millis() - lastHeartbeat > 30000) {
    Serial.println("HEARTBEAT: System operational, Gate: " + String(gateOpen ? "OPEN" : "CLOSED"));
    lastHeartbeat = millis();
  }

  delay(100); // Reduced delay for more responsive sensor reading
}

void openGate() {
  if (!gateOpen) {
    myservo.write(50); // open position
    gateOpen = true;
    gateOpenTime = millis();
    closingScheduled = false;
    Serial.println("SUCCESS: Gate opened");
  } else {
    Serial.println("INFO: Gate already open");
  }
}

void closeGate() {
  if (gateOpen) {
    myservo.write(140); // closed position
    gateOpen = false;
    closingScheduled = false;
    Serial.println("SUCCESS: Gate closed");
  } else {
    Serial.println("INFO: Gate already closed");
  }
}

void reportStatus() {
  String status = gateOpen ? "OPEN" : "CLOSED";
  int distance = getDistance();
  Serial.println("STATUS: Gate=" + status + ", Distance=" + String(distance) + "cm");
}

void debugSensor() {
  Serial.println("=== SENSOR DEBUG ===");
  for (int i = 0; i < 10; i++) {
    int dist = getDistance();
    Serial.println("Reading " + String(i+1) + ": " + String(dist) + "cm");
    delay(200);
  }
  Serial.println("=== DEBUG COMPLETE ===");
}

int getDistance() {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 30000);
  if (duration == 0) return 9999; // No echo received

  int distance = duration * 0.034 / 2;
  
  // Filter out obviously bad readings
  if (distance < 2 || distance > 400) {
    return 9999;
  }
  
  return distance;
}
