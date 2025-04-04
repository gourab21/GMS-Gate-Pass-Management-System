#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include "time.h"
#include "esp_sntp.h"

// WiFi Credentials
const char* ssid = "compscPG";
const char* password = "43073008";

// Firebase Realtime Database URL
const char* firebaseHost = "**************";
const char* firebaseAuth = "*********************";

// RFID Setup
#define SS_PIN 5
#define RST_PIN 22
MFRC522 mfrc522(SS_PIN, RST_PIN);

// LED & Buzzer Pins
#define GREEN_LED 2  
#define RED_LED 4    
#define BUZZER 12    
#define WHITE 13
#define GREEN2 14 

// Timezone Offset for India
const long gmtOffset_sec = 19800;
const int daylightOffset_sec = 0;

void printLocalTime(String &currentTime) {
    struct tm timeinfo;
    if (!getLocalTime(&timeinfo)) return;
    char buffer[6];
    strftime(buffer, sizeof(buffer), "%H:%M", &timeinfo);
    currentTime = String(buffer);
}

void setup() {
    WiFi.begin(ssid, password);
    pinMode(GREEN_LED, OUTPUT);
    pinMode(RED_LED, OUTPUT);
    pinMode(BUZZER, OUTPUT);
    pinMode(WHITE, OUTPUT);
    pinMode(GREEN2, OUTPUT);
    while (WiFi.status() != WL_CONNECTED) delay(1000);

    SPI.begin();
    mfrc522.PCD_Init();
    configTime(gmtOffset_sec, daylightOffset_sec, "pool.ntp.org", "time.nist.gov");
}

void loop() {
    analogWrite(WHITE,195);
    if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) return;
    analogWrite(WHITE,0);
    String rfidID = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
        rfidID += String(mfrc522.uid.uidByte[i], HEX);
    }
    rfidID.toUpperCase();
    checkAccessRules(rfidID);
}

// Function to check access rules
void checkAccessRules(String rfidID) {
    if (WiFi.status() != WL_CONNECTED) return;

    String url = String(firebaseHost) + "students/" + rfidID + ".json?auth=" + firebaseAuth;
    HTTPClient http;
    http.begin(url);
    int httpResponseCode = http.GET();

    if (httpResponseCode == 200) {
        String payload = http.getString();
        DynamicJsonDocument doc(512);
        deserializeJson(doc, payload);

        if (!doc.isNull()) {
            int outStatus = doc["Out"];
            String greenLEDTime = doc["green_led"].as<String>();

            String currentTime;
            printLocalTime(currentTime);

            if (outStatus == 1) {
                grantAccessIN();
                updateOutStatus(rfidID, 0);
                
            } else {
                if (greenLEDTime == "00:00" || 
                   ((currentTime >= "05:00" && currentTime <= "08:30") && greenLEDTime <= currentTime) || 
                   (currentTime >= "08:30" && currentTime < "18:00") || 
                   ((currentTime >= "18:00" && currentTime <= "23:00") && greenLEDTime <= currentTime)) {
                    
                    grantAccess();

                    // Between 08:30 and 18:00 → Update only Out status
                    if (currentTime >= "08:30" && currentTime < "18:00") {
                        updateOutStatus(rfidID, 1);
                    } 
                    // Before 08:30 or after 18:00 → Update Out status + green_led
                    else {
                        updateOutStatus(rfidID, 1);
                        updateGreenLED(rfidID, "23:59");
                    }

                } else {
                    denyAccess();
                }
            }
        } else {
            denyAccess();
        }
    }
    http.end();
}

// Function to update Out status in Firebase
void updateOutStatus(String rfidID, int newStatus) {
    if (WiFi.status() != WL_CONNECTED) return;

    String url = String(firebaseHost) + "students/" + rfidID + "/Out.json?auth=" + firebaseAuth;
    HTTPClient http;
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    String payload = String(newStatus);
    http.PUT(payload);
    http.end();
}

// Function to update green_led in Firebase
void updateGreenLED(String rfidID, String newTime) {
    if (WiFi.status() != WL_CONNECTED) return;

    String url = String(firebaseHost) + "students/" + rfidID + "/green_led.json?auth=" + firebaseAuth;
    HTTPClient http;
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    String payload = "\"" + newTime + "\""; // Ensure it's a string in JSON format
    http.PUT(payload);
    http.end();
}

// Function for Access Granted OUT
void grantAccessIN() {
    digitalWrite(GREEN_LED, HIGH);
    digitalWrite(GREEN2, HIGH);
    digitalWrite(BUZZER, HIGH);
    delay(200);
    digitalWrite(BUZZER, LOW);
    delay(150);
    digitalWrite(BUZZER, HIGH);
    delay(200);
    digitalWrite(BUZZER, LOW);
    delay(300);
    digitalWrite(GREEN_LED, LOW);
    digitalWrite(GREEN2, LOW);
}

// Function for Access Granted for IN
void grantAccess() {
    digitalWrite(GREEN_LED, HIGH);
    digitalWrite(GREEN2, HIGH);
    digitalWrite(BUZZER, HIGH);
    delay(150);
    digitalWrite(BUZZER, LOW);
    digitalWrite(GREEN_LED, LOW);
    digitalWrite(GREEN2, LOW);
    delay(100);
    digitalWrite(BUZZER, HIGH);
    digitalWrite(GREEN_LED, HIGH);
    digitalWrite(GREEN2, HIGH);
    delay(150);
    digitalWrite(BUZZER, LOW);
    digitalWrite(GREEN_LED, LOW);
    digitalWrite(GREEN2, LOW);
    delay(100);
    digitalWrite(BUZZER, HIGH);
    digitalWrite(GREEN_LED, HIGH);
    digitalWrite(GREEN2, HIGH);
    delay(150);
    digitalWrite(BUZZER, LOW);
    digitalWrite(GREEN_LED, LOW);
    digitalWrite(GREEN2, LOW);
}

// Function for Access Denied
void denyAccess() {
    digitalWrite(RED_LED, HIGH);
    digitalWrite(BUZZER, HIGH);
    delay(500);
    digitalWrite(RED_LED, LOW);
    digitalWrite(BUZZER, LOW);
}
