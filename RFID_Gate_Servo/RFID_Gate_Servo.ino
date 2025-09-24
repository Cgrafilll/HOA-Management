#include <Servo.h>

// Servo objects for the two gates
Servo gate1Servo;
Servo gate2Servo;

// Servo pin assignments
const int GATE1_PIN = 9;
const int GATE2_PIN = 10;

// Ultrasonic sensor pins
const int TRIG_PIN1 = 2;
const int ECHO_PIN1 = 3;
const int TRIG_PIN2 = 4;
const int ECHO_PIN2 = 5;

// Gate status tracking
bool gate1Open = false;
bool gate2Open = false;

unsigned long gate1OpenTime = 0;
unsigned long gate2OpenTime = 0;

// Object detection timing
unsigned long object1DetectedTime = 0;
unsigned long object2DetectedTime = 0;

// Add new tracking variables at the top
bool object1WasDetected = false;
bool object2WasDetected = false;

void setup() {
  // Initialize serial communication
  Serial.begin(9600);

  // Attach servos to pins
  gate1Servo.attach(GATE1_PIN);
  gate2Servo.attach(GATE2_PIN);

  // Initialize ultrasonic sensor pins
  pinMode(TRIG_PIN1, OUTPUT);
  pinMode(ECHO_PIN1, INPUT);
  pinMode(TRIG_PIN2, OUTPUT);
  pinMode(ECHO_PIN2, INPUT);

  // Set both gates to closed position initially
  gate1Servo.write(80);
  gate2Servo.write(50);

  // Initialize gate status
  gate1Open = false;
  gate2Open = false;

  // Wait for servos to reach position
  delay(1000);

  // Signal ready to PHP
  Serial.println("READY");
  Serial.println("Gate Controller Initialized");
}

void loop() {
  // Check for incoming serial commands
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    command.toUpperCase();

    // Process the command
    processCommand(command);
  }

  // Handle object detection
  handleObjectDetection();

  // Small delay to prevent overwhelming the system
  delay(100);
}

void processCommand(String command) {
  if (command == "OPEN1" || command == "OPEN") {
    openGate(1);
  } else if (command == "OPEN2") {
    openGate(2);
  } else if (command == "CLOSE1" || command == "CLOSE") {
    closeGate(1);
  } else if (command == "CLOSE2") {
    closeGate(2);
  } else if (command == "STATUS") {
    reportStatus();
  } else if (command == "RESET") {
    resetAllGates();
  } else {
    Serial.println("ERROR: Unknown command - " + command);
  }
}

void openGate(int gateNumber) {
  if (gateNumber == 1) {
    if (!gate1Open) {
      gate1Servo.write(20);
      gate1Open = true;
      gate1OpenTime = millis();
      object1DetectedTime = 0;  // Reset detection timer
      object1WasDetected = false;
      Serial.println("SUCCESS: Gate1 opened");
    } else {
      Serial.println("INFO: Gate1 already open");
    }
  } else if (gateNumber == 2) {
    if (!gate2Open) {
      gate2Servo.write(120);
      gate2Open = true;
      gate2OpenTime = millis();
      object2DetectedTime = 0;  // Reset detection timer
      object2WasDetected = false;
      Serial.println("SUCCESS: Gate2 opened");
    } else {
      Serial.println("INFO: Gate2 already open");
    }
  } else {
    Serial.println("ERROR: Invalid gate number");
  }
}

void closeGate(int gateNumber) {
  if (gateNumber == 1) {
    if (gate1Open) {
      gate1Servo.write(80);
      gate1Open = false;
      gate1OpenTime = 0;
      object1DetectedTime = 0;
      object1WasDetected = false;
      Serial.println("SUCCESS: Gate1 closed");
    } else {
      Serial.println("INFO: Gate1 already closed");
    }
  } else if (gateNumber == 2) {
    if (gate2Open) {
      gate2Servo.write(50);
      gate2Open = false;
      gate2OpenTime = 0;
      object2DetectedTime = 0;
      object2WasDetected = false;
      Serial.println("SUCCESS: Gate2 closed");
    } else {
      Serial.println("INFO: Gate2 already closed");
    }
  } else {
    Serial.println("ERROR: Invalid gate number");
  }
}

void handleObjectDetection() {
  unsigned long currentTime = millis();

  // Check Gate 1 for objects (only if gate is open)
  if (gate1Open) {
    int distance1 = getDistance(TRIG_PIN1, ECHO_PIN1);

    if (distance1 <= 10 && distance1 > 0) {  // Object detected within 10cm
      if (!object1WasDetected) {
        object1WasDetected = true;
        Serial.println("DETECT1: Object detected at Gate1");
      }
      // Reset timer while object is still detected
      object1DetectedTime = 0;
    } else if (object1WasDetected && distance1 > 10) {
      // Object was detected but now gone - start countdown
      if (object1DetectedTime == 0) {
        object1DetectedTime = currentTime;
        Serial.println("DETECT1: Object cleared - closing Gate1 in 5s");
      }
    }

    // Close gate 5 seconds after object cleared
    if (object1DetectedTime > 0 && (currentTime - object1DetectedTime >= 5000)) {
      Serial.println("AUTO: Closing Gate1 after object passage");
      closeGate(1);
    }
  }

  // Check Gate 2 for objects (only if gate is open)
  if (gate2Open) {
    int distance2 = getDistance(TRIG_PIN2, ECHO_PIN2);

    if (distance2 <= 10 && distance2 > 0) {  // Object detected within 10cm
      if (!object2WasDetected) {
        object2WasDetected = true;
        Serial.println("DETECT2: Object detected at Gate2");
      }
      // Reset timer while object is still detected
      object2DetectedTime = 0;
    } else if (object2WasDetected && distance2 > 10) {
      // Object was detected but now gone - start countdown
      if (object2DetectedTime == 0) {
        object2DetectedTime = currentTime;
        Serial.println("DETECT2: Object cleared - closing Gate2 in 5s");
      }
    }

    // Close gate 5 seconds after object cleared
    if (object2DetectedTime > 0 && (currentTime - object2DetectedTime >= 5000)) {
      Serial.println("AUTO: Closing Gate2 after object passage");
      closeGate(2);
    }
  }
}

int getDistance(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);

  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 30000);  // 30ms timeout
  if (duration == 0) return 9999;                 // No echo received

  int distance = duration * 0.034 / 2;  // Convert to cm

  // Filter out invalid readings
  if (distance < 2 || distance > 400) {
    return 9999;
  }

  return distance;
}

void reportStatus() {
  Serial.println("STATUS: Gate1=" + String(gate1Open ? "OPEN" : "CLOSED") + " Gate2=" + String(gate2Open ? "OPEN" : "CLOSED"));
}

void resetAllGates() {
  Serial.println("RESET: Closing all gates");
  closeGate(1);
  closeGate(2);
  delay(100);
  Serial.println("RESET: Complete");
}

// Function to manually control servo positions (for testing)
void testServoMovement() {
  Serial.println("TEST: Moving servos");

  // Test Gate 1
  gate1Servo.write(20);
  delay(1000);
  gate1Servo.write(80);
  delay(1000);

  // Test Gate 2
  gate2Servo.write(120);
  delay(1000);
  gate2Servo.write(50);
  delay(1000);

  Serial.println("TEST: Complete");
}