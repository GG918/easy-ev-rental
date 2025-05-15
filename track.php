<?php
include_once 'Database.php';
include_once 'auth.php';

// Require authentication
if (!isLoggedIn()) {
    header('Location: index.php?login_required=1');
    exit;
}

// Get user location data
$db = new Database();
$locations = $db->getLatestLocations();

// Format data for display
$trackingData = [];
foreach ($locations as $loc) {
    // ...existing code...
}

// CSV file path constant
define('TRACK_CSV_FILE', __DIR__ . '/Thursday  Track.csv');

// State transition related constants
define('SPEED_WALKING', 4.0);           // mph, walking speed threshold
define('SPEED_BRAKE_THRESHOLD', 1.0);   // mph, braking threshold while driving
define('MIN_STATE_DURATION', 5);        // seconds, minimum duration for state transition
define('HYSTERESIS_THRESHOLD', 0.5);    // mph, hysteresis threshold

function parseTrackCsv($filepath) {
    if (!file_exists($filepath)) {
        throw new Exception('Track CSV file not found');
    }
    
    $file = fopen($filepath, 'r');
    if (!$file) {
        throw new Exception('Unable to open track CSV file');
    }
    
    $headers = fgetcsv($file);
    if (!$headers) {
        fclose($file);
        throw new Exception('Invalid CSV format - no headers found');
    }
    
    // Find the indices of required columns
    $columns = array_flip($headers);
    $required_columns = [
        'Time(sec)', 'Latitude', 'Longitude', 'Speed(m/s)', 'Course(deg)',
        'Date(GMT)', 'Date(Local)'  // Changed 'Time(Local)' to 'Date(Local)'
    ];
    
    foreach ($required_columns as $col) {
        if (!isset($columns[$col])) {
            fclose($file);
            throw new Exception("Required column '$col' not found in CSV");
        }
    }
    
    $data = [];
    $total_speed = 0;
    $speed_count = 0;
    $start_time = null;
    
    while (($row = fgetcsv($file)) !== false) {
        $timestamp = strtotime($row[$columns['Date(GMT)']]);
        $time_sec = floatval($row[$columns['Time(sec)']]);
        $speed = floatval($row[$columns['Speed(m/s)']]);
        $course = floatval($row[$columns['Course(deg)']]);
        
        // Normalize speed and course values
        if ($speed < 0) $speed = 0;
        if ($course < 0) $course = 0;
        
        // Record the first valid time point
        if ($start_time === null) {
            $start_time = $time_sec;
        }
        
        // Calculate normalized time
        $normalized_time = $time_sec - $start_time;
        
        if ($speed > 0) {
            $total_speed += $speed;
            $speed_count++;
        }
        
        $data[] = [
            'timestamp' => $timestamp,
            'time_sec' => $normalized_time,
            'original_time' => $time_sec,
            'latitude' => floatval($row[$columns['Latitude']]),
            'longitude' => floatval($row[$columns['Longitude']]),
            'speed' => $speed,
            'speed_mph' => $speed > 0 ? $speed * 2.23694 : 0,
            'course' => $course
        ];
    }
    
    // Sort by time to ensure data is ordered
    usort($data, function($a, $b) {
        return $a['time_sec'] <=> $b['time_sec'];
    });
    
    fclose($file);
    
    // Calculate average speed
    $avg_speed = $speed_count > 0 ? $total_speed / $speed_count : 0;
    
    return [
        'points' => $data,
        'stats' => [
            'avg_speed' => $avg_speed,
            'avg_speed_mph' => $avg_speed * 2.23694
        ]
    ];
}

try {
    $trackResult = parseTrackCsv(TRACK_CSV_FILE);
    $trackData = $trackResult['points'];
    $trackStats = $trackResult['stats'];
    $error = '';
} catch (Exception $e) {
    $error = $e->getMessage();
    $trackData = [];
    $trackStats = ['avg_speed' => 0, 'avg_speed_mph' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Viewer</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        html, body { 
            margin: 0; 
            padding: 0; 
            height: 100%; 
        }
        
        #map { 
            width: 100vw;
            height: 100vh;
        }
        
        .controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            display: flex;
            gap: 10px;
        }
        
        .speed-control {
            margin-top: 10px;
        }
        
        .speed-control input {
            width: 100%;
        }
        
        /* Track animation styles */
        @keyframes drawPath {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        .animated-path {
            animation: drawPath 3s ease-in-out forwards;
        }
        
        .path-base {
            stroke-dasharray: 1000;
            stroke-dashoffset: 1000;
        }
        
        /* Custom marker styles */
        .custom-marker {
            color: #FF0000;
            font-size: 30px; /* Increase font size */
            text-shadow: 3px 3px 6px rgba(0,0,0,0.4);
            transform: translateX(-50%) translateY(-50%);
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .custom-marker:hover {
            transform: translateX(-50%) translateY(-50%) scale(1.2);
        }
        
        /* Action icon styles */
        .action-icon {
            font-size: 24px;
            margin-right: 5px;
        }

        /* Action label styles */
        .action-label {
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-top: 4px;
        }
        
        /* Path animation transition effect */
        .leaflet-polyline {
            transition: d 0.1s linear;
        }
        
        /* Track tooltip styles */
        .track-tooltip {
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            pointer-events: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            max-width: 200px;
        }
        
        /* Track line hover effect */
        .leaflet-polyline {
            transition: all 0.3s;
            cursor: crosshair;
        }
        
        .leaflet-polyline:hover {
            stroke-width: 6px;
        }
        
        /* Track line interaction effect */
        .track-path {
            transition: all 0.3s ease;
            cursor: crosshair;
        }
        
        .track-path:hover {
            stroke-width: 8px;
            stroke-opacity: 0.9;
        }
        
        /* Time label styles */
        .time-label {
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .elapsed-time {
            font-size: 12px;
            opacity: 0.9;
        }
        
        /* Marker info panel styles */
        .marker-info {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.4;
            min-width: 150px;
        }
        
        .marker-info-label {
            font-weight: bold;
            color: #4CAF50;
        }
        
        .marker-info-value {
            float: right;
            color: #fff;
        }
        
        .marker-info-row {
            margin: 4px 0;
            clear: both;
        }
        
        /* Remove default Leaflet popup styles */
        .leaflet-popup-content-wrapper {
            background: transparent;
            box-shadow: none;
        }
        
        .leaflet-popup-tip-container {
            display: none;
        }

        /* Add CSS transition effects */
        .state-transition {
            transition: transform 0.3s ease-in-out;
        }

        .custom-marker {
            transition: all 0.3s ease-in-out;
        }

        .marker-icon-change {
            animation: iconChange 0.3s ease-in-out;
        }

        @keyframes iconChange {
            0% { transform: scale(1) translateX(-50%) translateY(-50%); }
            50% { transform: scale(1.2) translateX(-50%) translateY(-50%); }
            100% { transform: scale(1) translateX(-50%) translateY(-50%); }
        }

        /* Progress bar container styles */
        .progress-container {
            width: 100%;
            height: 4px;
            background: rgba(0,0,0,0.1);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        /* Progress bar styles */
        .progress-bar {
            height: 100%;
            width: 0;
            background: #4CAF50;
            transition: width 0.1s linear;
        }

        /* Optimize control panel layout */
        .controls {
            min-width: 200px;
        }

        /* Time display styles */
        .time-display {
            font-size: 12px;
            color: #666;
            text-align: right;
            margin-top: 4px;
        }

        /* Progress bar container style adjustment */
        .progress-container {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        /* Progress bar style optimization */
        .progress-bar-wrapper {
            height: 8px;
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
            cursor: pointer;
            transition: height 0.2s ease;
        }

        .progress-bar-wrapper:hover {
            height: 12px;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background: #4CAF50;
            border-radius: 4px;
            transition: all 0.1s linear;
            position: relative;
        }

        .progress-bar:hover {
            background: #45a049;
        }

        /* Time display style optimization */
        .time-display {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #333;
            margin-top: 5px;
        }

        /* Control panel style adjustment */
        .controls {
            min-width: 200px;
            margin-top: 4px;
        }

        /* Progress bar container style adjustment */
        .progress-container {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        /* Progress bar style optimization */
        .progress-bar-wrapper {
            height: 8px;
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
            cursor: pointer;
            transition: height 0.2s ease;
        }

        .progress-bar-wrapper:hover {
            height: 12px;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background: #4CAF50;
            border-radius: 4px;
            transition: all 0.1s linear;
            position: relative;
        }

        .progress-bar:hover {
            background: #45a049;
        }

        /* Time display style optimization */
        .time-display {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #333;
            margin-top: 5px;
        }

        /* Control panel style adjustment */
        .controls {
            min-width: 200px;
            background: rgba(255, 255, 255, 0.95);
        }

        /* Progress bar tooltip styles */
        .progress-tooltip {
            position: absolute;
            bottom: 100%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            white-space: nowrap;
        }

        .progress-bar-wrapper:hover .progress-tooltip {
            opacity: 1;
        }

        /* Speed control style optimization */
        .speed-control {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 0;
        }

        .speed-control input[type="range"] {
            flex: 1;
            height: 6px;
            -webkit-appearance: none;
            background: rgba(0,0,0,0.1);
            border-radius: 3px;
            transition: height 0.2s;
        }

        .speed-control input[type="range"]:hover {
            height: 8px;
        }

        .speed-control input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            background: #4CAF50;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .speed-control input[type="range"]:hover::-webkit-slider-thumb {
            transform: scale(1.2);
        }

        #speedValue {
            min-width: 40px;
            text-align: right;
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div id="map"></div>
    <div class="controls">
        <button id="playButton">Play</button>
        <button id="resetButton">Reset</button>
        <div class="speed-control">
            <input type="range" id="animationSpeed" min="1" max="100" step="1" value="20">
            <span id="speedValue">20x</span>
let isPlaying = false;
let currentPathLayer = null;
let completedPathLayer = null;

function createPathWithTooltip(coordinates, color, weight) {
    const path = L.polyline(coordinates, {
        color,
        weight,
        opacity: 0.7,
        className: 'track-path'
    }).addTo(map);
    
    let currentTooltip = null;
    
    function findClosestPoint(latlng) {
        let closestPoint = null;
        let minDistance = Infinity;
        let pointIndex = 0;
        
        trackData.forEach((point, index) => {
            const distance = map.distance(
                latlng,
                L.latLng(point.latitude, point.longitude)
            );
            if (distance < minDistance) {
                minDistance = distance;
                closestPoint = point;
                pointIndex = index;
            }
        });
        
        return { point: closestPoint, distance: minDistance, index: pointIndex };
    }
    
    function formatTime(timestamp, elapsed) {
        const date = new Date(timestamp * 1000);
        const timeStr = date.toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            fractionalSecondDigits: 1
        });
        
        // Format elapsed time
        const minutes = Math.floor(elapsed / 60);
        const seconds = (elapsed % 60).toFixed(1);
        const elapsedStr = minutes > 0 ? 
            `${minutes}m ${seconds}s` : 
            `${seconds}s`;
            
        return `<div class="time-label">${timeStr}</div>
                <div class="elapsed-time">Elapsed: ${elapsedStr}</div>`;
    }
    
    path.on('mousemove', function(e) {
        const { point, distance, index } = findClosestPoint(e.latlng);
        
        if (point && distance < 20) { // 20 meters threshold
            // Remove old tooltip
            if (currentTooltip) {
                map.removeLayer(currentTooltip);
            }
            
            // Create new tooltip
            currentTooltip = L.tooltip({
                permanent: false,
                direction: 'top',
                className: 'track-tooltip',
                offset: [0, -weight] // Adjust offset based on line width
            })
                .setLatLng(e.latlng)
                .setContent(formatTime(point.timestamp, point.time_sec))
                .addTo(map);
                
            // Highlight current segment
            if (index < coordinates.length - 1) {
                path.setStyle({
                    weight: weight * 1.5
                });
            }
        }
    });
    
    path.on('mouseout', function() {
        if (currentTooltip) {
            map.removeLayer(currentTooltip);
            currentTooltip = null;
        }
        path.setStyle({
            weight: weight
        });
    });
    
    return path;
}

function initMap() {
    if (map) {
        map.remove();
    }
    
    // Initialize map, set default view
    const defaultCenter = trackData.length > 0 
        ? [trackData[0].latitude, trackData[0].longitude] 
        : [53.381549, -1.478778];
    
    map = L.map('map').setView(defaultCenter, 16);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    if (trackData.length > 0) {
        const coordinates = trackData.map(point => [point.latitude, point.longitude]);
        
        // Use the new create path function
        pathLayer = createPathWithTooltip(coordinates, '#cccccc', 4);
        
        // Create path layers for animation with the same interaction effects
        currentPathLayer = createPathWithTooltip([], '#FF0000', 4);
        completedPathLayer = createPathWithTooltip([], '#4CAF50', 4);
        
        // Create larger marker
        marker = L.marker(coordinates[0], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: '●',
                iconSize: [40, 40], // Increase icon size
                iconAnchor: [20, 20] // Adjust anchor position
            })
        }).addTo(map);
        
        // Add pulse animation to marker
        marker._icon.classList.add('marker-pulse');
        
        // Initialize marker info
        updateMarkerInfo(marker, trackData[0], null, 0);
        
        // Adjust map view to fit the track
        map.fitBounds(pathLayer.getBounds(), {
            padding: [50, 50]
        });
    }
    
    // Trigger map redraw
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
}

// Set simulation clock state object
const animationState = {
    simulatedTime: 0,
    lastFrameTime: 0,
    speedMultiplier: 1
};

function updatePath(currentPoint, progress) {
    if (!trackData.length) return;
    
    // Find all points before current point
    const currentIndex = trackData.findIndex(p => p.time_sec >= currentPoint.time_sec);
    const completedPoints = trackData.slice(0, currentIndex + 1).map(p => [p.latitude, p.longitude]);
    
    // Interpolate between current point and next point
    if (currentIndex < trackData.length - 1) {
        const nextPoint = trackData[currentIndex + 1];
        const currentLat = currentPoint.latitude + (nextPoint.latitude - currentPoint.latitude) * progress;
        const currentLng = currentPoint.longitude + (nextPoint.longitude - currentPoint.longitude) * progress;
        completedPoints.push([currentLat, currentLng]);
    }
    
    // Update completed path and current path
    completedPathLayer.setLatLngs(completedPoints);
    currentPathLayer.setLatLngs(completedPoints);
}

function formatInfoContent(point) {
    const speed = point.speed !== null ? point.speed.toFixed(1) : '-';
    const speedMph = point.speed !== null ? (point.speed * 2.23694).toFixed(1) : '-';
    const course = point.course !== null ? point.course.toFixed(0) : '-';
    
    const time = new Date(point.timestamp * 1000).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    return `
        <div class="marker-info">
            <div class="marker-info-row">
                <span class="marker-info-label">Time:</span>
                <span class="marker-info-value">${time}</span>
            </div>
            <div class="marker-info-row">
                <span class="marker-info-label">Speed:</span>
                <span class="marker-info-value">${speed} m/s</span>
            </div>
            <div class="marker-info-row">
                <span class="marker-info-label">Speed (MPH):</span>
                <span class="marker-info-value">${speedMph}</span>
            </div>
            <div class="marker-info-row">
                <span class="marker-info-label">Direction:</span>
                <span class="marker-info-value">${course}° ${course !== '-' ? '↑' : ''}</span>
            </div>
            <div class="marker-info-row">
                <span class="marker-info-label">Elapsed:</span>
                <span class="marker-info-value">${point.time_sec.toFixed(1)}s</span>
            </div>
        </div>
    `;
}

function updateMarkerInfo(marker, currentPoint, nextPoint, progress) {
    if (!currentPoint) return;
    
    // Calculate interpolated data
    const interpolatedPoint = {
        ...currentPoint,
        timestamp: currentPoint.timestamp,
        time_sec: currentPoint.time_sec + (nextPoint ? (nextPoint.time_sec - currentPoint.time_sec) * progress : 0)
    };
    
    if (nextPoint) {
        // Speed interpolation
        if (currentPoint.speed !== null && nextPoint.speed !== null) {
            interpolatedPoint.speed = currentPoint.speed + (nextPoint.speed - currentPoint.speed) * progress;
        }
        
        // Course interpolation
        if (currentPoint.course !== null && nextPoint.course !== null) {
            interpolatedPoint.course = currentPoint.course + (nextPoint.course - currentPoint.course) * progress;
        }
    }
    
    // Update marker popup content
    const popup = marker.getPopup();
    if (popup) {
        popup.setContent(formatInfoContent(interpolatedPoint));
    } else {
        marker.bindPopup(formatInfoContent(interpolatedPoint), {
            offset: [0, -20],
            closeButton: false,
            className: 'marker-popup'
        }).openPopup();
    }
}

function formatTimeDisplay(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function updateProgressBar(currentTime, totalTime) {
    const progressBar = document.querySelector('.progress-bar');
    const progress = (currentTime / totalTime) * 100;
    progressBar.style.width = `${progress}%`;

    // Update time display
    document.getElementById('currentTime').textContent = formatTimeDisplay(currentTime);
    document.getElementById('totalTime').textContent = formatTimeDisplay(totalTime);
}

function animateMarker(currentTime) {
    if (!isPlaying) return;
    
    // Calculate frame interval
    if (animationState.lastFrameTime === 0) {
        animationState.lastFrameTime = currentTime;
    }
    
    const deltaTime = currentTime - animationState.lastFrameTime;
    animationState.lastFrameTime = currentTime;
    
    // Get and limit speed multiplier - add speed limit logic
    const requestedSpeed = parseFloat(document.getElementById('animationSpeed').value);
    let speedMultiplier = requestedSpeed;
    
    // Perform interpolation calculations when speed is high
    if (speedMultiplier > 20) {
        // Calculate additional interpolation frames
        const interpolationSteps = Math.ceil(speedMultiplier / 20);
        // Adjust actual speed to maintain smoothness
        speedMultiplier = speedMultiplier / interpolationSteps;
        
        // Perform multiple position updates to ensure smooth transition
        for (let i = 0; i < interpolationSteps; i++) {
            animationState.simulatedTime += (deltaTime / 1000) * speedMultiplier;
            updateMarkerPosition(animationState.simulatedTime);
        }
    } else {
        // Direct update at normal speed
        animationState.simulatedTime += (deltaTime / 1000) * speedMultiplier;
        updateMarkerPosition(animationState.simulatedTime);
    }
    
    // Check if reached the end
    if (animationState.simulatedTime < trackData[trackData.length - 1].time_sec) {
        animationFrame = requestAnimationFrame(animateMarker);
    } else {
        isPlaying = false;
        document.getElementById('playButton').textContent = 'Play';
        updatePath(trackData[trackData.length - 1], 1);
    }
}

// Find the current position based on elapsed time
let currentPoint = trackData[0];
let nextPoint = null;
let currentIndex = 0;

for (let i = 0; i < trackData.length - 1; i++) {
    if (trackData[i].time_sec <= elapsedSec && trackData[i + 1].time_sec > elapsedSec) {
        currentPoint = trackData[i];
        nextPoint = trackData[i + 1];
        currentIndex = i;
        break;
    }
}

if (nextPoint) {
    const segmentProgress = (elapsedSec - currentPoint.time_sec) / 
                          (nextPoint.time_sec - currentPoint.time_sec);
    
    // Use easing function for smooth movement
    const easeProgress = easeInOutQuad(segmentProgress);
    
    const lat = currentPoint.latitude + (nextPoint.latitude - currentPoint.latitude) * easeProgress;
    const lng = currentPoint.longitude + (nextPoint.longitude - currentPoint.longitude) * easeProgress;
    
    // Update marker position
    marker.setLatLng([lat, lng]);
}

// Add easing function for smoother animation
function easeInOutQuad(t) {
    return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
}

// Reset animation state
function resetAnimation() {
    isPlaying = false;
    cancelAnimationFrame(animationFrame);
    document.getElementById('playButton').textContent = 'Play';
    
    // Reset simulation clock state
    animationState.simulatedTime = 0;
    animationState.lastFrameTime = 0;
    
    if (marker && trackData.length > 0) {
        // Reset marker position
        marker.setLatLng([trackData[0].latitude, trackData[0].longitude]);
        // Clear path
        currentPathLayer.setLatLngs([]);
        completedPathLayer.setLatLngs([]);
        // Reset marker info
        updateMarkerInfo(marker, trackData[0], null, 0);
    }
    
    // Reset progress bar
    updateProgressBar(0, trackData[trackData.length - 1].time_sec);
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        initMap();
        // Hide marker initially
        if (marker) {
            marker.setOpacity(0);
        }
    }, 0);
    
    document.getElementById('playButton').addEventListener('click', () => {
        if (marker) {
            marker.setOpacity(1); // Show marker
        }
        toggleAnimation();
    });
    
    document.getElementById('resetButton').addEventListener('click', () => {
        resetAnimation();
        if (marker) {
            marker.setOpacity(0); // Hide marker
        }
    });
    
    const speedControl = document.getElementById('animationSpeed');
    const speedValue = document.getElementById('speedValue');
    speedControl.addEventListener('input', () => {
        speedValue.textContent = parseFloat(speedControl.value).toFixed(1) + 'x';
        // Update speed multiplier without resetting animation state
        animationState.speedMultiplier = parseFloat(speedControl.value);
    });

    // Initialize progress bar total time
    const totalTime = trackData[trackData.length - 1].time_sec;
    document.getElementById('totalTime').textContent = formatTimeDisplay(totalTime);

    // Add progress bar click event handler
    document.querySelector('.progress-container').addEventListener('click', (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const clickPosition = (e.clientX - rect.left) / rect.width;
        const totalTime = trackData[trackData.length - 1].time_sec;
        
        // Update simulated time to click position
        animationState.simulatedTime = totalTime * clickPosition;
        
        // If not currently playing, update position once
        if (!isPlaying) {
            animateMarker(performance.now());
        }
    });

    // Enhance progress bar interaction
    const progressWrapper = document.querySelector('.progress-bar-wrapper');
    const tooltip = document.querySelector('.progress-tooltip');

    progressWrapper.addEventListener('mousemove', (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const position = (e.clientX - rect.left) / rect.width;
        const totalTime = trackData[trackData.length - 1].time_sec;
        const previewTime = totalTime * position;
        
        tooltip.textContent = formatTimeDisplay(previewTime);
        tooltip.style.left = `${e.clientX - rect.left}px`;
    });

    progressWrapper.addEventListener('click', (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const clickPosition = (e.clientX - rect.left) / rect.width;
        const totalTime = trackData[trackData.length - 1].time_sec;
        
        animationState.simulatedTime = totalTime * clickPosition;
        
        if (!isPlaying) {
            animateMarker(performance.now());
        }
    });
});
    </script>
    <script id="trackData" type="application/json">
        <?= json_encode($trackData) ?>
    </script>
</body>
</html>
