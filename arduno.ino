#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>
#include <Adafruit_Fingerprint.h>
#include <SoftwareSerial.h>

// LCD Setup
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Fingerprint Sensor
SoftwareSerial fingerprintSerial(2, 3);  // RX, TX
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&fingerprintSerial);

// ESP Communication
SoftwareSerial espSerial(10, 11);  // RX, TX

// Buzzer
#define BUZZER_PIN 8
#define TONE_SUCCESS 1000
#define TONE_ERROR 500
#define TONE_WAIT 700

bool awaitingFingerprint = false;

void setup() {
    Serial.begin(9600);       // Debug serial
    espSerial.begin(9600);    // ESP communication
    
    // Initialize LCD
    Wire.begin();
    lcd.init();
    lcd.backlight();
    
    // Initialize Fingerprint sensor
    finger.begin(57600);
    if (!finger.verifyPassword()) {
        lcd.clear();
        lcd.print("Sensor Error");
        while (1);
    }
    
    // Initialize buzzer
    pinMode(BUZZER_PIN, OUTPUT);
    
    // Ready message
    lcd.clear();
    lcd.print("System Ready");
    lcd.setCursor(0, 1);
    lcd.print("Scan RFID Card");
}

void loop() {
    // Check for response from ESP
    if (espSerial.available()) {
        String response = espSerial.readStringUntil('\n');
        handleResponse(response);
    }
    
    // If awaiting fingerprint, check sensor
    if (awaitingFingerprint) {
        int fingerprintID = getFingerprintID();
        if (fingerprintID >= 0) {
            // Send to ESP
            StaticJsonDocument<128> doc;
            doc["fingerprint_id"] = fingerprintID;
            String jsonString;
            serializeJson(doc, jsonString);
            espSerial.println(jsonString);
            
            awaitingFingerprint = false;
            lcd.clear();
            lcd.print("Processing...");
        }
    }
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

void handleResponse(String response) {
    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, response);
    
    if (error) {
        lcd.clear();
        lcd.print("Error");
        playTone(TONE_ERROR, 1000);
        return;
    }
    
    String status = doc["status"];
    String message = doc["lcd_message"].as<String>();
    String buzzerTone = doc["buzzer_tone"].as<String>();
    
    // Display message
    lcd.clear();
    lcd.print(message);
    
    // Handle tones
    if (buzzerTone == "SUCCESS_TONE") {
        playTone(TONE_SUCCESS, 200);
    } else if (buzzerTone == "ERROR_TONE") {
        playTone(TONE_ERROR, 500);
    } else if (buzzerTone == "WAIT_TONE") {
        playTone(TONE_WAIT, 100);
    }
    
    // Check if fingerprint needed
    if (status == "success" && doc["verification_type"] == "rfid") {
        delay(1000);
        lcd.clear();
        lcd.print("Place Finger");
        awaitingFingerprint = true;
    }
}

void playTone(int frequency, int duration) {
    tone(BUZZER_PIN, frequency, duration);
    delay(duration);
    noTone(BUZZER_PIN);
}