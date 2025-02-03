#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <SoftwareSerial.h>

const char* ssid = "YourWiFiSSID";
const char* password = "YourWiFiPassword";
const char* serverUrl = "http://your-local-server/rfidattendance/api/devices.php";

#define SS_PIN D2
#define RST_PIN D1
#define GREEN_LED D3
#define RED_LED D4

SoftwareSerial arduinoSerial(D6, D5); // RX, TX
MFRC522 rfid(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(115200);
  arduinoSerial.begin(9600);
  
  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  
  SPI.begin();
  rfid.PCD_Init();
  
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected");
}

void loop() {
  // Check for fingerprint verification from Arduino
  if (arduinoSerial.available()) {
    String fingerData = arduinoSerial.readStringUntil('\n');
    DynamicJsonDocument doc(200);
    deserializeJson(doc, fingerData);
    
    if (doc.containsKey("fingerprint_id")) {
      sendVerificationRequest("fingerprint", "", doc["fingerprint_id"]);
    }
  }

  // Check for RFID
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    delay(50);
    return;
  }

  String rfidUid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    rfidUid += String(rfid.uid.uidByte[i] < 0x10 ? "0" : "");
    rfidUid += String(rfid.uid.uidByte[i], HEX);
  }
  rfidUid.toUpperCase();

  sendVerificationRequest("rfid", rfidUid, 0);
  
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  delay(1000);
}

void sendVerificationRequest(String type, String rfidUid, int fingerprintId) {
  if (WiFi.status() == WL_CONNECTED) {
    WiFiClient client;
    HTTPClient http;

    StaticJsonDocument<200> doc;
    doc["device_id"] = WiFi.macAddress();
    doc["verification_type"] = type;
    
    if (type == "rfid") {
      doc["rfid_uid"] = rfidUid;
    } else {
      doc["fingerprint_id"] = fingerprintId;
    }

    String jsonString;
    serializeJson(doc, jsonString);

    http.begin(client, serverUrl);
    http.addHeader("Content-Type", "application/json");
    
    int httpCode = http.POST(jsonString);
    
    if (httpCode > 0) {
      String payload = http.getString();
      // Forward response to Arduino
      arduinoSerial.println(payload);

      DynamicJsonDocument response(1024);
      deserializeJson(response, payload);

      // Handle LED feedback
      if (response["status"] == "success") {
        digitalWrite(GREEN_LED, HIGH);
        delay(1000);
        digitalWrite(GREEN_LED, LOW);
      } else {
        digitalWrite(RED_LED, HIGH);
        delay(1000);
        digitalWrite(RED_LED, LOW);
      }
    }
    
    http.end();
  }
}