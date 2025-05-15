<?php
// Add debug output, only enabled in development environment
function debug_log($message) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("[Locations] " . $message);
    }
}

// Define current environment - Set to false in production
define('DEBUG_MODE', false);

debug_log("Page load started");

include_once 'Database.php';
include_once 'auth.php';

// Get user login status
$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();

if (DEBUG_MODE) {
    debug_log("User login status: " . ($isLoggedIn ? "Logged in" : "Not logged in"));
    if ($isLoggedIn) {
        debug_log("User ID: " . $currentUser['id'] . ", Username: " . $currentUser['username']);
    }
}

try {
    debug_log("Starting vehicle data fetch");
    // Create database connection
    $db = new Database();
    
    // Use existing view to get latest location data
    $pointsData = $db->getLatestLocations();
    debug_log("Retrieved " . count($pointsData) . " vehicle records");
    
    // Ensure correct data format, only convert types
    foreach ($pointsData as &$point) {
        $point['latitude'] = (float)$point['latitude'];
        $point['longitude'] = (float)$point['longitude'];
        $point['battery_level'] = isset($point['battery_level']) ? (int)$point['battery_level'] : 0;
    }
    
    $pointsJson = json_encode($pointsData, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    debug_log("Database error: " . $e->getMessage());
    $pointsData = [];
    $pointsJson = "[]";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Tracking System</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="css/locations.css">
    <link rel="stylesheet" href="css/booking.css">
</head>
<body>
<!-- Map container -->
<div id="map"></div>

<!-- Location permission prompt dialog - changed to English -->
<div id="locationPermissionPrompt" class="permission-prompt">
    <div class="permission-prompt-content">
        <h3>Location Usage Information</h3>
        <p>We need to use your location to help you find nearby vehicles and provide a better user experience.</p>
        <p>Your location information is only used within this application and will not be shared with third parties.</p>
        <div class="permission-buttons">
            <button id="allowLocationBtn" class="allow-btn">Allow Location</button>
            <button id="denyLocationBtn" class="deny-btn">Don't Allow</button>
        </div>
    </div>
</div>

<!-- Nearby scooter info panel -->
<div id="nearbyInfo" class="nearby-info">
    <h3>Nearby Available Scooters</h3>
    <div id="nearbyStatus"></div>
    <table class="nearby-table">
        <thead>
            <tr>
                <th>Distance</th>
                <th>Battery</th>
            </tr>
        </thead>
        <tbody id="nearbyScootersList">
            <!-- Nearby scooters will be dynamically added here -->
        </tbody>
    </table>
    <div style="margin-top: 10px; text-align: center;">
        <button id="closeNearbyInfo" class="refresh-button" style="width: auto; margin-right: 5px;">Close</button>
        <button id="showAllScooters" class="refresh-button" style="width: auto; background-color: #2196F3; margin-left: 5px;">Show All Scooters</button>
    </div>
</div>

<!-- Reservation status panel -->
<div id="reservationStatus" class="reservation-status">
    <h4>Vehicle Reserved</h4>
    <div class="timer-container">
        <div class="timer-label">Reservation countdown</div>
        <div class="timer" id="reservationTimer">15:00</div>
    </div>
    <div id="reservedScooterInfo">
        Vehicle ID: <span id="reservedScooterId"></span><br>
        Reservation time: <span id="reservedTime"></span><br>
        <span id="expiryTimeLabel">Expiry time: </span><span id="reservationExpiryTime"></span>
    </div>
    <div class="reservation-actions">
        <button id="startVehicle" class="start-btn">Start Vehicle</button>
        <button id="completeTrip" class="complete-btn" style="display: none;">End Trip</button>
        <button id="cancelReservation" class="cancel-btn">Cancel Reservation</button>
        <button id="navigateToReserved" class="navigate-btn">Navigate</button>
    </div>
</div>

<!-- Booking modal - removed vehicle image, optimized UI -->
<div id="bookingModal" class="booking-modal" style="display:none;">
    <div class="booking-modal-content">
        <div class="booking-modal-header">
            <h3>Reserve Vehicle <span id="bookingVehicleId"></span></h3>
            <span class="booking-close">&times;</span>
        </div>
        <div class="booking-modal-body">
            <div class="vehicle-info">
                <div class="vehicle-details">
                    <h3>Vehicle #<span id="modalVehicleId"></span></h3>
                    <div class="vehicle-battery">
                        <span><strong>Battery Level:</strong></span>
                        <div class="battery-indicator">
                            <div class="battery-level" id="modalBatteryLevel"></div>
                            <div class="battery-tip"></div>
                            <div class="battery-text" id="modalBatteryText"></div>
                        </div>
                    </div>
                    <p><strong>Location:</strong> <span id="modalLocation">Sheffield</span></p>
                </div>
            </div>
            
            <form id="bookingForm" class="booking-form">
                <input type="hidden" id="vehicle_id" name="vehicle_id">
                
                <div class="form-group">
                    <label>Reservation Date</label>
                    <div class="date-selector">
                        <!-- Date buttons will be dynamically generated by JS -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Select Time Slot (30 minutes per reservation)</label>
                    <div class="time-slots" id="timeSlots">
                        <!-- Time slots will be dynamically generated by JS -->
                    </div>
                </div>
                
                <div class="booking-summary" id="bookingSummary" style="display: none;">
                    <h3>Reservation Summary</h3>
                    <p>You will reserve vehicle <strong id="summaryVehicleId"></strong></p>
                    <p>Reservation date: <strong id="summaryDate"></strong></p>
                    <p>Reservation time: <strong id="summaryTime"></strong></p>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBooking" disabled>Submit Reservation</button>
            </form>
        </div>
    </div>
</div>

<!-- Include Leaflet library -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Include modular JS files -->
<script src="js/util-service.js"></script>
<script src="js/map-service.js"></script>
<script src="js/location-service.js"></script>
<script src="js/data-service.js"></script>
<script src="js/reservation.js"></script>
<script src="js/ui-service.js"></script>

<!-- Set PHP variables to JavaScript -->
<script>
// Pass PHP variables to JavaScript - data will be directly injected here
const USER_IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
const USER_ID = <?= $isLoggedIn ? $_SESSION['user_id'] : 'null' ?>;
const USER_NAME = "<?= $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '' ?>";

// Ensure INITIAL_VEHICLE_DATA is valid JSON to prevent syntax errors
const INITIAL_VEHICLE_DATA = <?= $pointsJson ? $pointsJson : '[]' ?>;

// Simplify debug logs
console.log("Initializing page...");
console.log("User data loaded, number of vehicle records:", INITIAL_VEHICLE_DATA.length);

// Add global error handler
window.addEventListener('error', function(event) {
    console.error('Global error:', event.message, 'at', event.filename, ':', event.lineno);
});

window.onerror = function(message, source, lineno, colno, error) {
    console.error('Window Error:', message, 'at', source, ':', lineno, ':', colno);
    return false;
};
</script>

<!-- Include initialization script -->
<script src="js/init.js"></script>
</body>
</html>
