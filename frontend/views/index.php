<?php
// Include authentication files
require_once __DIR__ . '/../../backend/core/auth.php';
require_once __DIR__ . '/../../backend/includes/utils.php';

$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();

// Check for error or success messages
$loginError = $_GET['login_error'] ?? '';
$registerError = $_GET['register_error'] ?? '';
$registerSuccess = isset($_GET['register_success']);
$showLogin = isset($_GET['show_login']); 
$returnUrl = $_GET['return_url'] ?? '/web/frontend/views/locations.php';

// Get form data for repopulation after errors
$formUsername = $_GET['username'] ?? '';
$formEmail = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to eASY</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Audiowide">
    <link rel="stylesheet" href="/web/frontend/public/css/index.css">
    <script src="/web/frontend/public/js/auth-service.js"></script>
</head>
<body>
    <header>
        <div class="logo"><a href="/web/frontend/views/index.php">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/web/frontend/views/locations.php">Find Vehicles</a></li>
            </ul>
        </nav>
        <div class="topright">
            <ul>
                <?php if ($isLoggedIn): ?>
                    <li class="user-menu">
                        <button><?php echo htmlspecialchars($currentUser['username']); ?></button>
                        <div class="user-menu-content">
                            <a href="#">My Profile</a>
                            <a href="/web/frontend/views/my_reservations.php">My Reservations</a>
                            <a href="/web/backend/core/logout_process.php">Logout</a>
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
                <p class="modal-register-link">Don't have an account? <a href="#" onclick="AuthService.closeModal('loginModal'); AuthService.openModal('registerModal'); return false;">Click to register</a></p>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - Your sustainable mobility choice</p>
            <div class="footer-nav">
                <a href="#">Help</a>
                <a href="<?php echo url('/frontend/views/maintenance.php'); ?>">Log Maintenance</a>
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