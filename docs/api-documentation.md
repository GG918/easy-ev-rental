# API Documentation

## Overview

This document outlines the RESTful API endpoints available in the Electric Vehicle Rental System. All API requests should be made to the base URL: `https://yourdomain.com/api.php`.

## Authentication

Most API endpoints require authentication. Authentication is performed using session-based authentication.

### Login

**Endpoint**: `?action=login`  
**Method**: POST  
**Description**: Authenticates a user and creates a session.

**Request Body**:
```json
{
    "username": "string",
    "password": "string"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Login successful",
    "user": {
        "id": 1,
        "username": "username"
    }
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Invalid username or password"
}
```

### Register

**Endpoint**: `?action=register`  
**Method**: POST  
**Description**: Registers a new user account.

**Request Body**:
```json
{
    "username": "string",
    "email": "string",
    "password": "string"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Registration successful"
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Username already exists"
}
```

### Logout

**Endpoint**: `?action=logout`  
**Method**: GET  
**Description**: Logs out the current user and destroys the session.

**Response**:
```json
{
    "success": true,
    "message": "Logout successful"
}
```

## Vehicle Location

### Get All Locations

**Endpoint**: `?action=get_locations`  
**Method**: GET  
**Description**: Retrieves the current location of all available vehicles.

**Response**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "longitude": -1.4667,
            "latitude": 53.3811,
            "status": "available",
            "battery_level": 75,
            "speed_mph": 0
        },
        {
            "id": 2,
            "longitude": -1.4729,
            "latitude": 53.3799,
            "status": "in_use",
            "battery_level": 68,
            "speed_mph": 12.5
        }
    ]
}
```

### Update Vehicle Location

**Endpoint**: `?action=update_location`  
**Method**: POST  
**Description**: Updates the location of a vehicle (typically used by the vehicle hardware).
**Authentication**: Requires API key authentication

**Request Body**:
```json
{
    "id": 1,
    "longitude": -1.4667,
    "latitude": 53.3811,
    "speed_mph": 5.2,
    "battery_level": 82,
    "status": "in_use"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Location updated successfully"
}
```

## Bookings/Reservations

### Create Booking

**Endpoint**: `?action=create_booking`  
**Method**: POST  
**Description**: Creates a new booking for a vehicle.
**Authentication**: Requires user login

**Request Body**:
```json
{
    "vehicle_id": 1,
    "start_date": "2023-11-15 14:30:00",
    "end_date": "2023-11-15 15:00:00"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Booking created successfully",
    "booking_id": 123
}
```

### Get User Bookings

**Endpoint**: `?action=get_user_bookings`  
**Method**: GET  
**Description**: Retrieves all bookings for the currently logged-in user.
**Authentication**: Requires user login

**Response**:
```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "vehicle_id": 1,
            "start_date": "2023-11-15 14:30:00",
            "end_date": "2023-11-15 15:00:00",
            "status": "reserved",
            "created_at": "2023-11-14 10:24:35"
        }
    ]
}
```

### Cancel Booking

**Endpoint**: `?action=cancel_booking`  
**Method**: POST  
**Description**: Cancels an existing booking.
**Authentication**: Requires user login

**Request Body**:
```json
{
    "booking_id": 123
}
```

**Response**:
```json
{
    "success": true,
    "message": "Booking cancelled successfully"
}
```

## Maintenance Records

### Get Maintenance Records

**Endpoint**: `?action=get_maintenance_records`  
**Method**: GET  
**Description**: Retrieves maintenance records for a specific vehicle.
**Authentication**: Requires admin login

**Parameters**:
- `vehicle_id` (optional): Filter by vehicle ID

**Response**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "vehicle_id": 5,
            "description": "Battery replacement",
            "maintenance_date": "2023-10-15",
            "created_at": "2023-10-15 09:30:22"
        }
    ]
}
```

### Create Maintenance Record

**Endpoint**: `?action=create_maintenance_record`  
**Method**: POST  
**Description**: Creates a new maintenance record.
**Authentication**: Requires admin login

**Request Body**:
```json
{
    "vehicle_id": 5,
    "description": "Battery replacement",
    "maintenance_date": "2023-10-15"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Maintenance record created successfully",
    "record_id": 1
}
```

## Location Tracking

### Store Vehicle Location (for trackers)

**Endpoint**: `/store.php`  
**Method**: POST  
**Description**: Receives and stores location data from vehicle tracking devices (Arduino).
**Authentication**: None (protected by device-specific identifiers)

**Request Body**:
```json
{
  "location": {
    "lat": 53.3811,
    "lng": -1.4701
  },
  "device_id": "1",
  "speed_mph": 12.5,
  "battery_level": 85,
  "status": "in_use"
}
```

**Parameters**:
- `location` (required): Object containing latitude and longitude
  - `lat` (required): Latitude in decimal degrees
  - `lng` (required): Longitude in decimal degrees
- `device_id` (optional): Unique identifier for the vehicle/device (defaults to 1)
- `speed_mph` (optional): Current speed in miles per hour (defaults to 0.0)
- `battery_level` (optional): Battery level percentage (defaults to 100)
- `status` (optional): Vehicle status (defaults to calculated based on speed)
- `timestamp` (optional): Time of data collection (defaults to server time)

**Response**:
```json
{
  "success": true,
  "message": "Location data stored successfully",
  "device_id": "1",
  "timestamp": "2023-11-15 14:30:00"
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Database error",
  "details": "Error message details"
}
```

**Notes**:
- Data from each device is logged in `debug/arduino_data.log` for troubleshooting
- Vehicle status is automatically determined based on speed if not provided
- The server timestamp is used if none is provided in the request

## Error Handling

All API endpoints follow a consistent error response format:

```json
{
    "success": false,
    "message": "Error message describing what went wrong",
    "error_code": 400
}
```

Common error codes:
- 400: Bad Request (invalid parameters)
- 401: Unauthorized (authentication required)
- 403: Forbidden (insufficient permissions)
- 404: Not Found (resource does not exist)
- 500: Internal Server Error (server-side issue)

## Rate Limiting

API requests are rate limited to prevent abuse:
- 100 requests per minute for authenticated users
- 20 requests per minute for unauthenticated requests

Exceeding these limits will result in a 429 (Too Many Requests) response code. 