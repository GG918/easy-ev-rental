<?php
include_once 'Database.php';
include_once 'auth.php';
include_once 'includes/helpers.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();

// Redirect to login page if not logged in
if (!$isLoggedIn) {
    header('Location: index.php?login_required=1');
    exit;
}

$errorMessage = '';
$successMessage = '';
$reservations = [];

// Get filter parameters
$filterVehicleType = $_GET['vehicle_type'] ?? 'all';
$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? 'all';

try {
    // Get database connection
    $db = new Database();
    
    // Get user's reservation history - with filters
    $reservations = $db->getUserReservationHistory($currentUser['id']);
    
    // Apply filters
    if (!empty($reservations)) {
        $reservations = array_filter($reservations, function($reservation) use ($filterVehicleType, $filterDate, $filterStatus) {
            // Vehicle Type filter
            if ($filterVehicleType !== 'all' && $filterVehicleType !== 'scooter') {
                return false;
            }
            
            // Date filter
            if ($filterDate !== '') {
                $reservationDate = date('Y-m-d', strtotime($reservation['start_date']));
                if ($reservationDate !== $filterDate) {
                    return false;
                }
            }
            
            // Status filter
            if ($filterStatus !== 'all' && $reservation['status'] !== $filterStatus) {
                return false;
            }
            
            return true;
        });
    }
    
    // Get current active reservation
    $activeReservation = $db->getUserActiveReservation($currentUser['id']);
    
    // Get in-progress trip
    $activeOrder = $db->getUserActiveOrder($currentUser['id']);
} catch (Exception $e) {
    $errorMessage = 'Failed to load reservation history: ' . $e->getMessage();
}

// Cancel reservation handling
if (isset($_POST['cancel_reservation']) && isset($_POST['booking_id']) && isset($_POST['vehicle_id'])) {
    $bookingId = $_POST['booking_id'];
    $vehicleId = $_POST['vehicle_id'];
    
    try {
        $result = $db->cancelReservation($bookingId, $vehicleId);
        if ($result) {
            $successMessage = 'Reservation cancelled successfully';
            // Re-fetch reservation history
            $reservations = $db->getUserReservationHistory($currentUser['id']);
            $activeReservation = $db->getUserActiveReservation($currentUser['id']);
        } else {
            $errorMessage = 'Failed to cancel reservation';
        }
    } catch (Exception $e) {
        $errorMessage = 'Error cancelling reservation: ' . $e->getMessage();
    }
}

// Start trip handling
if (isset($_POST['start_trip']) && isset($_POST['booking_id']) && isset($_POST['vehicle_id'])) {
    $bookingId = $_POST['booking_id'];
    $vehicleId = $_POST['vehicle_id'];
    
    try {
        $result = $db->startVehicleOrder($bookingId, $vehicleId);
        if ($result) {
            $successMessage = 'Trip started successfully';
            // Re-fetch reservation history
            $reservations = $db->getUserReservationHistory($currentUser['id']);
            $activeReservation = null;
            $activeOrder = $db->getUserActiveOrder($currentUser['id']);
        } else {
            $errorMessage = 'Failed to start trip';
        }
    } catch (Exception $e) {
        $errorMessage = 'Error starting trip: ' . $e->getMessage();
    }
}

// End trip handling
if (isset($_POST['end_trip']) && isset($_POST['booking_id']) && isset($_POST['vehicle_id'])) {
    $bookingId = $_POST['booking_id'];
    $vehicleId = $_POST['vehicle_id'];
    
    try {
        $result = $db->completeVehicleOrder($bookingId, $vehicleId);
        if ($result) {
            $successMessage = 'Trip completed successfully';
            // Re-fetch reservation history
            $reservations = $db->getUserReservationHistory($currentUser['id']);
            $activeOrder = null;
        } else {
            $errorMessage = 'Failed to complete trip';
        }
    } catch (Exception $e) {
        $errorMessage = 'Error completing trip: ' . $e->getMessage();
    }
}

// Helper function: format status display
function formatStatus($status) {
    $statusClasses = [
        'reserved' => 'status-reserved',
        'in_progress' => 'status-in_progress',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];
    
    $statusLabels = [
        'reserved' => 'Reserved',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    
    $class = isset($statusClasses[$status]) ? $statusClasses[$status] : 'status-unknown';
    $label = isset($statusLabels[$status]) ? $statusLabels[$status] : ucfirst($status);
    
    return "<span class=\"{$class}\">{$label}</span>";
}

// Helper function: format time range
function formatTimeRange($startTime, $endTime) {
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    
    $dateFormat = 'M j, Y'; // Date format
    $timeFormat = 'g:i A'; // Time format
    
    // Return date and time as an array
    return [
        'date' => $start->format($dateFormat),
        'time' => $start->format($timeFormat) . ' - ' . $end->format($timeFormat)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - eASY</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/booking.css">
    <link rel="stylesheet" href="css/my_reservations.css">
</head>
<body>
    <header>
        <div class="logo"><a href="index.php">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="locations.php">Find Vehicles Here!</a></li>
            </ul>
        </nav>
        <div class="topright">
            <ul>
                <?php if ($isLoggedIn): ?>
                    <li class="user-menu">
                        <button><?php echo htmlspecialchars($currentUser['username']); ?></button>
                        <div class="user-menu-content">
                            <a href="#">My Profile</a>
                            <a href="my_reservations.php">My Reservations</a>
                            <a href="track.php">Track</a>
                            <a href="logout.php">Logout</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><button onclick="AuthService.openModal('registerModal')">Register</button></li>
                    <li><button onclick="AuthService.openModal('loginModal')">Login</button></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <div class="main-content">
        <div class="card">
            <h2>My Reservations</h2>
            
            <!-- Filter form -->
            <form class="filter-form" method="get">
                <div class="filter-group">
                    <label>Vehicle Type:</label>
                    <select name="vehicle_type">
                        <option value="all" <?php echo $filterVehicleType === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="scooter" <?php echo $filterVehicleType === 'scooter' ? 'selected' : ''; ?>>Scooter</option>
                        <option value="e-bike" <?php echo $filterVehicleType === 'e-bike' ? 'selected' : ''; ?>>E-Bike</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date:</label>
                    <input type="date" name="date" value="<?php echo $filterDate; ?>">
                </div>
                
                <div class="filter-group">
                    <label>Status:</label>
                    <select name="status">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="reserved" <?php echo $filterStatus === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                        <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="filter-apply">Apply Filters</button>
                    <button type="button" class="filter-reset" onclick="window.location.href='my_reservations.php'">Reset</button>
                </div>
            </form>

            <?php if (!empty($errorMessage)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="message success">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($activeReservation): ?>
                <div class="card active-reservation">
                    <h3>Active Reservation</h3>
                    <p><strong>Type:</strong> <?php echo ucfirst($activeReservation['vehicle_type'] ?? 'Scooter'); ?></p>
                    <?php $activeTimeRange = formatTimeRange($activeReservation['start_date'], $activeReservation['end_date']); ?>
                    <p><strong>Date:</strong> <?php echo $activeTimeRange['date']; ?></p>
                    <p><strong>Time:</strong> <?php echo $activeTimeRange['time']; ?></p>
                    
                    <?php if (!empty($activeReservation['expiry_time'])): ?>
                        <p><strong>Expires:</strong> <?php echo (new DateTime($activeReservation['expiry_time']))->format('M j, Y g:i A'); ?></p>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="booking_id" value="<?php echo $activeReservation['id']; ?>">
                            <input type="hidden" name="vehicle_id" value="<?php echo $activeReservation['vehicle_id']; ?>">
                            <button type="submit" name="start_trip" class="start-btn">Start Trip</button>
                        </form>
                        
                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                            <input type="hidden" name="booking_id" value="<?php echo $activeReservation['id']; ?>">
                            <input type="hidden" name="vehicle_id" value="<?php echo $activeReservation['vehicle_id']; ?>">
                            <button type="submit" name="cancel_reservation" class="cancel-btn">Cancel Reservation</button>
                        </form>
                        
                        <button onclick="window.open('https://www.google.com/maps/dir/?api=1&destination=<?php echo htmlspecialchars($activeReservation['location']); ?>&travelmode=walking', '_blank')" class="navigate-btn">Navigate</button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($activeOrder): ?>
                <div class="card active-trip">
                    <h3>Active Trip</h3>
                    <p><strong>Type:</strong> <?php echo ucfirst($activeOrder['vehicle_type'] ?? 'Scooter'); ?></p>
                    <p><strong>Started:</strong> <?php echo (new DateTime($activeOrder['start_date']))->format('M j, Y g:i A'); ?></p>
                    
                    <div class="action-buttons">
                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to end this trip?');">
                            <input type="hidden" name="booking_id" value="<?php echo $activeOrder['id']; ?>">
                            <input type="hidden" name="vehicle_id" value="<?php echo $activeOrder['vehicle_id']; ?>">
                            <button type="submit" name="end_trip" class="end-btn">End Trip</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <p>You don't have any reservation history yet.</p>
                    <a href="locations.php">Find a vehicle to reserve</a>
                </div>
            <?php else: ?>
                <h3>Reservation History</h3>
                <table class="reservation-table">
                    <thead>
                        <tr>
                            <th>Vehicle Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php $timeRange = formatTimeRange($reservation['start_date'], $reservation['end_date']); ?>
                            <tr class="<?php 
                                if ($activeReservation && $activeReservation['id'] == $reservation['id']) echo 'active-reservation';
                                else if ($activeOrder && $activeOrder['id'] == $reservation['id']) echo 'active-trip';
                            ?>">
                                <td><?php echo ucfirst($reservation['vehicle_type'] ?? 'Scooter'); ?></td>
                                <td><?php echo $timeRange['date']; ?></td>
                                <td><?php echo $timeRange['time']; ?></td>
                                <td><?php echo formatStatus($reservation['status']); ?></td>
                                <td><?php echo (new DateTime($reservation['created_at']))->format('M j, Y'); ?></td>
                                <td>
                                    <?php if ($reservation['status'] == 'reserved'): ?>
                                        <div class="action-buttons">
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                                                <input type="hidden" name="vehicle_id" value="<?php echo $reservation['vehicle_id']; ?>">
                                                <button type="submit" name="start_trip" class="start-btn">Start</button>
                                            </form>
                                            
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                                                <input type="hidden" name="vehicle_id" value="<?php echo $reservation['vehicle_id']; ?>">
                                                <button type="submit" name="cancel_reservation" class="cancel-btn">Cancel</button>
                                            </form>
                                        </div>
                                    <?php elseif ($reservation['status'] == 'in_progress'): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $reservation['vehicle_id']; ?>">
                                            <button type="submit" name="end_trip" class="end-btn">End Trip</button>
                                        </form>
                                    <?php else: ?>
                                        <em>No actions available</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2023 eASY Electric Vehicle Rental System</p>
            <div class="footer-nav">
                <a href="about.php">About Us</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
    </footer>

    <script src="js/auth-service.js"></script>
    <script src="js/my_reservations.js"></script>
</body>
</html>
