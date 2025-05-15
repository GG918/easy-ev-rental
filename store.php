<?php
// === PHP version to receive and store location into Yunohost database ===
// Place in my_webapp/public/store.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!isset($data['location']['lat'], $data['location']['lng'], $data['timestamp'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$lat = floatval($data['location']['lat']);
$lng = floatval($data['location']['lng']);
$timestamp_raw = $data['timestamp'];
$device_id = isset($data['device_id']) ? $data['device_id'] : 'Arduino';

// Ensure timestamp is formatted as yyyy-mm-dd hh:mm:ss
$date = new DateTime($timestamp_raw);
$timestamp = $date->format('Y-m-d H:i:s');

require_once("Database.php"); // Assuming Database.php is in my_webapp/www/

$db = new Database();
$pdo = $db->getConnection();

$speed_mph = 0.0;
$status = 'in_use';
$battery_level = 100;
$point = "POINT($lng $lat)";

try {
    $stmt = $pdo->prepare("INSERT INTO Locations (speed_mph, status, location, battery_level, timestamp) VALUES (?, ?, ST_GeomFromText(?), ?, ?)");
    $stmt->execute([$speed_mph, $status, $point, $battery_level, $timestamp]);
    
    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
