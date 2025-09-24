#include <Servo.h>

// Servo objects for the two gates
Servo gate1Servo;
Servo gate2Servo;

// Servo pin assignments
const int GATE1_PIN = 9;
const int GATE2_PIN = 10;

// Gate status tracking
bool gate1Open = false;
bool gate2Open = false;

// Auto-close timing (optional - can be disabled by setting to 0)
const unsigned long AUTO_CLOSE_DELAY = 10000; // 10 seconds in milliseconds
unsigned long gate1OpenTime = 0;
unsigned long gate2OpenTime = 0;

void setup() {
  // Initialize serial communication
  Serial.begin(9600);
  
  // Attach servos to pins
  gate1Servo.attach(GATE1_PIN);
  gate2Servo.attach(GATE2_PIN);
  
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
  
  // Handle auto-close functionality (if enabled)
  if (AUTO_CLOSE_DELAY > 0) {
    handleAutoClose();
  }
  
  // Small delay to prevent overwhelming the system
  delay(50);
}

void processCommand(String command) {
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
  else if (command == "STATUS") {
    reportStatus();
  }
  else if (command == "RESET") {
    resetAllGates();
  }
  else {
    Serial.println("ERROR: Unknown command - " + command);
  }
}

void openGate(int gateNumber) {
  if (gateNumber == 1) {
    if (!gate1Open) {
      gate1Servo.write(20);
      gate1Open = true;
      gate1OpenTime = millis();
      Serial.println("SUCCESS: Gate1 opened");
    } else {
      Serial.println("INFO: Gate1 already open");
    }
  }
  else if (gateNumber == 2) {
    if (!gate2Open) {
      gate2Servo.write(120);
      gate2Open = true;
      gate2OpenTime = millis();
      Serial.println("SUCCESS: Gate2 opened");
    } else {
      Serial.println("INFO: Gate2 already open");
    }
  }
  else {
    Serial.println("ERROR: Invalid gate number");
  }
}

void closeGate(int gateNumber) {
  if (gateNumber == 1) {
    if (gate1Open) {
      gate1Servo.write(80);
      gate1Open = false;
      gate1OpenTime = 0;
      Serial.println("SUCCESS: Gate1 closed");
    } else {
      Serial.println("INFO: Gate1 already closed");
    }
  }
  else if (gateNumber == 2) {
    if (gate2Open) {
      gate2Servo.write(50);
      gate2Open = false;
      gate2OpenTime = 0;
      Serial.println("SUCCESS: Gate2 closed");
    } else {
      Serial.println("INFO: Gate2 already closed");
    }
  }
  else {
    Serial.println("ERROR: Invalid gate number");
  }
}

void handleAutoClose() {
  unsigned long currentTime = millis();
  
  // Auto-close Gate 1 if it's been open too long
  if (gate1Open && gate1OpenTime > 0 && (currentTime - gate1OpenTime >= AUTO_CLOSE_DELAY)) {
    Serial.println("AUTO: Closing Gate1 after timeout");
    closeGate(1);
  }
  
  // Auto-close Gate 2 if it's been open too long
  if (gate2Open && gate2OpenTime > 0 && (currentTime - gate2OpenTime >= AUTO_CLOSE_DELAY)) {
    Serial.println("AUTO: Closing Gate2 after timeout");
    closeGate(2);
  }
}

void reportStatus() {
  Serial.println("STATUS: Gate1=" + String(gate1Open ? "OPEN" : "CLOSED") + 
                 " Gate2=" + String(gate2Open ? "OPEN" : "CLOSED"));
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
  gate2Servo.write(80);
  delay(1000);
  
  Serial.println("TEST: Complete");
}