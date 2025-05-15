# Database Architecture Design Documentation

## Table Structure Descriptions

### users Table
User information table, stores system user data.

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active'
);
```

### booking Table
Reservation records table, records all vehicle booking information.

```sql
CREATE TABLE booking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('reserved','in_progress','completed','cancelled') NOT NULL,
    expiry_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status_dates (status, start_date, end_date),
    INDEX idx_vehicle (vehicle_id)
);
```

### Locations Table
Vehicle location information table, records real-time location data.

```sql
CREATE TABLE Locations (
    id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    location POINT NOT NULL SRID 4326,
    battery_level INT NOT NULL,
    speed_mph FLOAT,
    course FLOAT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SPATIAL INDEX idx_location (location),
    INDEX idx_timestamp (timestamp),
    INDEX idx_status (status)
);
```

### maintenances Table
Maintenance records table, records vehicle maintenance history.

```sql
CREATE TABLE maintenances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    description TEXT NOT NULL,
    maintenance_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_vehicle_date (vehicle_id, maintenance_date)
);
```

## View Definitions

### latest_scooter_data
Gets the latest location information for each vehicle.

```sql
CREATE VIEW latest_scooter_data AS
SELECT 
    l.id,
    l.status,
    ST_X(l.location) as longitude,
    ST_Y(l.location) as latitude,
    l.battery_level,
    l.speed_mph,
    l.timestamp
FROM Locations l
INNER JOIN (
    SELECT id, MAX(timestamp) as max_time
    FROM Locations
    GROUP BY id
) latest ON l.id = latest.id AND l.timestamp = latest.max_time;
```

## Indexing Strategy

1. Primary Key Indexes: All tables use auto-increment primary keys
2. Foreign Key Indexes: Booking and maintenance tables linked to users table
3. Composite Indexes: Optimized for common query scenarios
4. Spatial Indexes: Optimized for geographical location query performance

## Data Integrity Constraints

1. Username and email uniqueness constraints
2. Booking status enum restrictions
3. Battery level range check (0-100)
4. Maintenance records must be linked to valid users 