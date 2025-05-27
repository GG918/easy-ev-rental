<?php
/**
 * Authentication System Core File
 * Provides user authentication, authorization, and session management functions.
 */

// Start session (if not already started)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
$config = require_once dirname(__DIR__) . '/config/config.php';

// Ensure Database class is available
require_once dirname(__DIR__) . '/core/Database.php';
$db = new Database();

/**
 * Check if the user is logged in.
 * @return bool True if logged in, false otherwise.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get the currently logged-in user.
 * @return array|null User information array or null if not logged in.
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Check if the user is an administrator.
 * @return bool True if admin, false otherwise.
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Validate user credentials and create a session.
 * @param string $username Username.
 * @param string $password Password.
 * @return array Response array with success and message fields.
 */
function login($username, $password) {
    global $db;
    
    // Validate input
    if (empty($username) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Please provide username and password.'
        ];
    }
    
    try {
        // Query user (remove role column)
        $sql = "SELECT id, username, password, email FROM users WHERE username = ?";
        $user = $db->fetchOne($sql, [$username]);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Username or password is incorrect.'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Username or password is incorrect.'
            ];
        }
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'user'; // Set default role
        $_SESSION['last_activity'] = time();
        
        return [
            'success' => true,
            'message' => 'Login successful.'
        ];
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred during login. Please try again.'
        ];
    }
}

/**
 * Register a new user.
 * @param string $username Username.
 * @param string $email Email address.
 * @param string $password Password.
 * @return array Response array with success and message fields.
 */
function register($username, $email, $password) {
    global $db;
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ];
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format.'
        ];
    }
    
    try {
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        $existingUser = $db->fetchOne($sql, [$username]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Username already exists.'
            ];
        }
        
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $existingEmail = $db->fetchOne($sql, [$email]);
        
        if ($existingEmail) {
            return [
                'success' => false,
                'message' => 'This email is already registered.'
            ];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user, created_at will be set automatically to current time
        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $db->execute($sql, [$username, $email, $hashedPassword]);
        
        $userId = $db->getLastInsertId();
        
        // Auto-login
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user'; // Use default role
        $_SESSION['last_activity'] = time();
        
        return [
            'success' => true,
            'message' => 'Registration successful.'
        ];
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred during registration. Please try again.'
        ];
    }
}

/**
 * Log out the user.
 * @return bool True if logout was successful.
 */
function logout() {
    // Clear all session data
    $_SESSION = [];
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    return true;
}

/**
 * Validate if the session has expired, and renew or log out.
 * @return bool True if the session is valid, false otherwise.
 */
function validateSession() {
    global $config;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $sessionLifetime = $config['app']['session_lifetime'] ?? 7200; // Default 2 hours
    $currentTime = time();
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    
    // Check if session has expired
    if ($currentTime - $lastActivity > $sessionLifetime) {
        logout();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = $currentTime;
    return true;
}

// Automatically validate session on page load
validateSession(); 

// Compatibility functions, call new functions using old names
// These functions are for compatibility with old code

/**
 * Compatibility function: Call new login function using old function name loginUser.
 * @param string $username Username.
 * @param string $password Password.
 * @return array Login result.
 */
function loginUser($username, $password) {
    return login($username, $password);
}

/**
 * Compatibility function: Call new register function using old function name registerUser.
 * @param string $username Username.
 * @param string $email Email address.
 * @param string $password Password.
 * @return array Registration result.
 */
function registerUser($username, $email, $password) {
    return register($username, $email, $password);
}

/**
 * Compatibility function: Call new logout function using old function name logoutUser.
 * @return bool True if logout was successful.
 */
function logoutUser() {
    return logout();
} 