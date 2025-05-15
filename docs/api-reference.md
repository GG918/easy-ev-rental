# API Reference Documentation

## Authentication
All API requests require a valid user login status in the Session, otherwise a 401 error is returned.

## Standard Response Format
```json
{
    "success": true|false,
    "message": "Operation result description",
    "data": {} // Optional, specific data
}
```

## User Endpoints

### Login
- Path: `api.php?action=login`
- Method: POST
- Parameters:
```json
{
    "username": "username",
    "password": "password"
}
```
- Response:
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

### Register
- Path: `api.php?action=register`
- Method: POST
- Parameters:
```json
{
    "username": "username",
    "email": "email",
    "password": "password"
}
```

## Vehicle Endpoints

### Get Nearby Vehicles
- Path: `api.php?action=getNearbyScooters`
- Method: GET
- Parameters:
  - lat: latitude
  - lng: longitude
  - radius: search radius (meters)
- Response:
```json
{
    "success": true,
    "vehicles": [
        {
            "id": 1,
            "latitude": 53.3814,
            "longitude": -1.4778,
            "battery_level": 85,
            "status": "available",
            "distance": 120
        }
    ]
}
```

### Book a Vehicle
- Path: `api.php?action=reserveScooter`
- Method: POST
- Parameters:
```json
{
    "scooter_id": 1,
    "start_time": "2024-01-20 14:00:00", // Optional
    "end_time": "2024-01-20 14:30:00"    // Optional
}
```

### Start Trip
- Path: `api.php?action=startTrip`
- Method: POST
- Parameters:
```json
{
    "booking_id": 1,
    "scooter_id": 1
}
```

### End Trip
- Path: `api.php?action=endTrip`
- Method: POST
- Parameters:
```json
{
    "booking_id": 1,
    "scooter_id": 1
}
```

## Trip Tracking Endpoints

### Get Trip Track
- Path: `api.php?action=getTripTrack`
- Method: GET
- Parameters:
  - booking_id: Booking ID
- Response:
```json
{
    "success": true,
    "track": [
        {
            "latitude": 53.3814,
            "longitude": -1.4778,
            "speed": 15.5,
            "timestamp": "2024-01-20 14:15:30"
        }
    ]
}
```

## Error Codes

- 400: Bad request parameters
- 401: Unauthorized access
- 403: Insufficient permissions
- 404: Resource not found
- 409: Resource conflict (e.g., booking conflict)
- 500: Internal server error 