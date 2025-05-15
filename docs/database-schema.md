# Database Schema Documentation

## Tables Overview

The EV Rental System database consists of the following main tables:

1. **users** - Stores user account information
2. **booking** - Manages vehicle reservation records
3. **locations** - Tracks real-time vehicle location data
4. **maintenances** - Records vehicle maintenance history
5. **latest_scooter_data** - A view that provides the most recent location data for each vehicle

## Tables Structure

### users

Stores information about system users, including authentication data.

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)
```

### booking

Records all booking/reservation information.

```sql
CREATE TABLE `booking` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `expiry_time` datetime DEFAULT NULL,
  `status` enum('reserved','in_progress','completed','cancelled') NOT NULL DEFAULT 'reserved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)
```

### locations

Records real-time GPS data for all vehicles.

```sql
CREATE TABLE `Locations` (
  `id` int(11) NOT NULL,
  `speed_mph` float NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('available','in_use') NOT NULL DEFAULT 'in_use',
  `location` point NOT NULL,
  `battery_level` int(11) NOT NULL DEFAULT 100
)
```

### maintenances

Records maintenance history for vehicles.

```sql
CREATE TABLE `maintenances` (
  `id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `description` text NOT NULL,
  `maintenance_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)
```

### latest_scooter_data (View)

A view that provides the most recent location data for each vehicle.

```sql
CREATE VIEW `latest_scooter_data` AS 
SELECT 
  `sd`.`id` AS `id`, 
  st_x(`sd`.`location`) AS `longitude`, 
  st_y(`sd`.`location`) AS `latitude`, 
  `sd`.`speed_mph` AS `speed_mph`, 
  `sd`.`timestamp` AS `timestamp`, 
  `sd`.`status` AS `status`, 
  `sd`.`battery_level` AS `battery_level` 
FROM 
  (`Locations` `sd` 
  JOIN (
    SELECT 
      `Locations`.`id` AS `id`,
      MAX(`Locations`.`timestamp`) AS `latest_timestamp` 
    FROM `Locations` 
    GROUP BY `Locations`.`id`
  ) `latest` 
  ON(
    `sd`.`id` = `latest`.`id` AND 
    `sd`.`timestamp` = `latest`.`latest_timestamp`
  )
)
```

## Recommended Improvements

1. **Consistent Naming**: Standardize table names to follow a consistent naming convention (e.g., all snake_case or all singular nouns).

2. **Foreign Key Constraints**: Add foreign key constraints between tables to ensure data integrity:
   - `booking.user_id` → `users.id`
   - `booking.vehicle_id` → Add a dedicated vehicles table
   - `maintenances.vehicle_id` → Add a dedicated vehicles table

3. **Indexing**: Add appropriate indexes for frequently queried columns:
   - Index on `Locations.timestamp` for faster range queries
   - Index on `booking.user_id` for faster user-specific queries

4. **Data Types**: Review and optimize data types for columns to ensure efficient storage. 