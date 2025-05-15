# Tracking System and Software Part

## A Report Introduction (5%)

With the increasing demand for sustainable urban mobility and the rising adoption of electric vehicles (EVs), efficient management of shared micro-mobility systems has become essential. This project presents the development of a comprehensive EV rental and tracking platform, incorporating multidisciplinary efforts across motor design, motor control, infrastructure development, battery management systems (BMS), and real-time tracking software. The primary aim is to facilitate environmentally friendly transportation solutions while enhancing the user experience and operational efficiency for fleet operators.

---

## Project Aim

The project consists of several major components. Motor team and battery team.  
In parallel, the GPS-integrated software platform delivers seamless real-time vehicle tracking, vehicle booking. Users can access the system through a web-based interface to locate and rent available EVs, locate available charging stations, view vehicle status, and monitor ride history. Meanwhile, fleet administrators can access the cloud-based database through phpMyAdmin to view and manage all vehicle-related information.

---

## Project Objectives

Building on the integrated platform that connects users with available EVs and fleet managers with comprehensive operational insights. Core hardware components, including GPS modules and Wi-Fi-enabled microcontroller Arduino Uno R4 WiFi will be used to collect and transmit live vehicle data to a server hosted in a cloud environment. This real-time data is dynamically visualized using Leaflet.js combined with OpenStreetMap. Simultaneously, backend APIs support essential services such as user authentication, booking management, and database synchronization.

---

## Specifications

### Server
- IONOS

### Tracking Devices
- MCU: Arduino Wifi R4
- GPS module: UNIT GPS SMA - AT6668

### Network
- Agreement: 802.11 b/g/n
- Frequency band: 2.4 GHz

### User-side Application
#### Front-end
- Technology Stack:
  - HTML, CSS, PHP, JavaScript (JS)
- Map Engine:
  - Leaflet.js + OpenStreetMap
- Main Functional Modules:
  - User Interface
  - Rental Page
  - User Dashboard

#### Back-end
- Technology Stack:
  - PHP (API interface and database interaction)
  - Database: MariaDB (MySQL compatible)
  - Communication Protocol:
    - HTTP/HTTPS (API call)
    - WebSocket (real-time push)
- Core API Endpoints:
  - GET /api/locations → Get a real-time list of locations for all vehicles.
  - POST /api/book → User makes a reservation with the vehicle and changes the status.

### Database Tables
- **Locations:** Real-time location of the vehicle.
  - Id (Key), Vehicle_id, Latitude, Longitude, Status (Available/In_Use), Speed, last_updated
- **Bookings:** Reservations record.
  - Booking_id (Key), User_id, Vehicle_id, Start_time, End_time, Status (Reserved/In_Progress/Completed/Cancelled)
- **Users:** Users’ information.
  - User_id (Key), Username, Email, password_hash

---

## Main Functions

- Registration and login page.
- Vehicle rental page.
- Vehicle availability.
- Real-Time monitoring vehicles’ status.
- Real-time vehicle tracking map.
- Ride history and usage statistics.

---

## Performance & Responsiveness

- GPS Accuracy: ≤ 5m.
- Back-end:
  - Allow 1000 batch position writing every 1s.
  - Max Latency: ≤ 3s from GPS to database record.
  - Single API request response time: ≤ 500ms.

---

## Security
- Secure communication protocols (HTTPS).
- Secure password storage with bcrypt hashing.
- Role-based access control.

---

## Literature Review (15%)

### General
Urban mobility is rapidly transitioning toward sustainable transportation methods, with electric vehicle (EV) sharing systems playing a critical role in alleviating urban congestion and reducing environmental impact.

### GPS
Electric vehicle (EV) tracking and rental systems rely on the integration of GPS/GNSS positioning and Internet of Things (IoT) connectivity.

---

## Background Theory and Design Methodology (15%)

### Hardware Development
- Arduino UNO R4 WiFi as the core microcontroller.
- Integration of AT6668 GPS module for real-time location updates.

### Backend Implementation
- Server Infrastructure: Hosted on IONOS Cloud platform.
- Database: MariaDB with PHP API interface.
- Authentication System: Secure user management with bcrypt hashing.

### Front-End Visualization
- Map Interface: Leaflet.js with OpenStreetMap.
- Reservation Management: Users can view and manage reservations.
- Trip Playback: Animated playback of trip history with speed control.

---

## Project Technical Results and Interpretation (30%)

### Sensing Layer
- GPS module: AT6668.
- GPS data parsed using TinyGPSPlus and formatted in JSON.

### Network Layer
- WiFi-enabled Arduino UNO R4 transmits GPS data using HTTP POST requests.

### Service Layer
- RESTful API providing core functionalities:
  - Upload GPS location data
  - Submit a booking request
  - User login and registration

### Interface Layer
- Real-time map display with Leaflet.js.
- User interaction for booking and vehicle management.

---

## Performance Evaluation

- Average GPS accuracy: 3.34 meters.
- Average system latency: 6.48 seconds.
- GPS data transmission to server: HTTP POST (Arduino to IONOS Cloud).
- Latency exceeded target (5s) due to server load and network conditions.

---

## Achievements with Respect to the Project Aims and Objectives (5%)

1. Hardware integration successfully combined the AT6668 GPS module with the Arduino UNO R4 WiFi.
2. Software implementation delivered a cloud-hosted backend with a RESTful API and MariaDB database.

---

## Summary and Conclusions (5%)

- The system achieved an average GPS accuracy of 3.34 meters.
- Response latency was 6.48 seconds, exceeding the target.
- System design follows a four-layer IoT model (Sensing, Networking, Service, Interface).

---

## Future Works

1. Indoor Navigation Enhancement: Leveraging Wi-Fi for accurate indoor tracking.
2. System Performance Optimization: Implementing edge computing and load balancing.
3. Security Enhancement: Adopting multi-factor authentication and end-to-end encryption.

---

## References
1. M. Moumen, M. Krarti, and M. Baali, “Real-time GPS Tracking System for IoT-Enabled Connected Vehicles,” Int. J. Electr. Comput. Eng., vol. 13, no. 2, pp. 123–134, 2023.
2. S. S. Auti and N. Hulle, “Position Tracking and Path Guidance for Alzheimer's Patient by Using Shoes,” 2014.
3. J. Jana et al., “Design and Implementation of IoT-Based System for Tracking and Monitoring of Suspected COVID-19 Patients,” in Advances in VLSI and Embedded Systems, Springer, 2023.
4. N. T. Morallo, “Vehicle Tracker System Design Based on GSM and GPS Interface Using Arduino as Platform,” Indones. J. Electr. Eng. Comput. Sci., 2021.
5. O. U. Nwankwo, “IoT-Assisted Intelligent Vehicle Tracking System using Cloud Computing,” in 2022 International Conference on Information and Communication Technology Convergence (ICTC), 2022.
