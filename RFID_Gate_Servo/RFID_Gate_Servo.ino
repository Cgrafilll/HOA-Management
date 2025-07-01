#include <Servo.h>

Servo myservo;

void setup() {
  Serial.begin(9600);
  myservo.attach(9); // Adjust pin if needed
}

void loop() {
  if (Serial.available()) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    if (command == "OPEN") {
      myservo.write(90);   // Open
      delay(5000);
      myservo.write(0);    // Close
    }
  }
}
