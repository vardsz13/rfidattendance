#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <SoftwareSerial.h>

// WiFi credentials
const char* ssid = "UNKNOWN";
const char* password = "@Wifinaminto1";
const char* serverUrl = "http://192.168.1.12/rfidattendance/api/devices.php";

// Pin Definitions
#define SS_PIN D2    // RFID SS/SDA
#define RST_PIN D1   // RFID RST
#define GREEN_LED D3
#define RED_LED D4

// Software Serial for Arduino
SoftwareSerial arduinoSerial(D6, D5); // RX, TX

MFRC522 rfid(SS_PIN, RST_PIN);

void setup() {
    Serial.begin(115200);  // Debug serial
    arduinoSerial.begin(9600);  // Arduino communication
    
    // Initialize LEDs
    pinMode(GREEN_LED, OUTPUT);
    pinMode(RED_LED, OUTPUT);
    digitalWrite(GREEN_LED, LOW);
    digitalWrite(RED_LED, LOW);
    
    // Initialize RFID
    SPI.begin();
    rfid.PCD_Init();
    
    // Connect to WiFi
    WiFi.begin(ssid, password);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\nConnected to WiFi");
}

void loop() {
    // Check for RFID card
    if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
        String rfidUid = "";
        for (byte i = 0; i < rfid.uid.size; i++) {
            rfidUid += String(rfid.uid.uidByte[i] < 0x10 ? "0" : "");
            rfidUid += String(rfid.uid.uidByte[i], HEX);
        }
        rfidUid.toUpperCase();
        
        sendToServer("rfid", rfidUid, "");
        rfid.PICC_HaltA();
        rfid.PCD_StopCrypto1();
    }
    
    // Check for fingerprint data from Arduino
    if (arduinoSerial.available()) {
        String arduinoData = arduinoSerial.readStringUntil('\n');
        DynamicJsonDocument doc(200);
        deserializeJson(doc, arduinoData);
        
        if (doc.containsKey("fingerprint_id")) {
            String fingerprintId = doc["fingerprint_id"].as<String>();
            sendToServer("fingerprint", "", fingerprintId);
        }
    }
}

void sendToServer(String type, String rfidUid, String fingerprintId) {
    if (WiFi.status() == WL_CONNECTED) {
        WiFiClient client;
        HTTPClient http;
        
        // Create JSON payload
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

        // Send request
        http.begin(client, serverUrl);
        http.addHeader("Content-Type", "application/json");
        int httpCode = http.POST(jsonString);
        
        if (httpCode > 0) {
            String response = http.getString();
            
            // Forward response to Arduino
            arduinoSerial.println(response);
            
            // Handle LED feedback
            DynamicJsonDocument respDoc(1024);
            deserializeJson(respDoc, response);
            
            if (respDoc["status"] == "success") {
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