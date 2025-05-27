<?php
session_start();
include_once 'Database.php';

// Create database connection
$db = new Database();

// Set response type to JSON
header('Content-Type: application/json');

// Get the requested action
$action = $_GET['action'] ?? '';

// Login check function
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Please log in first']);
        exit;
    }
    return $_SESSION['user_id'];
}

// Process requests
try {
    switch ($action) {
        case 'getLatestLocations':
            // Get latest vehicle location data - Using new method
            $data = $db->getLatestLocations();
            echo json_encode($data);
            break;
            
        case 'reserveScooter':
            // Reserve vehicle
            $user_id = checkLogin();
            
            // Get POST data
            $postData = file_get_contents('php://input');
            error_log("Received reservation data: " . $postData); // Debug log
            
            $data = json_decode($postData, true);
            
            if (!isset($data['scooter_id'])) {
                throw new Exception('Missing scooter_id parameter');
            }
            
            $vehicle_id = $data['scooter_id'];
            
            // Check if the vehicle is available - Use new getVehicleStatus method
            $vehicleStatus = $db->getVehicleStatus($vehicle_id);
            
            if (!$vehicleStatus) {
                throw new Exception('Vehicle not found');
            }
            
            if ($vehicleStatus['availability'] != 1) {
                throw new Exception('Vehicle is not available for reservation');
            }
            
            if (isset($vehicleStatus['battery_level']) && $vehicleStatus['battery_level'] < 15) {
                throw new Exception('Vehicle battery too low for reservation');
            }
            
            // Check if the user has an active reservation
            $existingBooking = $db->getUserActiveReservation($user_id);
            
            if ($existingBooking) {
                throw new Exception('You already have an active reservation. Please cancel it first.');
            }
            
            // Determine if it's a simple or advanced booking
            $isAdvancedBooking = isset($data['start_time']) && isset($data['end_time']);
            
            // Set reservation time
            $now = new DateTime();
            
            if ($isAdvancedBooking) {
                // Advanced booking - Use specified start and end times
                $start_time = new DateTime($data['start_time']);
                $end_time = new DateTime($data['end_time']);
                
                // Validate time parameters
                if ($start_time >= $end_time) {
                    throw new Exception('End time must be after start time');
                }
                
                // Check for time conflicts
                $conflictBooking = $db->getBookingConflicts($vehicle_id, $data['start_time'], $data['end_time']);
                if ($conflictBooking) {
                    throw new Exception('This time slot is already booked');
                }
                
                // For advanced booking, set expiry_time to NULL
                $expiry_time = null;
            } else {
                // Simple booking - Start from current time
                $start_time = $now;
                $end_time = (clone $now)->modify('+30 minutes');
                
                // Reservation expiry time (15 minutes later)
                $expiry_time = (clone $now)->modify('+15 minutes');
            }
            
            try {
                // Create reservation
                $booking_id = $db->createReservation(
                    $user_id,
                    $vehicle_id,
                    $start_time->format('Y-m-d H:i:s'),
                    $end_time->format('Y-m-d H:i:s'),
                    $expiry_time ? $expiry_time->format('Y-m-d H:i:s') : null
                );
                
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Vehicle reserved successfully',
                    'booking_id' => $booking_id,
                    'start_time' => $start_time->format('Y-m-d H:i:s'),
                    'end_time' => $end_time->format('Y-m-d H:i:s'),
                    'expiry_time' => $expiry_time ? $expiry_time->format('Y-m-d H:i:s') : null
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to create reservation: ' . $e->getMessage());
            }
            break;
            
        case 'cancelReservation':
            // Cancel reservation
            $user_id = checkLogin();
            
            // Get POST data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['scooter_id'])) {
                throw new Exception('Missing scooter_id parameter');
            }
            
            $vehicle_id = $data['scooter_id'];
            
            try {
                // Get user's active reservation
                $activeBooking = $db->getUserActiveReservation($user_id);
                
                if (!$activeBooking || $activeBooking['vehicle_id'] != $vehicle_id) {
                    throw new Exception('No active reservation found for this vehicle');
                }
                
                // Cancel reservation
                $db->cancelReservation($activeBooking['id'], $vehicle_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Reservation cancelled successfully'
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to cancel reservation: ' . $e->getMessage());
            }
            break;
            
        case 'verifyReservation':
            // Verify reservation status
            if (!isset($_GET['id'])) {
                throw new Exception('Missing vehicle id parameter');
            }
            
            $scooterId = $_GET['id'];
            $bookingId = $_GET['booking_id'] ?? null;
            
            $query = "SELECT 
                      b.id, b.user_id, b.vehicle_id, b.start_date, b.end_date, b.expiry_time, b.status,
                      u.username,
                      v.battery_level, v.latitude, v.longitude
                    FROM booking b
                    JOIN users u ON b.user_id = u.id
                    LEFT JOIN (
                        SELECT id, battery_level, 
                               ST_Y(location) as latitude, 
                               ST_X(location) as longitude
                        FROM Locations l1
                        WHERE timestamp = (SELECT MAX(timestamp) FROM Locations l2 WHERE l2.id = l1.id)
                    ) v ON b.vehicle_id = v.id
                    WHERE b.vehicle_id = ? AND b.status = 'reserved'";
                    
            $params = [$scooterId, $scooterId];
            
            if ($bookingId) {
                $query .= " AND b.id = ?";
                $params[] = $bookingId;
            } else {
                $query .= " AND (b.expiry_time IS NULL OR b.expiry_time > NOW())";
            }
            
            $result = $db->fetchOne($query, $params);
            
            $isValid = false;
            if ($result && isset($result['id']) && ($result['user_id'] == $_SESSION['user_id'] || !isset($_SESSION['user_id']))) {
                $isValid = true;
            }
            
            echo json_encode([
                'is_valid' => $isValid,
                'data' => $result ? $result : null
            ]);
            break;
            
        case 'getAvailableTimeSlots':
            // Get available time slots for the vehicle
            if (!isset($_GET['scooter_id']) || !isset($_GET['date'])) {
                throw new Exception('Missing required parameters');
            }
            
            $scooterId = $_GET['scooter_id'];
            $date = $_GET['date'];
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD');
            }
            
            // Get time slots
            $result = $db->getAvailableTimeSlots($scooterId, $date);
            
            echo json_encode([
                'success' => true,
                'timeSlots' => $result['timeSlots'],
                'bookedSlots' => $result['bookedSlots']
            ]);
            break;
            
        case 'getUserReservations':
            // Get user reservation history
            $user_id = checkLogin();
            
            $reservations = $db->getUserReservationHistory($user_id);
            
            echo json_encode([
                'success' => true,
                'reservations' => $reservations
            ]);
            break;
            
        case 'verifyVehicleStatus':
            // Verify vehicle status
            if (!isset($_GET['id'])) {
                throw new Exception('Missing vehicle id parameter');
            }
            
            $vehicleId = $_GET['id'];
            $vehicleStatus = $db->getVehicleStatus($vehicleId);
            
            if (!$vehicleStatus) {
                throw new Exception('Vehicle not found');
            }
            
            echo json_encode([
                'success' => true,
                'vehicle' => $vehicleStatus
            ]);
            break;
        
        case 'startTrip':
            // Start trip
            $user_id = checkLogin();
            
            // Get POST data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['booking_id']) || !isset($data['scooter_id'])) {
                throw new Exception('Missing required parameters');
            }
            
            $booking_id = $data['booking_id'];
            $vehicle_id = $data['scooter_id'];
            
            // Verify if the reservation belongs to the current user
            $booking = $db->getBookingDetails($booking_id);
            
            if (!$booking || $booking['user_id'] != $user_id) {
                throw new Exception('Invalid reservation');
            }
            
            if ($booking['status'] != BOOKING_STATUS_RESERVED) {
                throw new Exception('This reservation cannot be started in its current state');
            }
            
            // Start trip
            $result = $db->startVehicleOrder($booking_id, $vehicle_id);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Trip started successfully' : 'Failed to start trip'
            ]);
            break;
            
        case 'endTrip':
            // End trip
            $user_id = checkLogin();
            
            // Get POST data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['booking_id']) || !isset($data['scooter_id'])) {
                throw new Exception('Missing required parameters');
            }
            
            $booking_id = $data['booking_id'];
            $vehicle_id = $data['scooter_id'];
            
            // Verify if the reservation belongs to the current user
            $booking = $db->getBookingDetails($booking_id);
            
            if (!$booking || $booking['user_id'] != $user_id) {
                throw new Exception('Invalid reservation');
            }
            
            if ($booking['status'] != BOOKING_STATUS_IN_PROGRESS) {
                throw new Exception('This trip cannot be completed in its current state');
            }
            
            // End trip
            $result = $db->completeVehicleOrder($booking_id, $vehicle_id);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Trip completed successfully' : 'Failed to complete trip'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $action);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}

?>