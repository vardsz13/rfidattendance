#include <Adafruit_Fingerprint.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>
#include <SoftwareSerial.h>

SoftwareSerial fingerprintSerial(2, 3);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&fingerprintSerial);

SoftwareSerial espSerial(10, 11);
LiquidCrystal_I2C lcd(0x27, 16, 2);

#define BUZZER_PIN 8
#define TONE_SUCCESS 1000
#define TONE_ERROR 500
#define TONE_WAIT 700

bool registrationMode = false;
uint8_t lastFingerprintId = 0;

void setup() {
  Serial.begin(9600);
  espSerial.begin(9600);
  
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.print("System Ready");
  
  finger.begin(57600);
  if (!finger.verifyPassword()) {
    lcd.clear();
    lcd.print("Sensor Error");
    while (1);
  }
  
  pinMode(BUZZER_PIN, OUTPUT);
}

void loop() {
  if (espSerial.available()) {
    String response = espSerial.readStringUntil('\n');
    DynamicJsonDocument doc(1024);
    deserializeJson(doc, response);

    String status = doc["status"];
    String lcdMessage = doc["lcd_message"];
    String buzzerTone = doc["buzzer_tone"];
    registrationMode = doc["mode"] == "register";

    lcd.clear();
    lcd.print(lcdMessage);

    if (buzzerTone == "SUCCESS_TONE") {
      playTone(TONE_SUCCESS, 200);
    } else if (buzzerTone == "ERROR_TONE") {
      playTone(TONE_ERROR, 500);
    } else if (buzzerTone == "WAIT_TONE") {
      playTone(TONE_WAIT, 100);
    }

    // For verification mode
    if (!registrationMode && status == "success" && doc["verification_type"] == "rfid") {
      delay(1000);
      lcd.clear();
      lcd.print("Place Finger");
      
      int fingerprintId = getFingerprintID();
      if (fingerprintId >= 0) {
        DynamicJsonDocument fingerDoc(200);
        fingerDoc["fingerprint_id"] = fingerprintId;
        String fingerJson;
        serializeJson(fingerDoc, fingerJson);
        espSerial.println(fingerJson);
      }
    }
  }

  // Handle registration mode
  if (registrationMode && Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    if (cmd.startsWith("ENROLL:")) {
      int id = cmd.substring(7).toInt();
      if (id > 0 && id <= 127) {
        if (enrollFingerprint(id)) {
          lcd.clear();
          lcd.print("Enrolled ID: ");
          lcd.print(id);
          playTone(TONE_SUCCESS, 200);
        } else {
          lcd.clear();
          lcd.print("Enroll Failed");
          playTone(TONE_ERROR, 500);
        }
      }
    }
  }
}

uint8_t enrollFingerprint(uint8_t id) {
  int p = -1;
  lcd.clear();
  lcd.print("Place finger");
  
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
  }

  p = finger.image2Tz(1);
  if (p != FINGERPRINT_OK) return p;

  lcd.clear();
  lcd.print("Remove finger");
  delay(2000);
  
  lcd.clear();
  lcd.print("Place again");
  
  p = 0;
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
  }

  p = finger.image2Tz(2);
  if (p != FINGERPRINT_OK) return p;

  p = finger.createModel();
  if (p != FINGERPRINT_OK) return p;

  p = finger.storeModel(id);
  if (p != FINGERPRINT_OK) return p;

  return true;
}

int getFingerprintID() {
  uint8_t p = finger.getImage();
  if (p != FINGERPRINT_OK) return -1;

  p = finger.image2Tz();
  if (p != FINGERPRINT_OK) return -1;

  p = finger.fingerFastSearch();
  if (p != FINGERPRINT_OK) return -1;

  return finger.fingerID;
}

void playTone(int frequency, int duration) {
  tone(BUZZER_PIN, frequency, duration);
  delay(duration);
  noTone(BUZZER_PIN);
}