#include <Servo.h>

Servo myservo;

// Ultrasonic pins
#define trigPin 2
#define echoPin 3

// Distance threshold in cm
const int objectThreshold = 20;
const unsigned long openDuration = 5000; // Gate stays open for 5 seconds

bool gateOpen = false;
unsigned long gateOpenTime = 0;

void setup() {
  Serial.begin(9600);
  
  myservo.attach(9);
  myservo.write(0); // Initially closed
  
  pinMode(trigPin, OUTPUT);
  pinMode(echoPin, INPUT);
}

void loop() {
  int distance = getDistance();

  // Only open gate if object is detected and gate is currently closed
  if (!gateOpen && distance > 0 && distance < objectThreshold) {
    openGate();
    gateOpenTime = millis();
  }

  // Close gate if open and:
  // 1) the timer expires, OR
  // 2) the object is no longer detected
  if (gateOpen && (millis() - gateOpenTime >= openDuration || distance >= objectThreshold)) {
    closeGate();
  }
}

void openGate() {
  myservo.write(90); // Open position
  gateOpen = true;
  Serial.println("Gate opened.");
}

void closeGate() {
  myservo.write(0); // Close position
  gateOpen = false;
  Serial.println("Gate closed.");
}

int getDistance() {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);

  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 30000); // Timeout to avoid hangs
  if (duration == 0) return -1; // No echo detected

  int distance = duration * 0.034 / 2; // cm
  return distance;
}
