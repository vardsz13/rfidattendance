#include <Adafruit_Fingerprint.h>
#include <LiquidCrystal_I2C.h>
#include <SoftwareSerial.h>

// Communication pins
SoftwareSerial espSerial(10, 11);  // RX, TX for ESP8266
SoftwareSerial fingerprintSerial(2, 3);  // RX, TX for fingerprint sensor

// Initialize fingerprint sensor
Adafruit_Fingerprint fingerprint = Adafruit_Fingerprint(&fingerprintSerial);

// Initialize LCD
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Buzzer pin
#define BUZZER_PIN 8

// Buzzer tones
#define TONE_SUCCESS 2000  // Higher frequency for success
#define TONE_ERROR 500    // Lower frequency for error
#define TONE_WAIT 1000    // Medium frequency for waiting
#define TONE_LATE 700     // Specific tone for late status

void setup() {
  Serial.begin(9600);
  espSerial.begin(9600);
  
  // Initialize LCD
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.print("Initializing...");
  
  // Initialize fingerprint sensor
  fingerprint.begin(57600);
  if (!fingerprint.verifyPassword()) {
    lcd.clear();
    lcd.print("Sensor Error!");
    while(1) { delay(1000); }
  }
  
  // Initialize buzzer
  pinMode(BUZZER_PIN, OUTPUT);
  
  // Initial display
  lcd.clear();
  lcd.print("Ready to Scan");
  playTone("wait_tone");
}

void loop() {
  if (espSerial.available()) {
    String data = espSerial.readStringUntil('\n');
    handleESPCommand(data);
  }
  
  // Additional fingerprint checking logic here if needed
}

void handleESPCommand(String command) {
  if (command.startsWith("LCD:")) {
    String message = command.substring(4);
    updateDisplay(message);
  }
  else if (command.startsWith("BUZ:")) {
    String toneType = command.substring(4);
    playTone(toneType);
  }
}

void updateDisplay(String message) {
  lcd.clear();
  if (message.length() > 16) {
    String line1 = message.substring(0, 16);
    String line2 = message.substring(16);
    lcd.setCursor(0, 0);
    lcd.print(line1);
    lcd.setCursor(0, 1);
    lcd.print(line2);
  } else {
    lcd.print(message);
  }
}

void playTone(String toneType) {
  int freq = 0;
  int duration = 200;
  
  if (toneType == "success_tone") {
    // Success pattern: Two short high beeps
    tone(BUZZER_PIN, TONE_SUCCESS);
    delay(100);
    noTone(BUZZER_PIN);
    delay(50);
    tone(BUZZER_PIN, TONE_SUCCESS);
    delay(100);
    noTone(BUZZER_PIN);
  }
  else if (toneType == "error_tone") {
    // Error pattern: One long low beep
    tone(BUZZER_PIN, TONE_ERROR);
    delay(500);
    noTone(BUZZER_PIN);
  }
  else if (toneType == "wait_tone") {
    // Wait pattern: One short medium beep
    tone(BUZZER_PIN, TONE_WAIT);
    delay(100);
    noTone(BUZZER_PIN);
  }
  else if (toneType == "late_tone") {
    // Late pattern: Three short beeps
    for (int i = 0; i < 3; i++) {
      tone(BUZZER_PIN, TONE_LATE);
      delay(100);
      noTone(BUZZER_PIN);
      delay(50);
    }
  }
}

int getFingerprintID() {
  uint8_t p = fingerprint.getImage();
  if (p != FINGERPRINT_OK) return -1;
  
  p = fingerprint.image2Tz();
  if (p != FINGERPRINT_OK) return -1;
  
  p = fingerprint.fingerFastSearch();
  if (p != FINGERPRINT_OK) return -1;
  
  return fingerprint.fingerID;
}