# System Architecture

## Overview

The Electric Vehicle (EV) Rental System employs a multi-tier architecture following the IoT reference model with four main layers:

1. **Sensing Layer**: Vehicle-mounted hardware for data collection
2. **Network Layer**: Communication protocols and interfaces
3. **Service Layer**: Server-side APIs and business logic
4. **Interface Layer**: User-facing applications and admin tools

This document outlines the architecture, components, and data flow within the system.

## System Components

### Hardware Components

#### Tracking Device

- **Microcontroller**: Arduino UNO R4 WiFi
  - Core functionality: Process GPS data and transmit to server
  - WiFi connectivity: IEEE 802.11 b/g/n at 2.4 GHz

- **GPS Module**: AT6668 GPS SMA
  - Positioning accuracy: ≤5m
  - Data update frequency: 1Hz (configurable)
  - Power consumption: 45mA (acquisition), 35mA (tracking)

#### Infrastructure

- **Server**: IONOS Cloud VPS
  - PHP runtime environment
  - MariaDB database server
  - Apache web server

### Software Components

#### Backend Services

- **API Layer**
  - RESTful API endpoints for client-server communication
  - JWT/Session-based authentication
  - Rate limiting and request validation

- **Data Management**
  - Database access abstraction layer
  - Real-time data processing
  - Data aggregation and analytics

- **Business Logic**
  - Booking management
  - User authentication and authorization
  - Vehicle status monitoring

#### Frontend Applications

- **Web Application**
  - Responsive design (mobile and desktop)
  - Real-time map interface (Leaflet.js)
  - Booking and reservation system

- **Admin Dashboard**
  - User management
  - Fleet monitoring
  - Maintenance scheduling

## Data Flow

### 1. Location Data Collection

```
[GPS Module] → [Arduino Microcontroller] → [WiFi] → [Server API]
```

1. GPS module collects position data (lat/long, speed, time)
2. Arduino processes the data and formats it as JSON
3. Arduino sends HTTP POST requests to the server API endpoint
4. Server validates and stores the location data in the database

### 2. User Booking Flow

```
[User] → [Web Interface] → [API] → [Database] → [Vehicle Status Update]
```

1. User logs in and views available vehicles on map
2. User selects a vehicle and creates a booking
3. API processes the booking request and updates database
4. Vehicle status is updated to "reserved"
5. User receives confirmation and can track their reservation

### 3. Vehicle Usage Flow

```
[User] → [Start Trip] → [Vehicle Status Change] → [Real-time Tracking] → [End Trip]
```

1. User starts their trip, changing vehicle status to "in_use"
2. Location data continues to be transmitted during trip
3. Web interface displays real-time tracking information
4. User ends trip, changing vehicle status to "available"
5. System calculates trip metrics and updates records

## Database Schema

The system uses a relational database with the following core tables:

- **users**: User account information
- **booking**: Reservation records
- **locations**: Real-time GPS data
- **maintenances**: Maintenance records

For detailed schema information, see the [Database Schema Documentation](database-schema.md).

## Security Measures

1. **Authentication**:
   - Secure password hashing (bcrypt)
   - Session-based authentication for web interface
   - API key authentication for device communication

2. **Data Security**:
   - HTTPS/TLS encryption for all communication
   - Input validation and sanitization
   - Prepared statements for database queries

3. **Infrastructure Security**:
   - Regular security updates
   - Firewall configuration
   - Rate limiting to prevent abuse

## Performance Considerations

1. **Database Optimization**:
   - Indexing on frequently queried columns
   - Query optimization for location data

2. **Scalability**:
   - Load balancing for high traffic
   - Database sharding for large fleets
   - Edge computing for location data processing

3. **Real-time Performance**:
   - Maximum API response time: 500ms
   - GPS data transmission interval: 5s
   - Map refresh rate: 3s

## Future Enhancements

1. **Indoor Navigation Enhancement**: 
   - WiFi-based positioning for indoor tracking
   - Bluetooth beacons for precise indoor location

2. **System Performance Optimization**: 
   - Implementation of edge computing
   - Load balancing for high-traffic periods
   - Database optimization for large-scale deployment

3. **Security Enhancement**: 
   - Multi-factor authentication
   - End-to-end encryption for sensitive data
   - Advanced intrusion detection systems 