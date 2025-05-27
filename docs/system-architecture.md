# System Architecture

## Overview

The Electric Vehicle (EV) Rental System employs a multi-tier architecture following the IoT reference model with four main layers:

1. **Sensing Layer**: Vehicle-mounted hardware for data collection
2. **Network Layer**: Communication protocols and interfaces
3. **Service Layer**: Server-side APIs and business logic
4. **Interface Layer**: User-facing applications and admin tools

This document outlines the architecture, components, data flow, and database schema within the system.

## System Components

### Hardware Components

#### Tracking Device

- **Microcontroller**: Arduino UNO R4 WiFi
  - Core functionality: Process GPS data and transmit to server
  - WiFi connectivity: IEEE 802.11 b/g/n at 2.4 GHz
  - Built-in LED Matrix: Visual status indication

- **GPS Module**: AT6668 GPS SMA
  - Positioning accuracy: ≤5m
  - Data update frequency: 1Hz (configurable)
  - Power consumption: 45mA (acquisition), 35mA (tracking)

#### Infrastructure

- **Server**: Example: IONOS Cloud VPS (or any compatible hosting)
  - PHP runtime environment
  - MariaDB/MySQL database server
  - Apache/Nginx web server

### Software Components

#### Backend Services

- **API Layer**
  - RESTful API endpoints for client-server communication (`backend/api/api.php`, `backend/api/store.php`)
  - Session-based authentication (`backend/core/auth.php`)
  - Rate limiting and request validation (can be implemented via web server or application logic)

- **Data Management**
  - Database access abstraction layer (`backend/core/Database.php`)
  - Real-time data processing for location updates

- **Business Logic**
  - Booking management
  - User authentication and authorization
  - Vehicle status monitoring
  - Utility functions (`backend/includes/utils.php`, `backend/includes/helpers.php`)

#### Frontend Applications

- **Web Application** (`frontend/`)
  - Responsive design (HTML, CSS in `frontend/public/css/`)
  - Real-time map interface (Leaflet.js, JavaScript in `frontend/public/js/`)
  - Booking and reservation system (PHP views in `frontend/views/`)

- **Admin Dashboard** (Integrated within the web application, access controlled by user role)
  - User management (via API if extended)
  - Fleet monitoring
  - Maintenance scheduling and logging

## Data Flow

### 1. Location Data Collection (Arduino to Server)

```
[GPS Module] --(Serial)--> [Arduino Microcontroller] --(JSON over HTTP POST via WiFi)--> [Server API Endpoint: /backend/api/store.php]
```

1. GPS module collects position data (latitude, longitude, speed, time).
2. Arduino (`ev_tracker.ino`) processes the data, includes device ID and battery level, and formats it as JSON.
3. Arduino sends HTTP POST requests to the `/backend/api/store.php` endpoint on the server.
4. `store.php` validates the data and inserts it into the `Locations` table in the database.

### 2. User Booking Flow (Web Interface to Server)

```
[User via Web Browser] --(HTTP Requests)--> [Frontend PHP Views/JS] --(API Calls to /backend/api/api.php)--> [Backend Logic] --(Database Operations)--> [Database]
```

1. User logs in (authentication handled by `backend/core/auth.php` and `api.php`).
2. User views available vehicles on the map (data fetched via `api.php` which queries `Locations` table).
3. User selects a vehicle and submits a booking request (e.g., start/end time).
4. Frontend JavaScript (`reservation.js`, `data-service.js`) sends the request to `api.php` (e.g., `reservations` endpoint).
5. `api.php` processes the booking request (checks availability, conflicts using `Database.php` methods) and updates the `booking` table.
6. Vehicle status in `Locations` table might be updated or a new record reflecting the reservation could be logged (current logic inserts a new `in_use` record upon reservation).
7. User receives confirmation on the web interface.

### 3. Vehicle Usage Flow

```
[User Actions on Web Interface] --> [API Calls to /backend/api/api.php] --> [Backend Logic & Database Updates]
```

1. **Start Trip**: User initiates trip start (e.g., via a button in `my_reservations.php`).
  - Request sent to `api.php` (e.g., `trips` endpoint, POST to start).
  - Backend updates the `booking` status to `in_progress` and vehicle status in `Locations` to `in_use` (if not already).
2. **Real-time Tracking**: While the trip is active, the Arduino device continues to send location updates as per Data Flow #1.
3. **End Trip**: User ends the trip.
  - Request sent to `api.php` (e.g., `trips` endpoint, PUT to end).
  - Backend updates `booking` status to `completed`, calculates duration/cost (if applicable), and updates vehicle status in `Locations` to `available`.

## Database Schema

### Tables Overview

The EV Rental System database consists of the following main tables:

1. **`users`** - Stores user account information.
2. **`booking`** - Manages vehicle reservation records.
3. **`Locations`** - Tracks real-time vehicle location data. (Note: table name is capitalized)
4. **`maintenance`** - Records vehicle maintenance history. (Note: table name changed from `maintenances` to `maintenance` in some API logic, schema shows `maintenances`)
5. **`latest_scooter_data`** - A MySQL View that provides the most recent location data for each vehicle (based on `Locations` table).

### Table Structures

#### `users`

Stores information about system users, including authentication data.

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);
```

#### `booking`

Records all booking/reservation information.

```sql
CREATE TABLE `booking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL, -- Corresponds to 'id' in Locations table
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `start_time_actual` datetime DEFAULT NULL,
  `end_time_actual` datetime DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `expiry_time` datetime DEFAULT NULL, -- For reservations that expire if not started
  `status` enum('reserved','in_progress','completed','cancelled','expired') NOT NULL DEFAULT 'reserved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `vehicle_id` (`vehicle_id`)
  -- Consider adding FOREIGN KEY constraints if your MySQL version/config supports them well with the application logic.
  -- FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  -- FOREIGN KEY (`vehicle_id`) REFERENCES `Locations`(`id`) -- This might be complex if Locations.id is not strictly unique for a vehicle over time.
  -- A separate 'vehicles' table is often recommended.
);
```

#### `Locations`

Records real-time GPS data for all vehicles. Each entry is a snapshot. The `id` here refers to the `device_id` or `vehicle_id`.

```sql
CREATE TABLE `Locations` (
  `entry_id` int(11) NOT NULL AUTO_INCREMENT, -- Renamed from previous doc to avoid confusion with vehicle id
  `id` int(11) NOT NULL, -- This is the vehicle_id or device_id
  `speed_mph` float NOT NULL DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('available','in_use', 'maintenance', 'unknown') NOT NULL DEFAULT 'unknown',
  `location` point NOT NULL, -- Spatial data type for latitude/longitude
  `battery_level` int(11) DEFAULT 100,
  PRIMARY KEY (`entry_id`),
  KEY `id_timestamp` (`id`,`timestamp` DESC) -- Index for fetching latest location for a vehicle
  -- SPATIAL INDEX(`location`) -- If performing spatial queries beyond simple point storage
);
```

#### `maintenance`

Records maintenance history for vehicles.

```sql
CREATE TABLE `maintenance` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL, -- Corresponds to 'id' in Locations table
  `description` text NOT NULL,
  `maintenance_date` date NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`)
);
```

#### `latest_scooter_data` (View)

A MySQL View to get the most recent location record for each vehicle ID.

```sql
CREATE OR REPLACE VIEW `latest_scooter_data` AS
SELECT
    l1.`id` AS `id`, -- This is the vehicle_id
    ST_X(l1.`location`) AS `longitude`,
    ST_Y(l1.`location`) AS `latitude`,
    l1.`speed_mph`,
    l1.`timestamp`,
    l1.`status`,
    l1.`battery_level`
FROM
    `Locations` l1
INNER JOIN (
    SELECT
        `id`, -- vehicle_id
        MAX(`timestamp`) AS `latest_timestamp`
    FROM
        `Locations`
    GROUP BY
        `id`
) l2 ON l1.`id` = l2.`id` AND l1.`timestamp` = l2.`latest_timestamp`;
```

### Database Design Notes & Recommended Improvements:

1. **Vehicles Table**: Consider adding a dedicated `vehicles` table (e.g., `vehicle_id (PK)`, `device_serial_number`, `model`, `date_commissioned`, `current_status_enum`). This would provide a single source of truth for vehicle entities, and `Locations.id` and `booking.vehicle_id` would be foreign keys to this table.
2. **Consistent Naming**: Standardize table and column names (e.g., all snake_case, all singular or plural for tables). The schema above has some inconsistencies from the original documentation.
3. **Foreign Key Constraints**: Implement foreign key constraints to ensure referential integrity once a stable `vehicles` table exists.
4. **Indexing**: Review and add indexes to columns frequently used in `WHERE` clauses, `JOIN` conditions, and `ORDER BY` clauses (some basic indexes are included above).
5. **Data Types**: Ensure appropriate data types are used for all columns (e.g., `DECIMAL` for currency, correct `ENUM` values).
6. **Timezones**: Store all timestamps in UTC and handle timezone conversions at the application or presentation layer.

## Security Measures

1. **Authentication**:
  - Secure password hashing (bcrypt is used in `backend/core/auth.php`).
  - Session-based authentication for the web interface.
  - Consider API key or token-based authentication for device-to-server or third-party API communication if extended.

2. **Data Security**:
  - Use HTTPS/TLS for all communication (requires server configuration).
  - Perform input validation and sanitization on all user-supplied data (PHP `filter_input`, prepared statements).
  - Use prepared statements for all database queries to prevent SQL injection (as done in `Database.php`).

3. **Infrastructure Security**:
  - Keep server software (OS, web server, PHP, database) updated with security patches.
  - Implement firewall rules to restrict access to necessary ports.
  - Consider rate limiting on API endpoints to prevent abuse.
  - Regularly back up the database and application files.

## Performance Considerations

1. **Database Optimization**:
  - Efficient indexing (as noted in Database Schema section).
  - Optimize queries, especially those for fetching locations and checking availability.
  - Connection pooling if traffic becomes very high (typically handled by PHP-FPM and web server configuration to some extent).

2. **Scalability** (For larger deployments):
  - Web server load balancing.
  - Read replicas for the database.
  - Consider database sharding if the dataset grows extremely large.
  - Caching strategies (e.g., for frequently accessed static data or API responses).

3. **Real-time Performance Targets** (Examples, adjust as needed):
  - API response time: Aim for < 500ms for most common requests.
  - GPS data transmission interval from Arduino: Currently 60 seconds, adjustable in `ev_tracker.ino`.
  - Map refresh rate on client-side: Configurable in JavaScript, balance between real-time feel and server load.

## Future Enhancements (Examples from original docs)

1. **Indoor Navigation Enhancement**: WiFi-based positioning, Bluetooth beacons.
2. **System Performance Optimization**: Edge computing, advanced load balancing.
3. **Security Enhancement**: Multi-factor authentication, end-to-end encryption for certain data, advanced intrusion detection. 