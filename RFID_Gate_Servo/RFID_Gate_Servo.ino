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
bool object1Detected = false;

// Gate 2 variables
bool gate2Open = false;
unsigned long gate2OpenTime = 0;
unsigned long detect2StartTime = 0;
bool object2Detected = false;

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

  Serial.println("READY");
}

void loop() {
  // Listen for Serial commands from PHP
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
    else if (command == "CLOSE1" || command == "CLOSE") {
      closeGate(1);
    }
    else if (command == "CLOSE2") {
      closeGate(2);
    }
  }

  // Gate 1 Logic (Entry Gate)
  if (gate1Open) {
    int distance1 = getDistance(trigPin1, echoPin1);

    // Detect object
    if (distance1 <= objectThreshold && distance1 > 0) {
      if (!object1Detected) {
        object1Detected = true;
        detect1StartTime = millis();
        Serial.println("DETECT1: Object detected - Closing Gate1 in 5s");
      }
    }

    // Close 5s after object detected
    if (object1Detected && (millis() - detect1StartTime >= detectDelay)) {
      closeGate(1);
      object1Detected = false;
    }

    // Auto-close after 15s if no object detected
    if (!object1Detected && (millis() - gate1OpenTime > autoCloseTimeout)) {
      closeGate(1);
    }
  }

  // Gate 2 Logic (Exit Gate)
  if (gate2Open) {
    int distance2 = getDistance(trigPin2, echoPin2);

    // Detect object
    if (distance2 <= objectThreshold && distance2 > 0) {
      if (!object2Detected) {
        object2Detected = true;
        detect2StartTime = millis();
        Serial.println("DETECT2: Object detected - Closing Gate2 in 5s");
      }
    }

    // Close 5s after object detected
    if (object2Detected && (millis() - detect2StartTime >= detectDelay)) {
      closeGate(2);
      object2Detected = false;
    }

    // Auto-close after 15s if no object detected
    if (!object2Detected && (millis() - gate2OpenTime > autoCloseTimeout)) {
      closeGate(2);
    }
  }

  delay(100);
}

void openGate(int gateNum) {
  if (gateNum == 1 && !gate1Open) {
    servo1.write(50);
    gate1Open = true;
    gate1OpenTime = millis();
    object1Detected = false;
    Serial.println("SUCCESS: Gate1 opened");
  }
  else if (gateNum == 2 && !gate2Open) {
    servo2.write(50);
    gate2Open = true;
    gate2OpenTime = millis();
    object2Detected = false;
    Serial.println("SUCCESS: Gate2 opened");
  }
}

void closeGate(int gateNum) {
  if (gateNum == 1 && gate1Open) {
    servo1.write(140);
    gate1Open = false;
    object1Detected = false;
    Serial.println("SUCCESS: Gate1 closed");
  }
  else if (gateNum == 2 && gate2Open) {
    servo2.write(140);
    gate2Open = false;
    object2Detected = false;
    Serial.println("SUCCESS: Gate2 closed");
  }
}

int getDistance(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 30000);
  if (duration == 0) return 9999;

  int distance = duration * 0.034 / 2;
  
  if (distance < 2 || distance > 400) {
    return 9999;
  }
  
  return distance;
}