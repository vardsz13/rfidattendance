#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>

#define SS_PIN D2
#define RST_PIN D1
#define GREEN_LED D3
#define RED_LED D4

const char* ssid = "YourWiFiSSID";
const char* password = "YourWiFiPassword";
const char* serverUrl = "http://your-server/rfidattendance/api/devices.php";

MFRC522 rfid(SS_PIN, RST_PIN);
WiFiClient wifiClient;
HTTPClient http;

bool waitingForFingerprint = false;
unsigned long lastActionTime = 0;
const unsigned long TIMEOUT_DURATION = 30000;
String currentRfidUid = "";

void setup() {
  Serial.begin(9600);
  SPI.begin();
  rfid.PCD_Init();
  
  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    digitalWrite(RED_LED, !digitalRead(RED_LED));
  }
  digitalWrite(RED_LED, LOW);
  digitalWrite(GREEN_LED, HIGH);
  delay(1000);
  digitalWrite(GREEN_LED, LOW);
}

void loop() {
  if (Serial.available()) {
    String data = Serial.readStringUntil('\n');
    handleArduinoCommand(data);
  }
  
  if (!waitingForFingerprint && rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
    handleRFIDScan();
  }
  
  if (waitingForFingerprint && (millis() - lastActionTime) > TIMEOUT_DURATION) {
    resetSystem();
  }
}

void handleRFIDScan() {
  String rfidUID = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    rfidUID += String(rfid.uid.uidByte[i], HEX);
  }
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  
  currentRfidUid = rfidUID;
  
  if (WiFi.status() == WL_CONNECTED) {
    if (http.begin(wifiClient, serverUrl)) {
      http.addHeader("Content-Type", "application/json");
      
      StaticJsonDocument<200> jsonDoc;
      jsonDoc["rfid_uid"] = rfidUID;
      jsonDoc["device_id"] = "ESP_001";
      
      String jsonString;
      serializeJson(jsonDoc, jsonString);
      
      int httpCode = http.POST(jsonString);
      
      if (httpCode == HTTP_CODE_OK) {
        String response = http.getString();
        StaticJsonDocument<512> responseDoc;
        DeserializationError error = deserializeJson(responseDoc, response);
        
        if (!error) {
          const char* status = responseDoc["status"];
          const char* lcdMessage = responseDoc["lcd_message"];
          const char* buzzerTone = responseDoc["buzzer_tone"];
          
          if (String(status) == "waiting") {
            waitingForFingerprint = true;
            lastActionTime = millis();
            digitalWrite(GREEN_LED, HIGH);
          } else if (String(status) == "success") {
            handleSuccess(responseDoc);
          } else {
            handleError();
          }
          
          // Send LCD message to Arduino
          Serial.println("LCD:" + String(lcdMessage));
          // Send buzzer command to Arduino
          Serial.println("BUZ:" + String(buzzerTone));
        }
      }
      http.end();
    }
  }
}

void handleArduinoCommand(String command) {
  if (command.startsWith("FP:")) {
    int fingerprintID = command.substring(3).toInt();
    if (fingerprintID >= 0 && waitingForFingerprint) {
      if (WiFi.status() == WL_CONNECTED) {
        if (http.begin(wifiClient, serverUrl)) {
          http.addHeader("Content-Type", "application/json");
          
          StaticJsonDocument<200> jsonDoc;
          jsonDoc["rfid_uid"] = currentRfidUid;
          jsonDoc["fingerprint_id"] = fingerprintID;
          jsonDoc["device_id"] = "ESP_001";
          
          String jsonString;
          serializeJson(jsonDoc, jsonString);
          
          int httpCode = http.POST(jsonString);
          
          if (httpCode == HTTP_CODE_OK) {
            String response = http.getString();
            StaticJsonDocument<512> responseDoc;
            DeserializationError error = deserializeJson(responseDoc, response);
            
            if (!error) {
              const char* status = responseDoc["status"];
              const char* lcdMessage = responseDoc["lcd_message"];
              const char* buzzerTone = responseDoc["buzzer_tone"];
              
              if (String(status) == "success") {
                handleSuccess(responseDoc);
              } else {
                handleError();
              }
              
              Serial.println("LCD:" + String(lcdMessage));
              Serial.println("BUZ:" + String(buzzerTone));
            }
          }
          http.end();
        }
      }
      resetSystem();
    }
  }
}

void handleSuccess(const JsonDocument& response) {
  digitalWrite(GREEN_LED, HIGH);
  delay(1000);
  digitalWrite(GREEN_LED, LOW);
}

void handleError() {
  digitalWrite(RED_LED, HIGH);
  digitalWrite(GREEN_LED, LOW);
  delay(1000);
  digitalWrite(RED_LED, LOW);
}

void resetSystem() {
  waitingForFingerprint = false;
  currentRfidUid = "";
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
}