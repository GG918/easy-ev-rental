<?php
/**
 * Maintenance Record API Endpoint
 * Handles adding, completing, and retrieving maintenance records.
 */

// Include necessary files
require_once '../core/Database.php';
require_once '../core/auth.php';

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Validate if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Login required to access this resource.'
    ]);
    exit;
}

// Check if user is admin
$isAdmin = isAdmin();

// Initialize database connection
$db = new Database();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';

// Handle request
try {
    switch ($method) {
        case 'GET':
            // Get maintenance records list
            if ($pathInfo === '' || $pathInfo === '/') {
                getMaintenance();
            } elseif (preg_match('/^\/(\d+)$/', $pathInfo, $matches)) {
                // Get specific maintenance record
                getMaintenanceById($matches[1]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Requested resource not found.'
                ]);
            }
            break;
            
        case 'POST':
            // Add new maintenance record
            if ($pathInfo === '' || $pathInfo === '/') {
                addMaintenance();
            } elseif (preg_match('/^\/(\d+)\/complete$/', $pathInfo, $matches)) {
                // Complete maintenance
                completeMaintenance($matches[1]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Requested resource not found.'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Unsupported request method.'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Maintenance API Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error processing request.',
        'details' => $e->getMessage()
    ]);
}

/**
 * Get maintenance records list.
 */
function getMaintenance() {
    global $db;
    
    try {
        $sql = "SELECT 
                    m.id, 
                    m.vehicle_id, 
                    m.description, 
                    m.maintenance_date, 
                    m.created_at,
                    m.completed_at 
                FROM maintenance m 
                ORDER BY m.created_at DESC";
        
        $maintenance = $db->fetchAll($sql);
        
        echo json_encode([
            'status' => 'success',
            'data' => $maintenance
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to get maintenance records: " . $e->getMessage());
    }
}

/**
 * Get specific maintenance record.
 * @param int $id Maintenance record ID.
 */
function getMaintenanceById($id) {
    global $db;
    
    try {
        $sql = "SELECT 
                    m.id, 
                    m.vehicle_id, 
                    m.description, 
                    m.maintenance_date, 
                    m.created_at,
                    m.completed_at 
                FROM maintenance m 
                WHERE m.id = ?";
        
        $maintenance = $db->fetchOne($sql, [$id]);
        
        if (!$maintenance) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Maintenance record not found.'
            ]);
            return;
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $maintenance
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to get maintenance record: " . $e->getMessage());
    }
}

/**
 * Add new maintenance record.
 */
function addMaintenance() {
    global $db, $isAdmin;
    
    // Validate permission - only admin can add maintenance records
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permission denied to perform this action.'
        ]);
        return;
    }
    
    // Get and validate request data
    $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $maintenance_date = filter_input(INPUT_POST, 'maintenance_date', FILTER_SANITIZE_STRING);
    
    if (!$vehicle_id || !$description || !$maintenance_date) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required parameters.'
        ]);
        return;
    }
    
    try {
        // Check if vehicle exists
        $vehicle = $db->fetchOne("SELECT id FROM Locations WHERE id = ? LIMIT 1", [$vehicle_id]);
        
        if (!$vehicle) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Specified vehicle not found.'
            ]);
            return;
        }
        
        // Add maintenance record
        $maintenance_id = $db->recordMaintenance($vehicle_id, $description, $maintenance_date);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Maintenance record added successfully.',
            'data' => [
                'id' => $maintenance_id,
                'vehicle_id' => $vehicle_id
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to add maintenance record: " . $e->getMessage());
    }
}

/**
 * Complete maintenance.
 * @param int $id Maintenance record ID.
 */
function completeMaintenance($id) {
    global $db, $isAdmin;
    
    // Validate permission - only admin can complete maintenance
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permission denied to perform this action.'
        ]);
        return;
    }
    
    try {
        // Get maintenance record
        $maintenance = $db->fetchOne("SELECT id, vehicle_id, completed_at FROM maintenance WHERE id = ?", [$id]);
        
        if (!$maintenance) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Maintenance record not found.'
            ]);
            return;
        }
        
        // Check if already completed
        if ($maintenance['completed_at']) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'This maintenance record is already marked as completed.'
            ]);
            return;
        }
        
        // Complete maintenance
        $db->completeMaintenance($id, $maintenance['vehicle_id']);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Maintenance marked as completed.',
            'data' => [
                'id' => $id,
                'vehicle_id' => $maintenance['vehicle_id']
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to complete maintenance: " . $e->getMessage());
    }
}
?> 