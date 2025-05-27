<?php
class Database
{
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    private $error;

    public function __construct()
    {
        // Get database settings from config file
        $config_file = dirname(__DIR__) . '/config/config.php';
        if (!file_exists($config_file)) {
            die(json_encode([
                'error' => 'Config file not found',
                'path' => $config_file
            ]));
        }
        
        // Use include to get config, avoid output interference
        ob_start();
        $config = include $config_file;
        ob_end_clean();
        
        if (!is_array($config)) {
            die(json_encode([
                'error' => 'Config file format error',
                'detail' => 'Config file must return an array',
                'type' => gettype($config)
            ]));
        }
        
        if (!isset($config['db'])) {
            die(json_encode([
                'error' => 'Database config missing',
                'detail' => 'Could not find database config item',
                'config' => print_r($config, true)
            ]));
        }

        $this->host = $config['db']['host'] ?? 'localhost';
        $this->dbname = $config['db']['database'] ?? '';
        $this->username = $config['db']['username'] ?? '';
        $this->password = $config['db']['password'] ?? '';
        $this->charset = $config['db']['charset'] ?? 'utf8';

        // Use host connection instead of socket, no charset parameter
        $dsn = "mysql:host={$this->host};dbname={$this->dbname}";

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set charset
            $this->pdo->exec("SET NAMES {$this->charset}");

            // Set timezone
            date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die(json_encode([
                'error' => 'Database connection failed',
                'message' => $this->error
            ]));
        }
    }

    // Get PDO connection instance
    public function getConnection()
    {
        return $this->pdo;
    }

    // Generic query method with optional parameters
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die(json_encode([
                'error' => 'Database query error',
                'message' => $e->getMessage()
            ]));
        }
    }

    // Get all results as associative array
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get one row as associative array
    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    // Execute insert/update/delete and return affected rows
    public function execute($sql, $params = [])
    {
        return $this->query($sql, $params)->rowCount();
    }

    // Get last inserted ID
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    // Get vehicle status
    public function getVehicleStatus($vehicle_id)
    {
        try {
            // Get latest status from Locations table
            $vehicle = $this->fetchOne(
                "SELECT 
                    id, 
                    CASE WHEN status = 'available' THEN 1 ELSE 0 END as availability,
                    battery_level,
                    ST_X(location) as longitude,
                    ST_Y(location) as latitude,
                    status
                FROM Locations 
                WHERE id = ? 
                ORDER BY timestamp DESC 
                LIMIT 1", 
                [$vehicle_id]
            );
            
            return $vehicle;
        } catch (Exception $e) {
            // Log error and rethrow
            error_log("Error getting vehicle status: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Get user's active reservation
    public function getUserActiveReservation($user_id)
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
             WHERE user_id = ? 
             AND status = 'reserved' 
             AND (expiry_time IS NULL OR expiry_time > NOW())",
            [$user_id]
        );
    }
    
    // Create reservation
    public function createReservation($user_id, $vehicle_id, $start_time, $end_time, $expiry_time)
    {
        try {
            // Start transaction
            $this->getConnection()->beginTransaction();
            
            // Insert reservation record
            $this->execute(
                "INSERT INTO booking (user_id, vehicle_id, start_date, end_date, expiry_time, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, 'reserved', NOW())",
                [
                    $user_id, 
                    $vehicle_id, 
                    $start_time,
                    $end_time,
                    $expiry_time
                ]
            );
            
            $booking_id = $this->getConnection()->lastInsertId();
            
            // Update vehicle status - create new Locations record instead of updating
            // Change to 'in_use' to match Locations table status enum
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'in_use', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // Commit transaction
            $this->getConnection()->commit();
            
            return $booking_id;
        } catch (Exception $e) {
            // Rollback transaction
            $this->getConnection()->rollBack();
            error_log("Error creating reservation: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Cancel reservation
    public function cancelReservation($booking_id, $vehicle_id)
    {
        try {
            // Start transaction
            $this->getConnection()->beginTransaction();
            
            // Update reservation status
            $this->execute(
                "UPDATE booking SET status = 'cancelled' WHERE id = ?",
                [$booking_id]
            );
            
            // Update vehicle status - create new Locations record instead of updating
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'available', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // Commit transaction
            $this->getConnection()->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction
            $this->getConnection()->rollBack();
            error_log("Error cancelling reservation: " . $e->getMessage());
            throw $e;
        }
    }

    // Get latest vehicle location data
    public function getLatestLocations() 
    {
        try {
            $locations = $this->fetchAll(
                "SELECT 
                    id, 
                    speed_mph,
                    timestamp,
                    status,
                    ST_X(location) as longitude, 
                    ST_Y(location) as latitude,
                    battery_level
                FROM Locations l1
                WHERE timestamp = (
                    SELECT MAX(timestamp) 
                    FROM Locations l2 
                    WHERE l2.id = l1.id
                )"
            );
            
            return $locations;
        } catch (Exception $e) {
            error_log("Error getting latest locations: " . $e->getMessage());
            throw $e;
        }
    }

    // Get vehicle booking time conflicts
    public function getBookingConflicts($vehicle_id, $start_time, $end_time) 
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
            WHERE vehicle_id = ? AND (status = 'reserved')
            AND ((start_date <= ? AND end_date > ?) 
                OR (start_date < ? AND end_date >= ?))",
            [
                $vehicle_id, 
                $start_time, 
                $start_time,
                $end_time,
                $end_time
            ]
        );
    }
    
    // Get available time slots
    public function getAvailableTimeSlots($vehicle_id, $date)
    {
        try {
            // Get booked time slots
            $bookedSlots = $this->fetchAll(
                "SELECT start_date, end_date FROM booking 
                WHERE vehicle_id = ? AND DATE(start_date) = ? AND status != 'cancelled'",
                [$vehicle_id, $date]
            );
            
            // Generate time slots
            $timeSlots = [];
            $startHour = 9; // Start from 9 AM
            $endHour = 21;  // End at 9 PM
            
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                // Check full and half hours
                $slotTimes = [
                    ['hour' => $hour, 'minute' => 0],
                    ['hour' => $hour, 'minute' => 30]
                ];
                
                foreach ($slotTimes as $slotTime) {
                    $slotDateTime = new DateTime($date);
                    $slotDateTime->setTime($slotTime['hour'], $slotTime['minute']);
                    
                    // If today, skip past times
                    $now = new DateTime();
                    if ($date === $now->format('Y-m-d') && $slotDateTime <= $now) {
                        continue;
                    }
                    
                    // Check for conflicts
                    $hasConflict = false;
                    foreach ($bookedSlots as $bookedSlot) {
                        $startDate = new DateTime($bookedSlot['start_date']);
                        $endDate = new DateTime($bookedSlot['end_date']);
                        
                        if ($slotDateTime >= $startDate && $slotDateTime < $endDate) {
                            $hasConflict = true;
                            break;
                        }
                    }
                    
                    $timeSlots[] = [
                        'start' => $slotDateTime->format('H:i'),
                        'end' => $slotDateTime->modify('+30 minutes')->format('H:i'),
                        'available' => !$hasConflict,
                        'start_value' => $date . ' ' . $slotTime['hour'] . ':' . str_pad($slotTime['minute'], 2, '0', STR_PAD_LEFT) . ':00',
                        'end_value' => $date . ' ' . ($slotTime['minute'] === 30 ? ($slotTime['hour'] + 1) : $slotTime['hour']) . ':' . ($slotTime['minute'] === 30 ? '00' : '30') . ':00'
                    ];
                }
            }
            
            return [
                'timeSlots' => $timeSlots,
                'bookedSlots' => $bookedSlots
            ];
        } catch (Exception $e) {
            error_log("Error getting available time slots: " . $e->getMessage());
            throw $e;
        }
    }

    // Get user reservation history
    public function getUserReservationHistory($user_id, $limit = 20)
    {
        // Ensure $limit is an integer to prevent SQL injection
        $limit = (int)$limit;
        
        // Use integer value directly in SQL instead of parameter binding
        return $this->fetchAll(
            "SELECT 
                b.id, 
                b.vehicle_id, 
                b.start_date, 
                b.end_date, 
                b.expiry_time, 
                b.status, 
                b.created_at,
                l.battery_level,
                CONCAT(ST_Y(l.location), ',', ST_X(l.location)) as location,
                l.status as vehicle_status
             FROM booking b
             JOIN Locations l ON b.vehicle_id = l.id
             WHERE b.user_id = ?
             AND l.timestamp = (SELECT MAX(timestamp) FROM Locations WHERE id = b.vehicle_id)
             ORDER BY b.start_date DESC
             LIMIT $limit",
            [$user_id]  // Bind only user ID parameter
        );
    }

    // Start vehicle usage
    public function startVehicleOrder($booking_id, $vehicle_id)
    {
        try {
            // Start transaction
            $this->getConnection()->beginTransaction();
            
            // Update reservation status
            $this->execute(
                "UPDATE booking SET status = 'in_progress' WHERE id = ? AND status = 'reserved'",
                [$booking_id]
            );
            
            // Ensure Locations record remains in_use (no change needed)
            // Locations table status remains in_use
            
            // Commit transaction
            $this->getConnection()->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction
            $this->getConnection()->rollBack();
            error_log("Error starting vehicle usage: " . $e->getMessage());
            throw $e;
        }
    }

    // Complete vehicle usage
    public function completeVehicleOrder($booking_id, $vehicle_id)
    {
        try {
            // Start transaction
            $this->getConnection()->beginTransaction();
            
            // Get reservation info to calculate cost
            $booking = $this->fetchOne(
                "SELECT start_time_actual FROM booking WHERE id = ?", 
                [$booking_id]
            );
            
            if (!$booking) {
                throw new Exception("Reservation record not found");
            }
            
            $startTime = new DateTime($booking['start_time_actual']);
            $endTime = new DateTime();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            $hours = $duration / 3600;
            $totalCost = $hours * 10; // 10 units per hour
            
            // Update reservation status
            $this->execute(
                "UPDATE booking 
                SET status = 'completed', 
                    end_time_actual = NOW(),
                    duration_seconds = ?,
                    total_cost = ?
                WHERE id = ?",
                [$duration, $totalCost, $booking_id]
            );
            
            // Update vehicle status - create new Locations record
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'available', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // Commit transaction
            $this->getConnection()->commit();
            
            return [
                'success' => true,
                'duration' => $duration,
                'total_cost' => $totalCost
            ];
        } catch (Exception $e) {
            // Rollback transaction
            $this->getConnection()->rollBack();
            error_log("Error completing vehicle usage: " . $e->getMessage());
            throw $e;
        }
    }

    // Get user's active order
    public function getUserActiveOrder($user_id)
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
             WHERE user_id = ? 
             AND status = 'in_progress'",
            [$user_id]
        );
    }

    // Get reservation details
    public function getBookingDetails($booking_id)
    {
        return $this->fetchOne(
            "SELECT 
                b.*, 
                CONCAT('Electric Scooter #', b.vehicle_id) as vehicle_name
             FROM booking b 
             WHERE b.id = ?",
            [$booking_id]
        );
    }
    
    // Record vehicle maintenance
    public function recordMaintenance($vehicle_id, $description, $maintenance_date)
    {
        try {
            // Record maintenance
            $this->execute(
                "INSERT INTO maintenance (vehicle_id, description, maintenance_date, created_at) 
                VALUES (?, ?, ?, NOW())",
                [$vehicle_id, $description, $maintenance_date]
            );
            
            // Update vehicle status
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                SELECT id, 'maintenance', location, battery_level, speed_mph 
                FROM Locations 
                WHERE id = ? 
                ORDER BY timestamp DESC 
                LIMIT 1", 
                [$vehicle_id]
            );
            
            return $this->getLastInsertId();
        } catch (Exception $e) {
            error_log("Error recording maintenance: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Complete maintenance
    public function completeMaintenance($maintenance_id, $vehicle_id)
    {
        try {
            // Update maintenance record
            $this->execute(
                "UPDATE maintenance SET completed_at = NOW() WHERE id = ?",
                [$maintenance_id]
            );
            
            // Update vehicle status
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                SELECT id, 'available', location, battery_level, speed_mph 
                FROM Locations 
                WHERE id = ? 
                ORDER BY timestamp DESC 
                LIMIT 1", 
                [$vehicle_id]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error completing maintenance: " . $e->getMessage());
            throw $e;
        }
    }
    // Get single vehicle information (if different implementation from getVehicleStatus is needed)
    public function getVehicleById($vehicle_id)
    {
        return $this->getVehicleStatus($vehicle_id);
    }

    // Get all vehicles
    public function getAllVehicles()
    {
        return $this->getLatestLocations();
    }

    // Add vehicle (admin function)
    public function addVehicle($data)
    {
        try {
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph, timestamp) 
                 VALUES (?, 'available', POINT(?, ?), ?, 0, NOW())",
                [
                    $data['id'] ?? null,
                    $data['longitude'] ?? 0,
                    $data['latitude'] ?? 0,
                    $data['battery_level'] ?? 100
                ]
            );
            return $this->getLastInsertId();
        } catch (Exception $e) {
            error_log("Error adding vehicle: " . $e->getMessage());
            throw $e;
        }
    }

    // Update vehicle information (admin function)
    public function updateVehicle($vehicle_id, $data)
    {
        try {
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph, timestamp) 
                 VALUES (?, ?, POINT(?, ?), ?, ?, NOW())",
                [
                    $vehicle_id,
                    $data['status'] ?? 'available',
                    $data['longitude'] ?? 0,
                    $data['latitude'] ?? 0,
                    $data['battery_level'] ?? 100,
                    $data['speed_mph'] ?? 0
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error updating vehicle: " . $e->getMessage());
            throw $e;
        }
    }

    // Delete vehicle (admin function)
    public function deleteVehicle($vehicle_id)
    {
        try {
            // Do not actually delete, but mark as maintenance status
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph, timestamp) 
                 SELECT id, 'maintenance', location, battery_level, speed_mph, NOW()
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1",
                [$vehicle_id]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error deleting vehicle: " . $e->getMessage());
            throw $e;
        }
    }

    // Get vehicle locations
    public function getVehicleLocations()
    {
        return $this->getLatestLocations();
    }

    // Update vehicle location
    public function updateVehicleLocation($data)
    {
        try {
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph, timestamp) 
                 VALUES (?, ?, POINT(?, ?), ?, ?, NOW())",
                [
                    $data['vehicle_id'] ?? $data['id'],
                    $data['status'] ?? 'available',
                    $data['longitude'] ?? 0,
                    $data['latitude'] ?? 0,
                    $data['battery_level'] ?? 100,
                    $data['speed_mph'] ?? 0
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error updating vehicle location: " . $e->getMessage());
            throw $e;
        }
    }

    // Get all reservations (admin function)
    public function getAllReservations()
    {
        try {
            return $this->fetchAll(
                "SELECT 
                    b.id, 
                    b.user_id,
                    b.vehicle_id, 
                    b.start_date, 
                    b.end_date, 
                    b.status, 
                    b.created_at,
                    u.username,
                    CONCAT('Electric Scooter #', b.vehicle_id) as vehicle_name
                FROM booking b
                JOIN users u ON b.user_id = u.id
                ORDER BY b.created_at DESC"
            );
        } catch (Exception $e) {
            error_log("Error getting all reservations: " . $e->getMessage());
            throw $e;
        }
    }

    // Get reservation information (by ID)
    public function getReservationById($booking_id)
    {
        return $this->fetchOne(
            "SELECT 
                b.*, 
                u.username,
                CONCAT('Electric Scooter #', b.vehicle_id) as vehicle_name
             FROM booking b 
             JOIN users u ON b.user_id = u.id
             WHERE b.id = ?",
            [$booking_id]
        );
    }

    // Update reservation information
    public function updateReservation($booking_id, $data)
    {
        try {
            $updateFields = [];
            $params = [];
            
            if (isset($data['start_date'])) {
                $updateFields[] = "start_date = ?";
                $params[] = $data['start_date'];
            }
            
            if (isset($data['end_date'])) {
                $updateFields[] = "end_date = ?";
                $params[] = $data['end_date'];
            }
            
            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $params[] = $booking_id;
            
            $this->execute(
                "UPDATE booking SET " . implode(', ', $updateFields) . " WHERE id = ?",
                $params
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error updating reservation: " . $e->getMessage());
            throw $e;
        }
    }

    // Get user information (by ID)
    public function getUserById($user_id)
    {
        return $this->fetchOne(
            "SELECT id, username, email, created_at, role FROM users WHERE id = ?",
            [$user_id]
        );
    }

    // Get all users (admin function)
    public function getAllUsers()
    {
        return $this->fetchAll(
            "SELECT id, username, email, created_at, role FROM users ORDER BY created_at DESC"
        );
    }

    // Update user information
    public function updateUser($user_id, $data)
    {
        try {
            $updateFields = [];
            $params = [];
            
            if (isset($data['username'])) {
                $updateFields[] = "username = ?";
                $params[] = $data['username'];
            }
            
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (isset($data['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $params[] = $user_id;
            
            $this->execute(
                "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?",
                $params
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            throw $e;
        }
    }
} 