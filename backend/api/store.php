<?php
// === PHP endpoint to receive and store location data from Arduino trackers ===
// Handles data from EV rental tracking devices

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// 加载配置
$config = require_once dirname(__DIR__) . '/config/config.php';

// Log incoming data for debugging
$log_file = fopen($config['paths']['debug'] . "/arduino_data.log", "a");
fwrite($log_file, date("[Y-m-d H:i:s] ") . $raw . "\n\n");
fclose($log_file);

// Check for required fields
if (!isset($data['location']['lat'], $data['location']['lng'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required location fields"]);
    exit;
}

$lat = floatval($data['location']['lat']);
$lng = floatval($data['location']['lng']);
$device_id = isset($data['device_id']) ? intval($data['device_id']) : 1;
$speed_mph = isset($data['speed_mph']) ? floatval($data['speed_mph']) : 0.0;
$battery_level = isset($data['battery_level']) ? intval($data['battery_level']) : 100;

// Set timestamp to current server time if not provided
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');

// Normalize timestamp format
if (isset($data['timestamp'])) {
    $date = new DateTime($timestamp);
    $timestamp = $date->format('Y-m-d H:i:s');
}

// Default status based on speed (stationary = available, moving = in_use)
$status = ($speed_mph < 1.0) ? 'available' : 'in_use';
if (isset($data['status'])) {
    $status = $data['status'];
}

require_once dirname(__DIR__) . "/core/Database.php";

$db = new Database();
$pdo = $db->getConnection();

// Create spatial point from coordinates
$point = "POINT($lng $lat)";

try {
    // Insert location data
    $stmt = $pdo->prepare("INSERT INTO Locations (id, speed_mph, status, location, battery_level, timestamp) 
                          VALUES (?, ?, ?, ST_GeomFromText(?), ?, ?)");
    $stmt->execute([$device_id, $speed_mph, $status, $point, $battery_level, $timestamp]);
    
    // Return success response
    echo json_encode([
        "success" => true,
        "message" => "Location data stored successfully",
        "device_id" => $device_id,
        "timestamp" => $timestamp
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Store.php error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database error",
        "details" => $e->getMessage()
    ]);
}
?> 