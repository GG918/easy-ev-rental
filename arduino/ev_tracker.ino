#include <SoftwareSerial.h>
#include <TinyGPSPlus.h>
#include <ArduinoGraphics.h>
#include <Arduino_LED_Matrix.h>
#include <WiFiS3.h>
#include <WiFiClient.h>
#include <ArduinoJson.h>

// —— WiFi Config —— 
const char* ssid       = "YOUR_WIFI_SSID";     // Replace with your WiFi network name
const char* password   = "YOUR_WIFI_PASSWORD"; // Replace with your WiFi password

// —— Server Config —— 
const char* serverHost = "example.com";        // Replace with your server domain
const int   serverPort = 80;                   // Default HTTP port (use 443 for HTTPS)

// —— default value —— 
const char* deviceId    = "1";                 // Unique device identifier
const int    batteryLevelDefault = 100;

// —— upload delay —— 
const unsigned long reportInterval = 60UL * 1000; // Time interval for reporting data (e.g., every 60 seconds)
unsigned long lastReportTime = 0;

// —— GPS & matrix —— 
SoftwareSerial gpsSerial(3, 4); // D3<-TX, D4->RX (GPS Serial: D3 is TX, D4 is RX)
TinyGPSPlus gps;
ArduinoLEDMatrix matrix;
bool hasReceivedNMEA = false;
unsigned long lastDisplayTime = 0;
const unsigned long displayInterval = 2000;

// definition
void sendToServer(const char* device_id, float lat, float lng, float speed_mph, int battery_level);
void showSymbol(const char* text, uint32_t color);
void showScrollingDots(uint32_t color);

void setup() {
  Serial.begin(115200);
  while (!Serial);

  // GPS initialization
  gpsSerial.begin(115200);
  matrix.begin();
  Serial.println("🚀 GPS + LED Matrix + WiFi uploader starting...");

  // WiFi connection
  Serial.print("Connecting to WiFi");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println(" Connected!");
}

void loop() {
  // 1) GPS serial
  while (gpsSerial.available()) {
    char c = gpsSerial.read();
    gps.encode(c);
    hasReceivedNMEA = true;
  }

  unsigned long now = millis();

  // 2) Update display and upload every displayInterval
  if (now - lastDisplayTime >= displayInterval) {
    lastDisplayTime = now;

    if (!hasReceivedNMEA) {
      Serial.println("[🔴 Error] No GPS data received!");
      showSymbol("X", 0xFF0000);
    }
    else if (gps.location.isValid()) {
      float lat = gps.location.lat();
      float lng = gps.location.lng();
      float speed_mph = gps.speed.kmph() * 0.621371;

      Serial.print("[🟢 Fixed] ");
      Serial.print("Lat: "); Serial.print(lat,6);
      Serial.print(", Lng: "); Serial.print(lng,6);
      Serial.print(", Sats: "); Serial.print(gps.satellites.isValid() ? gps.satellites.value() : 0);
      Serial.print(", Speed: "); Serial.print(speed_mph); Serial.println(" mph");

      showSymbol("G", 0x00FF00);

      // Upload data
      if (now - lastReportTime >= reportInterval) {
        lastReportTime = now;
        sendToServer(deviceId, lat, lng, speed_mph, batteryLevelDefault);
      }
    }
    else {
      int sats = gps.satellites.isValid() ? gps.satellites.value() : 0;
      Serial.print("[🟡 Waiting] Satellites: "); Serial.println(sats);
      showScrollingDots(0xFFFF00);
    }
    hasReceivedNMEA = false;
  }
}

void sendToServer(const char* device_id, float lat, float lng, float speed_mph, int battery_level) {
  WiFiClient client;
  if (!client.connect(serverHost, serverPort)) {
    Serial.println("[X] Cannot connect to server");
    return;
  }

  StaticJsonDocument<256> doc;
  JsonObject loc = doc.createNestedObject("location");
  loc["lat"] = lat;
  loc["lng"] = lng;
  doc["device_id"]     = device_id;
  doc["speed_mph"]     = speed_mph;
  doc["battery_level"] = battery_level;

  String payload;
  serializeJson(doc, payload);
  Serial.println("Sending JSON payload:");
  Serial.println(payload);

  client.println("POST /store.php HTTP/1.1");
  client.print("Host: "); client.println(serverHost);
  client.println("Content-Type: application/json");
  client.print("Content-Length: "); client.println(payload.length());
  client.println("Connection: close");
  client.println();
  client.print(payload);

  while (client.connected() && !client.available()) delay(10);
  while (client.available()) {
    Serial.write(client.read());
  }
  client.stop();
}

void showSymbol(const char* text, uint32_t color) {
  matrix.beginDraw();
  matrix.clear();
  matrix.stroke(color);
  matrix.textFont(Font_5x7);
  matrix.text(text, 0, 1);
  matrix.endDraw();
}

void showScrollingDots(uint32_t color) {
  const int dotY = 3, dotSpacing = 3, dotCount = 4, frameDelay = 200;
  for (int offset = 0; offset < 12; offset++) {
    matrix.beginDraw();
    matrix.clear();
    matrix.stroke(color);
    for (int i = 0; i < dotCount; i++) {
      int x = (offset + i * dotSpacing) % 12;
      matrix.point(x, dotY);
    }
    matrix.endDraw();
    delay(frameDelay);
  }
} 