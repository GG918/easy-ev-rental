<?php
include_once 'auth.php';

// Handle AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Use Auth class to handle login request
    $result = $auth->login($username, $password);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} else {
    // If GET request, display login page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/login.css">
    <script src="js/auth-service.js"></script>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <form id="loginForm">
            <input type="text" id="username" name="username" placeholder="Enter Username" required>
            <input type="password" id="password" name="password" placeholder="Enter Password" required>
            <button type="submit">Login</button>
            <p id="loginMessage" class="form-result"></p>
            <p class="register-link">Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('loginForm').addEventListener('submit', function(event) {
                AuthService.submitLoginForm(event, 'locations.php');
            });
        });
    </script>
</body>
</html>
<?php
}
?>

