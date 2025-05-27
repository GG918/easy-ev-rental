<?php
include_once 'auth.php';
$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();

// Check for error or success messages
$loginError = $_GET['login_error'] ?? '';
$registerError = $_GET['register_error'] ?? '';
$registerSuccess = isset($_GET['register_success']);
$showLogin = isset($_GET['show_login']); 
$returnUrl = $_GET['return_url'] ?? 'locations.php';

// Get form data for repopulating after errors
$formUsername = $_GET['username'] ?? '';
$formEmail = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome: eASY</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Audiowide">
    <link rel="stylesheet" href="css/index.css">
    <script src="js/auth-service.js"></script>
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

<?php if (!empty($loginError)): ?>
    <div class="notification error"><?php echo htmlspecialchars($loginError); ?></div>
<?php endif; ?>

<?php if (!empty($registerError)): ?>
    <div class="notification error"><?php echo htmlspecialchars($registerError); ?></div>
<?php endif; ?>

<?php if ($registerSuccess): ?>
    <div class="notification success">Registration successful! Welcome to eASY.</div>
<?php endif; ?>

<div class="hero-section">
</div>

<!-- Register Modal -->
<div id="registerModal" class="modal">
    <div class="modal-content">
        <button class="close" onclick="AuthService.closeModal('registerModal')">&times;</button>
        <h2>Create Account</h2>
        <form id="registerForm">
            <input type="text" name="username" id="reg_username" placeholder="Username" value="<?php echo htmlspecialchars($formUsername); ?>" required>
            <input type="email" name="email" id="reg_email" placeholder="Email" value="<?php echo htmlspecialchars($formEmail); ?>" required>
            <input type="password" name="password" id="reg_password" placeholder="Password" required>
            <button type="submit">Register</button>
            <div id="registerResult" class="form-result"></div>
        </form>
    </div>
</div>

<!-- Login Modal -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <button class="close" onclick="AuthService.closeModal('loginModal')">&times;</button>
        <h2>Login</h2>
        <form id="loginForm" data-return-url="<?php echo htmlspecialchars($returnUrl); ?>">
            <input type="text" name="username" id="login_username" placeholder="Username" value="<?php echo htmlspecialchars($formUsername); ?>" required>
            <input type="password" name="password" id="login_password" placeholder="Password" required>
            <button type="submit">Login</button>
            <div id="loginResult" class="form-result"></div>
            <p class="modal-register-link">Don't have an account? <a href="#" onclick="AuthService.closeModal('loginModal'); AuthService.openModal('registerModal'); return false;">Register here</a></p>
        </form>
    </div>
</div>

<!-- Maintenance Modal -->
<div id="maintenanceModal" class="modal">
    <div class="modal-content">
        <button class="close" onclick="AuthService.closeModal('maintenanceModal')">&times;</button>
        <h2>Log Maintenance</h2>
        <form action="maintenance.php" method="POST">
            <input type="number" name="vehicle_id" placeholder="Vehicle ID" required>
            <textarea name="description" placeholder="Maintenance Details" required></textarea>
            <input type="date" name="maintenance_date" required>
            <button type="submit">Log</button>
        </form>
    </div>
</div>

<footer>
    <div class="footer-content">
        <p>&copy; 2025 eASY - Your Sustainable Ride</p>
        <div class="footer-nav">
            <a href="#">Help</a>
            <a href="#" onclick="AuthService.openModal('maintenanceModal')">Log Maintenance</a>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-open login modal if show_login parameter exists
    <?php if ($showLogin): ?>
    AuthService.openModal('loginModal');
    <?php endif; ?>
});
</script>
</body>
</html>