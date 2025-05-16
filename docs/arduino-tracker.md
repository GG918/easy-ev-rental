# Arduino Tracking Device Documentation

## Overview

The EV Rental System uses Arduino UNO R4 WiFi microcontrollers with GPS modules to track vehicle locations in real-time. Each vehicle is equipped with a tracking device that collects GPS data and transmits it to the central server.

## Hardware Components

- **Microcontroller**: Arduino UNO R4 WiFi
- **GPS Module**: AT6668 GPS SMA
- **Display**: Built-in LED Matrix (for status indication)
- **Power**: Vehicle battery with voltage regulation

## Features

- Real-time GPS tracking
- Visual status indication via LED matrix
- Periodic data transmission to server
- Battery level monitoring
- Speed calculation

## Configuration

The Arduino code (`arduino/ev_tracker.ino`) contains the following configurable parameters:

### WiFi Configuration
```c
const char* ssid       = "checkcheck";
const char* password   = "12356789";
```

### Server Configuration
```c
const char* serverHost = "easyrides.co.uk";
const int   serverPort = 80;
```

### Device Identification
```c
const char* deviceId    = "1111";
```

### Reporting Interval
```c
const unsigned long reportInterval = 60UL * 1000;  // 60 seconds
```

## Wiring Diagram

### GPS Module Connection
- GPS TX → Arduino Digital Pin 3
- GPS RX → Arduino Digital Pin 4
- GPS VCC → Arduino 5V
- GPS GND → Arduino GND

## LED Matrix Status Indicators

The built-in LED matrix displays the following status indicators:

- **"G" (Green)**: GPS fix obtained, tracking normally
- **"X" (Red)**: No GPS data received (error state)
- **Scrolling Dots (Yellow)**: Waiting for GPS fix

## Data Format

The device sends JSON-formatted data to the server with the following structure:

```json
{
  "location": {
    "lat": 53.3811,
    "lng": -1.4701
  },
  "device_id": "1111",
  "speed_mph": 12.5,
  "battery_level": 100
}
```

## Installation Instructions

1. Install the Arduino IDE
2. Install the following libraries through the Arduino Library Manager:
   - SoftwareSerial
   - TinyGPSPlus
   - ArduinoGraphics
   - Arduino_LED_Matrix
   - WiFiS3
   - ArduinoJson

3. Open `arduino/ev_tracker.ino` in the Arduino IDE
4. Configure the WiFi and server settings in the code
5. Connect the Arduino to your computer via USB
6. Select the appropriate board (Arduino UNO R4 WiFi) and port
7. Upload the sketch to the Arduino

## Operation

Once powered on, the device will:
1. Initialize the GPS module and LED matrix
2. Connect to the configured WiFi network
3. Begin reading GPS data
4. Display status on the LED matrix
5. When a GPS fix is obtained, send location data to the server every `reportInterval` (default: 60 seconds)

## Troubleshooting

- **No WiFi Connection**: Check SSID and password in the code
- **No GPS Data**: Ensure the GPS module is properly connected and has a clear view of the sky
- **Server Connection Failed**: Verify the server host and port settings
- **LED Matrix Shows 'X'**: GPS module is not sending valid data, check connections 