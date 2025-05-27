<?php
session_start();
require_once '../core/Database.php';
require_once '../core/auth.php';

// Enable CORS support
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Create database connection
$db = new Database();

// Get HTTP request method and path
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$pathSegments = explode('/', trim($requestPath, '/'));

// Find API part index
$apiIndex = array_search('api.php', $pathSegments);
$apiPath = array_slice($pathSegments, $apiIndex + 1);

// If no specific path, set to root
$resource = $apiPath[0] ?? 'root';
$id = $apiPath[1] ?? null;

// Handle request based on path and method
try {
    switch ($resource) {
        case 'root':
            // API root path
            echo json_encode([
                'status' => 'success',
                'message' => 'eASY API Service - RESTful Edition',
                'version' => '2.0',
                'endpoints' => [
                    'vehicles' => [
                        'GET /api.php/vehicles' => 'Get all vehicles (latest locations)',
                        'GET /api.php/vehicles/{id}' => 'Get specific vehicle details',
                        'POST /api.php/vehicles' => 'Add new vehicle (admin only)',
                        'PUT /api.php/vehicles/{id}' => 'Update vehicle (admin only)',
                        'DELETE /api.php/vehicles/{id}' => 'Delete vehicle (admin only)'
                    ],
                    'reservations' => [
                        'GET /api.php/reservations' => 'Get user reservations',
                        'GET /api.php/reservations/{id}' => 'Get specific reservation',
                        'POST /api.php/reservations' => 'Create new reservation',
                        'PUT /api.php/reservations/{id}' => 'Update reservation',
                        'DELETE /api.php/reservations/{id}' => 'Cancel reservation'
                    ],
                    'locations' => [
                        'GET /api.php/locations' => 'Get vehicle locations',
                        'POST /api.php/locations' => 'Update vehicle location'
                    ],
                    'users' => [
                        'GET /api.php/users' => 'Get users (admin only)',
                        'GET /api.php/users/{id}' => 'Get user details',
                        'PUT /api.php/users/{id}' => 'Update user information'
                    ],
                    'trips' => [
                        'POST /api.php/trips' => 'Start trip',
                        'PUT /api.php/trips/{id}' => 'End trip'
                    ],
                    'timeslots' => [
                        'GET /api.php/timeslots?vehicle_id={id}&date={date}' => 'Get available time slots'
                    ]
                ]
            ]);
            break;
            
        case 'vehicles':
            handleVehiclesRequest($requestMethod, $id, $db);
            break;
            
        case 'reservations':
            handleReservationsRequest($requestMethod, $id, $db);
            break;
            
        case 'locations':
            handleLocationsRequest($requestMethod, $id, $db);
            break;
            
        case 'users':
            handleUsersRequest($requestMethod, $id, $db);
            break;
            
        case 'trips':
            handleTripsRequest($requestMethod, $id, $db);
            break;
            
        case 'timeslots':
            handleTimeSlotsRequest($requestMethod, $db);
            break;
            
        default:
            // Unknown resource
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Resource not found',
                'available_resources' => ['vehicles', 'reservations', 'locations', 'users', 'trips', 'timeslots']
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}

// Handle vehicle related requests
function handleVehiclesRequest($method, $id, $db) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single vehicle details and status
                $vehicle = $db->getVehicleStatus($id);
                if ($vehicle) {
                    echo json_encode([
                        'status' => 'success',
                        'data' => $vehicle
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Vehicle not found'
                    ]);
                }
            } else {
                // Get latest location data for all vehicles
                $vehicles = $db->getLatestLocations();
                echo json_encode([
                    'status' => 'success',
                    'data' => $vehicles
                ]);
            }
            break;
            
        case 'POST':
            // Check user permission
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Permission denied'
                ]);
                return;
            }
            
            // Add new vehicle
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data'
                ]);
                return;
            }
            
            $result = $db->addVehicle($data);
            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Vehicle added successfully',
                    'data' => ['id' => $result]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to add vehicle'
                ]);
            }
            break;
            
        case 'PUT':
            // Check user permission
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Permission denied'
                ]);
                return;
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing vehicle ID'
                ]);
                return;
            }
            
            // Update vehicle information
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data'
                ]);
                return;
            }
            
            $result = $db->updateVehicle($id, $data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Vehicle updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update vehicle'
                ]);
            }
            break;
            
        case 'DELETE':
            // Check user permission
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Permission denied'
                ]);
                return;
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing vehicle ID'
                ]);
                return;
            }
            
            // Delete vehicle
            $result = $db->deleteVehicle($id);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Vehicle deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to delete vehicle'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
}

// Handle reservation related requests
function handleReservationsRequest($method, $id, $db) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Please log in first'
        ]);
        return;
    }
    
    $currentUser = getCurrentUser();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single reservation details (supports reservation validation)
                $vehicle_id = $_GET['vehicle_id'] ?? null;
                
                if ($vehicle_id) {
                    // Validate reservation status (compatible with original verifyReservation function)
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
                            
                    $params = [$vehicle_id];
                    
                    if ($id !== 'verify') {
                        $query .= " AND b.id = ?";
                        $params[] = $id;
                    } else {
                        $query .= " AND (b.expiry_time IS NULL OR b.expiry_time > NOW())";
                    }
                    
                    $result = $db->fetchOne($query, $params);
                    
                    $isValid = false;
                    if ($result && isset($result['id']) && ($result['user_id'] == $_SESSION['user_id'] || !isset($_SESSION['user_id']))) {
                        $isValid = true;
                    }
                    
                    echo json_encode([
                        'status' => 'success',
                        'is_valid' => $isValid,
                        'data' => $result ? $result : null
                    ]);
                } else {
                    // Get single reservation details
                    $reservation = $db->getReservationById($id);
                    
                    // Check permission (only view own reservations unless admin)
                    if (!isAdmin() && $reservation && $reservation['user_id'] != $currentUser['id']) {
                        http_response_code(403);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Permission denied'
                        ]);
                        return;
                    }
                    
                    if ($reservation) {
                        echo json_encode([
                            'status' => 'success',
                            'data' => $reservation
                        ]);
                    } else {
                        http_response_code(404);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Reservation not found'
                        ]);
                    }
                }
            } else {
                // Get all user reservations (compatible with original getUserReservations function)
                if (isAdmin() && isset($_GET['all'])) {
                    // Admin can view all reservations
                    $reservations = $db->getAllReservations();
                } else {
                    // Normal user can only view their own reservations
                    $reservations = $db->getUserReservationHistory($currentUser['id']);
                }
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $reservations
                ]);
            }
            break;
            
        case 'POST':
            // Create new reservation (compatible with original reserveScooter function)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['vehicle_id'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing vehicle_id parameter'
                ]);
                return;
            }
            
            $vehicle_id = $data['vehicle_id'];
            
            try {
                // Check if vehicle is available
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
                
                // Check if user has an active reservation
                $existingBooking = $db->getUserActiveReservation($currentUser['id']);
                
                if ($existingBooking) {
                    throw new Exception('You already have an active reservation. Please cancel it first.');
                }
                
                // Determine if simple or advanced booking
                $isAdvancedBooking = isset($data['start_time']) && isset($data['end_time']);
                
                // Set reservation time
                $now = new DateTime();
                
                if ($isAdvancedBooking) {
                    // Advanced booking - use specified start and end times
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
                    // Simple booking - start from current time
                    $start_time = $now;
                    $end_time = (clone $now)->modify('+30 minutes');
                    
                    // Reservation expiry time (15 minutes later)
                    $expiry_time = (clone $now)->modify('+15 minutes');
                }
                
                // Create reservation
                $booking_id = $db->createReservation(
                    $currentUser['id'],
                    $vehicle_id,
                    $start_time->format('Y-m-d H:i:s'),
                    $end_time->format('Y-m-d H:i:s'),
                    $expiry_time ? $expiry_time->format('Y-m-d H:i:s') : null
                );
                
                // Return success response
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Vehicle reserved successfully',
                    'data' => [
                        'booking_id' => $booking_id,
                        'vehicle_id' => $vehicle_id,
                        'start_time' => $start_time->format('Y-m-d H:i:s'),
                        'end_time' => $end_time->format('Y-m-d H:i:s'),
                        'expiry_time' => $expiry_time ? $expiry_time->format('Y-m-d H:i:s') : null
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing reservation ID'
                ]);
                return;
            }
            
            // Get reservation information, check permission
            $reservation = $db->getReservationById($id);
            if (!$reservation) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Reservation not found'
                ]);
                return;
            }
            
            if (!isAdmin() && $reservation['user_id'] != $currentUser['id']) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Permission denied'
                ]);
                return;
            }
            
            // Update reservation
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data'
                ]);
                return;
            }
            
            $result = $db->updateReservation($id, $data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Reservation updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update reservation'
                ]);
            }
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing reservation ID'
                ]);
                return;
            }
            
            // Get reservation information, check permission
            $reservation = $db->getReservationById($id);
            if (!$reservation) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Reservation not found'
                ]);
                return;
            }
            
            if (!isAdmin() && $reservation['user_id'] != $currentUser['id']) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Permission denied'
                ]);
                return;
            }
            
            // Cancel reservation (compatible with original cancelReservation function)
            try {
                // Get user's active reservation
                $activeBooking = $db->getUserActiveReservation($currentUser['id']);
                
                if (!$activeBooking || $activeBooking['vehicle_id'] != $reservation['vehicle_id']) {
                    throw new Exception('No active reservation found for this vehicle');
                }
                
                // Cancel reservation
                $result = $db->cancelReservation($activeBooking['id'], $reservation['vehicle_id']);
                
                if ($result) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Reservation cancelled successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to cancel reservation'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
}

// Handle location related requests
function handleLocationsRequest($method, $id, $db) {
    switch ($method) {
        case 'GET':
            // Get vehicle locations
            $locations = $db->getVehicleLocations();
            echo json_encode([
                'status' => 'success',
                'data' => $locations
            ]);
            break;
            
        case 'POST':
            // Check permission
            if (!isLoggedIn()) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Please log in first'
                ]);
                return;
            }
            
            // Upload vehicle location
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data'
                ]);
                return;
            }
            
            $result = $db->updateVehicleLocation($data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Location updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update location'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
}

// Handle user related requests
function handleUsersRequest($method, $id, $db) {
    // Check permission
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Please log in first'
        ]);
        return;
    }
    
    $currentUser = getCurrentUser();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Only view own information unless admin
                if (!isAdmin() && $id != $currentUser['id']) {
                    http_response_code(403);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Permission denied'
                    ]);
                    return;
                }
                
                // Get single user
                $user = $db->getUserById($id);
                if ($user) {
                    // Remove sensitive information
                    unset($user['password']);
                    echo json_encode([
                        'status' => 'success',
                        'data' => $user
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'User not found'
                    ]);
                }
            } else {
                // Get user list (admin only)
                if (!isAdmin()) {
                    http_response_code(403);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Permission denied'
                    ]);
                    return;
                }
                
                $users = $db->getAllUsers();
                // Remove sensitive information
                foreach ($users as &$user) {
                    unset($user['password']);
                }
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $users
                ]);
            }
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing user ID'
                ]);
                return;
            }
            
            // Only modify own information unless admin
            if (!isAdmin() && $id != $currentUser['id']) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Permission denied'
                ]);
                return;
            }
            
            // Update user information
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid request data'
                ]);
                return;
            }
            
            $result = $db->updateUser($id, $data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'User information updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update user information'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
}

// Handle trip related requests (New)
function handleTripsRequest($method, $id, $db) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Please log in first'
        ]);
        return;
    }
    
    $currentUser = getCurrentUser();
    
    switch ($method) {
        case 'POST':
            // Start trip (compatible with original startTrip function)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['booking_id']) || !isset($data['vehicle_id'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing required parameters: booking_id and vehicle_id'
                ]);
                return;
            }
            
            $booking_id = $data['booking_id'];
            $vehicle_id = $data['vehicle_id'];
            
            try {
                // Validate if reservation belongs to current user
                $booking = $db->getBookingDetails($booking_id);
                
                if (!$booking || $booking['user_id'] != $currentUser['id']) {
                    throw new Exception('Invalid reservation');
                }
                
                if ($booking['status'] != 'reserved') {
                    throw new Exception('This reservation cannot be started in its current state');
                }
                
                // Start trip
                $result = $db->startVehicleOrder($booking_id, $vehicle_id);
                
                if ($result) {
                    http_response_code(201);
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Trip started successfully',
                        'data' => [
                            'booking_id' => $booking_id,
                            'vehicle_id' => $vehicle_id,
                            'status' => 'in_progress'
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to start trip'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        case 'PUT':
            // End trip (compatible with original endTrip function)
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing trip ID (booking_id)'
                ]);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['vehicle_id'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing vehicle_id parameter'
                ]);
                return;
            }
            
            $booking_id = $id;
            $vehicle_id = $data['vehicle_id'];
            
            try {
                // Validate if reservation belongs to current user
                $booking = $db->getBookingDetails($booking_id);
                
                if (!$booking || $booking['user_id'] != $currentUser['id']) {
                    throw new Exception('Invalid reservation');
                }
                
                if ($booking['status'] != 'in_progress') {
                    throw new Exception('This trip cannot be completed in its current state');
                }
                
                // End trip
                $result = $db->completeVehicleOrder($booking_id, $vehicle_id);
                
                if ($result) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Trip completed successfully',
                        'data' => [
                            'booking_id' => $booking_id,
                            'vehicle_id' => $vehicle_id,
                            'status' => 'completed'
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Failed to complete trip'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
    }
}

// Handle time slot related requests (New)
function handleTimeSlotsRequest($method, $db) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
        return;
    }
    
    // Get available time slots for vehicle (compatible with original getAvailableTimeSlots function)
    if (!isset($_GET['vehicle_id']) || !isset($_GET['date'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required parameters: vehicle_id and date'
        ]);
        return;
    }
    
    $vehicle_id = $_GET['vehicle_id'];
    $date = $_GET['date'];
    
    // Validate date format
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ]);
        return;
    }
    
    try {
        // Get time slots
        $result = $db->getAvailableTimeSlots($vehicle_id, $date);
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'vehicle_id' => $vehicle_id,
                'date' => $date,
                'time_slots' => $result['timeSlots'],
                'booked_slots' => $result['bookedSlots']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get available time slots: ' . $e->getMessage()
        ]);
    }
}
?> 