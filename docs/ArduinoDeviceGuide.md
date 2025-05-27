# Arduino Tracking Device Guide

## Overview

The EV Rental System uses Arduino UNO R4 WiFi microcontrollers with GPS modules to track vehicle locations in real-time. Each vehicle is equipped with a tracking device that collects GPS data and transmits it to the central server.

For installation and initial configuration of the Arduino device and its software, please refer to **Part 2** of the `DeploymentGuide.md`.

This document covers:
- Hardware Components Overview
- Device Features
- Wiring Diagram
- LED Matrix Status Indicators
- Data Format for Server Communication
- General Operation Flow
- Troubleshooting Tips

## Hardware Components

- **Microcontroller**: Arduino UNO R4 WiFi (or a compatible board with WiFi and sufficient pins)
  - *Key Role*: Processes GPS data, manages WiFi communication, controls status indicators.
- **GPS Module**: AT6668 GPS SMA (or compatible NMEA-output GPS module)
  - *Key Role*: Acquires satellite signals to determine geographic location (latitude, longitude, speed, etc.).
- **Display**: Built-in LED Matrix on Arduino UNO R4 WiFi (or external LEDs/display if using another board)
  - *Key Role*: Provides visual status feedback (GPS fix, WiFi connection, errors).
- **Power**: Typically powered from the vehicle's battery through a voltage regulator (e.g., a buck converter) to supply a stable 5V to the Arduino.

## Features

- Real-time GPS tracking (latitude, longitude, speed).
- Visual status indication via LED matrix (e.g., GPS fix status, errors).
- Periodic data transmission to the central server via HTTP POST requests over WiFi.
- Device-specific identification (`deviceId`).
- Configurable reporting interval (`reportInterval`).
- Battery level monitoring (if implemented with a voltage divider and analog input - current `ev_tracker.ino` example sends a static value but can be extended).

## Wiring Diagram (Typical for `ev_tracker.ino`)

Refer to `DeploymentGuide.md` for initial setup wiring.

**GPS Module Connection to Arduino UNO R4 WiFi:**

*   **GPS TX (Transmit)** → Arduino **Digital Pin D3** (Configured as RX for `SoftwareSerial`)
*   **GPS RX (Receive)** → Arduino **Digital Pin D4** (Configured as TX for `SoftwareSerial`)
*   **GPS VCC (Power)** → Arduino **5V** Pin
*   **GPS GND (Ground)** → Arduino **GND** Pin

*Note: Pin assignments for SoftwareSerial (D3, D4) are defined in `arduino/ev_tracker.ino`. If you change these pins, update the `SoftwareSerial gpsSerial(3, 4);` line in the code.* Ensure your GPS module's logic levels are compatible with the Arduino (typically 5V or 3.3V; the UNO R4 is 5V tolerant on I/O pins).

## LED Matrix Status Indicators (Arduino UNO R4 WiFi)

The built-in 12x8 LED matrix on the Arduino UNO R4 WiFi is used to display status. The logic is in the `showSymbol()` and `showScrollingDots()` functions in `arduino/ev_tracker.ino`.

-   **`G` (Green)**: GPS fix has been successfully obtained, and the device is tracking normally.
-   **`X` (Red)**: No valid GPS data is being received from the GPS module, or another significant error has occurred (e.g., WiFi connection failed repeatedly - though current sketch mainly uses 'X' for GPS issues).
-   **Scrolling Dots (Yellow/Orange)**: The device is actively waiting to obtain a GPS fix from the satellites. This is common on startup or if the GPS signal is weak.
-   Other symbols or patterns could be programmed for different statuses (e.g., WiFi connection progress, data sending confirmation).

## Data Format (JSON sent to Server)

The Arduino device sends location data to the server (`/backend/api/store.php`) in JSON format via an HTTP POST request. The structure is as follows:

```json
{
  "location": {
    "lat": 53.3811,
    "lng": -1.4701
  },
  "device_id": "1111",
  "speed_mph": 12.5,
  "battery_level": 85,
  "status": "in_use" // Or "available", etc. This is sent by Arduino.
}
```

-   `location.lat`: Latitude in decimal degrees.
-   `location.lng`: Longitude in decimal degrees.
-   `device_id`: The unique identifier for the vehicle/device (configured in `ev_tracker.ino`).
-   `speed_mph`: Current speed in miles per hour, calculated from GPS data.
-   `battery_level`: Vehicle battery level percentage (current `ev_tracker.ino` sends a default value of 100 unless modified to read actual battery level).
-   `status`: Vehicle status reported by the Arduino (e.g. "in_use" or "available"). The server-side `store.php` might also calculate or override this based on speed if not provided or if specific server-side logic exists.

## Operation Flow

Once powered on and the sketch is running, the Arduino device will typically perform the following steps in a loop:

1.  **Initialization** (`setup()` function):
    *   Initialize serial communication (for debugging and GPS module).
    *   Initialize the LED matrix.
    *   Attempt to connect to the configured WiFi network. The LED matrix might show WiFi connection progress.
2.  **Main Loop** (`loop()` function):
    *   **Read GPS Data**: Continuously read and parse data from the GPS module using `TinyGPSPlus`.
    *   **Update LED Status**: Display the current GPS fix status (or other statuses) on the LED matrix.
    *   **Check Reporting Interval**: If a GPS fix is available and the `reportInterval` has elapsed since the last successful transmission:
        *   **Prepare Data**: Gather latitude, longitude, speed, device ID, battery level, and status into a JSON payload.
        *   **Connect to Server**: Establish an HTTP connection to the `serverHost` on `serverPort`.
        *   **Send Data**: Send the JSON payload as an HTTP POST request to the `/store.php` endpoint (or the configured API path).
        *   **Handle Response**: Check the server's HTTP response code. Log success or failure to the Serial Monitor.
        *   Update `lastReportTime`.
    *   Maintain WiFi connection; attempt to reconnect if dropped.

## Troubleshooting Common Issues

-   **No WiFi Connection / Repeated Connection Failures**:
    *   **Action**: Double-check SSID and password in `arduino/ev_tracker.ino`. Ensure your WiFi network is 2.4 GHz (Arduino UNO R4 WiFi does not support 5 GHz). Verify WiFi signal strength where the device is located. Check Serial Monitor for specific error messages from the WiFi library.

-   **No GPS Data / LED Matrix Shows 'X' or Stays on Scrolling Dots**:
    *   **Action**: 
        *   Verify GPS module wiring is correct (TX to RX, RX to TX, VCC, GND).
        *   Ensure the GPS module has a clear view of the sky. GPS signals do not penetrate well indoors or through dense materials.
        *   Check if the GPS module's antenna is properly connected (if external).
        *   Open Serial Monitor: `ev_tracker.ino` often prints raw NMEA sentences or `TinyGPSPlus` parsing status. If no characters are coming from the GPS, it might be a wiring or power issue to the module. If characters are garbled, check the baud rate (`gpsSerial.begin(9600)` must match GPS module default).
        *   Some GPS modules have an indicator LED; check its status.

-   **Server Connection Failed / Data Not Appearing on Server**:
    *   **Action**:
        *   Verify `serverHost` and `serverPort` in `arduino/ev_tracker.ino` are correct and the server is reachable from the Arduino's network.
        *   Check Serial Monitor for HTTP client error codes or messages when attempting to send data.
        *   Ensure the server-side endpoint (`/backend/api/store.php`) is operational and not throwing errors. Check server logs.
        *   If using HTTPS (`serverPort = 443`), ensure `WiFiClientSecure` is used and any necessary certificate handling is implemented (can be complex on Arduino).
        *   Firewall on the server or network might be blocking incoming connections on the specified port.

-   **Incorrect Location Data**: 
    *   **Action**: Usually due to poor GPS signal (multipath interference, urban canyons, indoor use). Try moving the device to an open sky area. Allow some time for the GPS to get a better fix (cold starts can take longer).

-   **Arduino Keeps Resetting**: 
    *   **Action**: Could be a power supply issue (insufficient current, unstable voltage). Could also be a software bug causing a watchdog reset or unhandled exception (check for infinite loops, memory issues if using dynamic allocation heavily - though `ev_tracker.ino` tends to be straightforward).

For further Arduino-specific issues, consulting Arduino forums and documentation for the WiFiS3, TinyGPSPlus, and ArduinoJson libraries can be very helpful. 