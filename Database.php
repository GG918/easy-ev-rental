<?php
class Database
{
    private $host     = "localhost";
    private $dbname   = "ev_rental_db";
    private $username = "gianni";
    private $password = "gianni111";
    private $charset  = "utf8mb4";
    private $pdo;
    private $error;

    public function __construct()
    {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            date_default_timezone_set('Europe/London');

        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die(json_encode([
                'error' => 'Database connection failed',
                'message' => $this->error
            ]));
        }
    }

    // Get the PDO connection instance
    public function getConnection()
    {
        return $this->pdo;
    }

    // General query method with optional parameters
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

    // Fetch all results as associative array
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch one row as associative array
    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    // Execute insert/update/delete and return affected rows
    public function execute($sql, $params = [])
    {
        return $this->query($sql, $params)->rowCount();
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
            // Begin transaction
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
            // Change 'reserved' to 'in_use' to match Locations table status enum value
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
            error_log("Reservation creation error: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Cancel reservation
    public function cancelReservation($booking_id, $vehicle_id)
    {
        try {
            // Begin transaction
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
            error_log("Reservation cancellation error: " . $e->getMessage());
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
        // Get booked time slots
        $bookedSlots = $this->fetchAll(
            "SELECT start_date, end_date FROM booking 
            WHERE vehicle_id = ? AND DATE(start_date) = ? AND status != 'cancelled'",
            [$vehicle_id, $date]
        );
        
        // Generate time slots
        $timeSlots = [];
        $startHour = 9;
        $endHour = 21;
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $slotStartTime = sprintf('%s %02d:%02d:00', $date, $hour, $minute);
                $slotEndTime = date('Y-m-d H:i:s', strtotime($slotStartTime) + 1800); // 30 minutes
                
                $isAvailable = true;
                
                // Check for overlap with booked time slots
                foreach ($bookedSlots as $bookedSlot) {
                    $bookedStartTime = $bookedSlot['start_date'];
                    $bookedEndTime = $bookedSlot['end_date'];
                    
                    if (
                        (strtotime($slotStartTime) >= strtotime($bookedStartTime) && strtotime($slotStartTime) < strtotime($bookedEndTime)) ||
                        (strtotime($slotEndTime) > strtotime($bookedStartTime) && strtotime($slotEndTime) <= strtotime($bookedEndTime)) ||
                        (strtotime($slotStartTime) <= strtotime($bookedStartTime) && strtotime($slotEndTime) >= strtotime($bookedEndTime))
                    ) {
                        $isAvailable = false;
                        break;
                    }
                }
                
                $timeSlots[] = [
                    'start' => date('H:i', strtotime($slotStartTime)),
                    'end' => date('H:i', strtotime($slotEndTime)),
                    'start_value' => $slotStartTime,
                    'end_value' => $slotEndTime,
                    'available' => $isAvailable
                ];
            }
        }
        
        return [
            'timeSlots' => $timeSlots,
            'bookedSlots' => $bookedSlots
        ];
    }

    // Get user's reservation history
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
            [$user_id]  // Only bind user ID parameter
        );
    }

    /**
     * Start vehicle order - change status from reserved to in_progress
     * @param int $booking_id Booking ID
     * @param int $vehicle_id Vehicle ID
     * @return bool Success or failure
     */
    public function startVehicleOrder($booking_id, $vehicle_id)
    {
        try {
            // Begin transaction
            $this->getConnection()->beginTransaction();
            
            // Update booking status to in_progress
            $this->execute(
                "UPDATE booking SET status = 'in_progress' WHERE id = ? AND status = 'reserved'",
                [$booking_id]
            );
            
            // Ensure Locations record remains in_use (no change needed)
            // Status in Locations table remains in_use
            
            // Commit transaction
            $this->getConnection()->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction
            $this->getConnection()->rollBack();
            error_log("Start vehicle order error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Complete vehicle order - change status from in_progress to completed, and change location status back to available
     * @param int $booking_id Booking ID
     * @param int $vehicle_id Vehicle ID
     * @return bool Success or failure
     */
    public function completeVehicleOrder($booking_id, $vehicle_id)
    {
        try {
            // Begin transaction
            $this->getConnection()->beginTransaction();
            
            // Update booking status to completed
            $this->execute(
                "UPDATE booking SET status = 'completed' WHERE id = ? AND status = 'in_progress'",
                [$booking_id]
            );
            
            // Update vehicle status to available
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
            error_log("Complete vehicle order error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if user has an active order
     * @param int $user_id User ID
     * @return array|false Order data or false
     */
    public function getUserActiveOrder($user_id)
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
             WHERE user_id = ? 
             AND status = 'in_progress'",
            [$user_id]
        );
    }
    
    /**
     * Get booking details
     * @param int $booking_id Booking ID
     * @return array|false Booking data or false
     */
    public function getBookingDetails($booking_id)
    {
        return $this->fetchOne(
            "SELECT b.*, 
                    l.battery_level,
                    l.status as vehicle_status,
                    ST_X(l.location) as longitude,
                    ST_Y(l.location) as latitude
             FROM booking b
             JOIN Locations l ON b.vehicle_id = l.id
             WHERE b.id = ?
             AND l.timestamp = (SELECT MAX(timestamp) FROM Locations WHERE id = b.vehicle_id)",
            [$booking_id]
        );
    }
}

// Add booking status constants
define('BOOKING_STATUS_RESERVED', 'reserved');
define('BOOKING_STATUS_IN_PROGRESS', 'in_progress');
define('BOOKING_STATUS_COMPLETED', 'completed');
define('BOOKING_STATUS_CANCELLED', 'cancelled');
?>
