<?php
require_once '../../backend/core/auth.php';
require_once '../../backend/includes/utils.php';

// Check if the user is already logged in
$isLoggedIn = isLoggedIn();
if ($isLoggedIn) {
    // If logged in, redirect to the main page
    header('Location: index.php');
    exit;
}

// Handle error messages
$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_credentials':
            $error = 'Invalid username or password.';
            break;
        case 'empty_fields':
            $error = 'Please fill in all required fields.';
            break;
        default:
            $error = 'An error occurred during login. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - eASY</title>
    <link rel="stylesheet" href="../public/css/index.css">
    <link rel="stylesheet" href="../public/css/auth.css">
</head>
<body>
    <header>
        <div class="logo"><a href="/web/frontend/views/index.php">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/web/frontend/views/locations.php">Find Vehicles</a></li>
            </ul>
        </nav>
    </header>

    <div class="main-content">
        <div class="auth-container">
            <div class="auth-card">
                <h2>Login</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form action="/backend/core/login_process.php" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Login</button>
                    </div>
                </form>
                
                <div class="auth-links">
                    <p>Don't have an account? <a href="/view/register">Register</a></p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - Your sustainable mobility choice</p>
            <div class="footer-nav">
                <a href="#">Help</a>
            </div>
        </div>
    </footer>
</body>
</html> 