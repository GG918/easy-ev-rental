<?php
// Include authentication files
require_once '../../backend/core/auth.php';
// Include necessary utility functions
require_once '../../backend/includes/utils.php';

$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();

// Redirect if not logged in
if (!$isLoggedIn) {
    header('Location: /view/index?show_login=1&return_url=/view/maintenance');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Maintenance - eASY</title>
    <link rel="stylesheet" href="../public/css/index.css">
    <link rel="stylesheet" href="../public/css/maintenance.css">
</head>
<body>
    <header>
        <div class="logo"><a href="/view/index">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/view/locations">Find Vehicles</a></li>
                <li><a href="/view/maintenance" class="active">Vehicle Maintenance</a></li>
            </ul>
        </nav>
        <div class="topright">
            <ul>
                <?php if ($isLoggedIn): ?>
                    <li class="user-menu">
                        <button><?php echo htmlspecialchars($currentUser['username']); ?></button>
                        <div class="user-menu-content">
                            <a href="#">My Profile</a>
                            <a href="/view/my_reservations">My Reservations</a>
                            <a href="../../backend/core/logout_process.php">Logout</a>
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
        <div class="page-header">
            <h1>Vehicle Maintenance Records</h1>
            <?php if ($isAdmin): ?>
            <button id="add-maintenance-btn" class="primary-button">Add Maintenance Record</button>
            <?php endif; ?>
        </div>

        <div class="notification-container">
            <div id="notification" class="notification hidden"></div>
        </div>

        <div class="maintenance-list">
            <div class="filters">
                <select id="status-filter">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
                <input type="date" id="date-filter" placeholder="Filter by date">
                <button id="apply-filters" class="secondary-button">Apply Filters</button>
                <button id="reset-filters" class="secondary-button">Reset</button>
            </div>

            <div class="table-container">
                <table id="maintenance-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle ID</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="maintenance-data">
                        <!-- Will be populated by JavaScript -->
                        <tr>
                            <td colspan="6" class="loading-message">Loading maintenance records...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Maintenance Record Modal -->
    <div id="maintenance-modal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="MaintenanceService.closeModal()">&times;</button>
            <h2>Add Maintenance Record</h2>
            <form id="maintenance-form">
                <div class="form-group">
                    <label for="vehicle-id">Vehicle ID</label>
                    <input type="number" id="vehicle-id" name="vehicle_id" required min="1">
                </div>
                <div class="form-group">
                    <label for="maintenance-date">Maintenance Date</label>
                    <input type="date" id="maintenance-date" name="maintenance_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="description">Maintenance Description</label>
                    <textarea id="description" name="description" required rows="4"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-button">Submit Record</button>
                    <button type="button" class="cancel-button" onclick="MaintenanceService.closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm Complete Maintenance Modal -->
    <div id="complete-modal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="MaintenanceService.closeConfirmModal()">&times;</button>
            <h2>Confirm Maintenance Completion</h2>
            <p>Are you sure you want to mark this maintenance record as completed?</p>
            <p>The vehicle status will be changed to "Available".</p>
            <div class="form-actions">
                <button id="confirm-complete-btn" class="submit-button">Confirm Completion</button>
                <button class="cancel-button" onclick="MaintenanceService.closeConfirmModal()">Cancel</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - Your sustainable mobility choice</p>
            <div class="footer-nav">
                <a href="#">Help</a>
                <a href="/view/maintenance">Log Maintenance</a>
            </div>
        </div>
    </footer>

    <script src="../public/js/auth-service.js"></script>
    <script src="../public/js/maintenance-service.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            MaintenanceService.init();
        });
    </script>
</body>
</html> 