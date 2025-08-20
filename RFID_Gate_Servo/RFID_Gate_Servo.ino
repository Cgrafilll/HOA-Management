#include <Servo.h>

Servo myservo;

#define trigPin 2
#define echoPin 3

const int objectThreshold = 20;          // cm
const unsigned long openDuration = 5000; // ms

bool gateOpen = false;
unsigned long gateOpenTime = 0;

void setup() {
  Serial.begin(9600);
  myservo.attach(9);
  myservo.write(20); // closed position (avoids twitch)

  pinMode(trigPin, OUTPUT);
  pinMode(echoPin, INPUT);

  Serial.println("System ready.");
}

void loop() {
  // 🔹 Listen for Serial commands
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();

    if (command.equalsIgnoreCase("OPEN")) {
      openGate();
      gateOpenTime = millis();
    }
    else if (command.equalsIgnoreCase("CLOSE")) {
      closeGate();
    }
  }

  // 🔹 Ultrasonic auto-close
  int distance = getDistance();

  if (gateOpen && distance <= objectThreshold) {
    closeGate(); // close if object detected
  }

  delay(50); // keep servo pulses smooth
}

void openGate() {
  myservo.write(160); // open
  gateOpen = true;
  Serial.println("Gate opened.");
}

void closeGate() {
  myservo.write(20); // closed
  gateOpen = false;
  Serial.println("Gate closed.");
}

int getDistance() {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 30000);
  if (duration == 0) return 9999;

  return duration * 0.034 / 2;
}
