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

  // If object detected and gate is closed → open gate
  if (distance > 0 && distance < objectThreshold && !gateOpen) {
    openGate();
    gateOpenTime = millis(); // Record time when opened
  }

  // If gate is open and enough time has passed → close gate
  if (gateOpen && millis() - gateOpenTime >= openDuration) {
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

  long duration = pulseIn(echoPin, HIGH);
  int distance = duration * 0.034 / 2; // cm
  return distance;
}
