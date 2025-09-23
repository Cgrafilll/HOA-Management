#include <Servo.h>

Servo servo1;
Servo servo2;

// Ultrasonic Sensor pins
#define trigPin1 2
#define echoPin1 3
#define trigPin2 4
#define echoPin2 5

const int objectThreshold = 20;          // cm - distance to detect object
const unsigned long autoCloseTimeout = 15000; // 15 seconds max open time
const unsigned long detectDelay = 5000;  // 5 seconds wait before closing

// Gate 1 variables
bool gate1Open = false;
unsigned long gate1OpenTime = 0;
unsigned long detect1StartTime = 0;
bool closing1Scheduled = false;
bool object1Detected = false;

// Gate 2 variables
bool gate2Open = false;
unsigned long gate2OpenTime = 0;
unsigned long detect2StartTime = 0;
bool closing2Scheduled = false;
bool object2Detected = false;

unsigned long lastHeartbeat = 0;

void setup() {
  Serial.begin(9600);
  servo1.attach(9);
  servo2.attach(10);
  servo1.write(140); // closed position
  servo2.write(140); // closed position

  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(trigPin2, OUTPUT);
  pinMode(echoPin2, INPUT);

  Serial.println("READY"); // Signal to PHP that Arduino is ready
  Serial.println("Dual Gate System initialized - Both gates CLOSED");
  Serial.println("Object threshold: " + String(objectThreshold) + "cm");
}

void loop() {
  // 🔹 Listen for Serial commands from PHP
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    command.toUpperCase();

    if (command == "OPEN1" || command == "OPEN") {
      openGate(1);
    }
    else if (command == "OPEN2") {
      openGate(2);
    }
    else if (command == "OPENALL") {
      openGate(1);
      openGate(2);
    }
    else if (command == "CLOSE1" || command == "CLOSE") {
      closeGate(1);
    }
    else if (command == "CLOSE2") {
      closeGate(2);
    }
    else if (command == "CLOSEALL") {
      closeGate(1);
      closeGate(2);
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

  // 🔹 Gate 1 Logic (Entry Gate)
  if (gate1Open) {
    int distance1 = getDistance(trigPin1, echoPin1);

    // Debug output every 2 seconds for Gate 1
    static unsigned long lastDebug1 = 0;
    if (millis() - lastDebug1 > 2000) {
      Serial.println("DEBUG1: Distance = " + String(distance1) + "cm, Gate1 open for " + 
                     String((millis() - gate1OpenTime)/1000) + "s");
      lastDebug1 = millis();
    }

    // Detect object
    if (distance1 <= objectThreshold && distance1 > 0) {
      if (!object1Detected) {
        object1Detected = true;
        detect1StartTime = millis();
        Serial.println("DETECT1: Object detected at " + String(distance1) + "cm → Closing Gate1 in 5s...");
      }
    }

    // Close 5s after object detected
    if (object1Detected && (millis() - detect1StartTime >= detectDelay)) {
      closeGate(1);
      object1Detected = false;
      closing1Scheduled = false;
    }

    // Auto-close after 15s only if no object was detected
    if (!object1Detected && (millis() - gate1OpenTime > autoCloseTimeout) && gate1Open) {
      Serial.println("TIMEOUT1: No object detected → Closing Gate1 after 15s");
      closeGate(1);
    }
  }

  // 🔹 Gate 2 Logic (Exit Gate)
  if (gate2Open) {
    int distance2 = getDistance(trigPin2, echoPin2);

    // Debug output every 2 seconds for Gate 2
    static unsigned long lastDebug2 = 0;
    if (millis() - lastDebug2 > 2000) {
      Serial.println("DEBUG2: Distance = " + String(distance2) + "cm, Gate2 open for " + 
                     String((millis() - gate2OpenTime)/1000) + "s");
      lastDebug2 = millis();
    }

    // Detect object
    if (distance2 <= objectThreshold && distance2 > 0) {
      if (!object2Detected) {
        object2Detected = true;
        detect2StartTime = millis();
        Serial.println("DETECT2: Object detected at " + String(distance2) + "cm → Closing Gate2 in 5s...");
      }
    }

    // Close 5s after object detected
    if (object2Detected && (millis() - detect2StartTime >= detectDelay)) {
      closeGate(2);
      object2Detected = false;
      closing2Scheduled = false;
    }

    // Auto-close after 15s only if no object was detected
    if (!object2Detected && (millis() - gate2OpenTime > autoCloseTimeout) && gate2Open) {
      Serial.println("TIMEOUT2: No object detected → Closing Gate2 after 15s");
      closeGate(2);
    }
  }

  // 🔹 Heartbeat every 30 seconds
  if (millis() - lastHeartbeat > 30000) {
    Serial.println("HEARTBEAT: System operational, Gate1: " + String(gate1Open ? "OPEN" : "CLOSED") + 
                   ", Gate2: " + String(gate2Open ? "OPEN" : "CLOSED"));
    lastHeartbeat = millis();
  }

  delay(100);
}

void openGate(int gateNum) {
  if (gateNum == 1) {
    if (!gate1Open) {
      servo1.write(50); // open position
      gate1Open = true;
      gate1OpenTime = millis();
      object1Detected = false; // reset
      Serial.println("SUCCESS: Gate1 opened");
    } else {
      Serial.println("INFO: Gate1 already open");
    }
  }
  else if (gateNum == 2) {
    if (!gate2Open) {
      servo2.write(50); // open position
      gate2Open = true;
      gate2OpenTime = millis();
      object2Detected = false; // reset
      Serial.println("SUCCESS: Gate2 opened");
    } else {
      Serial.println("INFO: Gate2 already open");
    }
  }
}

void closeGate(int gateNum) {
  if (gateNum == 1) {
    if (gate1Open) {
      servo1.write(140); // closed position
      gate1Open = false;
      object1Detected = false;
      closing1Scheduled = false;
      Serial.println("SUCCESS: Gate1 closed");
    } else {
      Serial.println("INFO: Gate1 already closed");
    }
  }
  else if (gateNum == 2) {
    if (gate2Open) {
      servo2.write(140); // closed position
      gate2Open = false;
      object2Detected = false;
      closing2Scheduled = false;
      Serial.println("SUCCESS: Gate2 closed");
    } else {
      Serial.println("INFO: Gate2 already closed");
    }
  }
}

void reportStatus() {
  String status1 = gate1Open ? "OPEN" : "CLOSED";
  String status2 = gate2Open ? "OPEN" : "CLOSED";
  int distance1 = getDistance(trigPin1, echoPin1);
  int distance2 = getDistance(trigPin2, echoPin2);
  Serial.println("STATUS: Gate1=" + status1 + ", Distance1=" + String(distance1) + "cm");
  Serial.println("STATUS: Gate2=" + status2 + ", Distance2=" + String(distance2) + "cm");
}

void debugSensor() {
  Serial.println("=== SENSOR DEBUG ===");
  Serial.println("Sensor 1 (Pins 2,3) for Gate 1:");
  for (int i = 0; i < 5; i++) {
    int dist = getDistance(trigPin1, echoPin1);
    Serial.println("Reading " + String(i+1) + ": " + String(dist) + "cm");
    delay(200);
  }
  
  Serial.println("Sensor 2 (Pins 4,5) for Gate 2:");
  for (int i = 0; i < 5; i++) {
    int dist = getDistance(trigPin2, echoPin2);
    Serial.println("Reading " + String(i+1) + ": " + String(dist) + "cm");
    delay(200);
  }
  Serial.println("=== DEBUG COMPLETE ===");
}

int getDistance(int trigPin, int echoPin) {
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