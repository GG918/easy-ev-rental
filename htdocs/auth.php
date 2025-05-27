<?php
session_start();
include_once 'Database.php';

/**
 * Auth Class - Handles all user authentication related operations
 */
class Auth {
    private $db;
    
    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Auth DB connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process authentication request
     * @param string $action Action type (login, register, logout)
     * @param array $data Request data
     * @return array Result
     */
    public function processRequest($action, $data = []) {
        switch($action) {
            case 'login':
                return $this->login($data['username'] ?? '', $data['password'] ?? '');
            case 'register':
                return $this->register(
                    $data['username'] ?? '',
                    $data['email'] ?? '',
                    $data['password'] ?? ''
                );
            case 'logout':
                return $this->logout();
            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }
    }
    
    /**
     * Validate user input
     * @param array $data User data
     * @param string $type Validation type
     * @return array Validation result
     */
    public function validateInput($data, $type) {
        $errors = [];
        
        // Common validation
        if ($type === 'login' || $type === 'register') {
            if (empty($data['username'])) {
                $errors[] = 'Username is required';
            }
            
            if (empty($data['password'])) {
                $errors[] = 'Password is required';
            }
        }
        
        // Registration specific validation
        if ($type === 'register') {
            if (empty($data['email'])) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            
            if (strlen($data['username']) < 3 || strlen($data['username']) > 30) {
                $errors[] = 'Username must be between 3 and 30 characters';
            }
            
            if (strlen($data['password']) < 6) {
                $errors[] = 'Password must be at least 6 characters long';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * User login
     * @param string $username Username
     * @param string $password Password
     * @return array Login result
     */
    public function login($username, $password) {
        // Validate input
        $validation = $this->validateInput(
            ['username' => $username, 'password' => $password],
            'login'
        );
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => implode('. ', $validation['errors'])
            ];
        }
        
        try {
            $result = $this->db->fetchAll(
                "SELECT id, username, password FROM users WHERE username = ?", 
                [$username]
            );
            
            if (count($result) == 1) {
                $user = $result[0];
                
                if (password_verify($password, $user['password']) || $password == $user['password']) {
                    // Login successful, set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['logged_in'] = true;
                    
                    return [
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username']
                        ]
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error, please try again later'
            ];
        }
    }
    
    /**
     * User registration
     * @param string $username Username
     * @param string $email Email
     * @param string $password Password
     * @return array Registration result
     */
    public function register($username, $email, $password) {
        // Validate input
        $validation = $this->validateInput(
            ['username' => $username, 'email' => $email, 'password' => $password],
            'register'
        );
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => implode('. ', $validation['errors'])
            ];
        }
        
        try {
            // Check if username already exists
            $existingUser = $this->db->fetchAll(
                "SELECT id FROM users WHERE username = ?", 
                [$username]
            );
            
            if (count($existingUser) > 0) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            
            // Check if email already exists
            $existingEmail = $this->db->fetchAll(
                "SELECT id FROM users WHERE email = ?", 
                [$email]
            );
            
            if (count($existingEmail) > 0) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $this->db->execute(
                "INSERT INTO users (username, email, password) VALUES (?, ?, ?)", 
                [$username, $email, $hashedPassword]
            );
            
            // Get newly inserted user ID
            $userId = $this->db->getConnection()->lastInsertId();
            
            if ($userId) {
                // Auto login new user
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['logged_in'] = true;
                
                return [
                    'success' => true, 
                    'message' => 'Registration successful',
                    'user' => [
                        'id' => $userId,
                        'username' => $username
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Registration failed, please try again later'];
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error, please try again later'];
        }
    }
    
    /**
     * User logout
     * @return array Logout result
     */
    public function logout() {
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy session
        session_destroy();
        
        // Ensure to clear cookies as well
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        return ['success' => true, 'message' => 'Logout successful'];
    }
    
    /**
     * Check if user is logged in
     * @return bool Is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current logged in user information
     * @return array|null User information
     */
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'isLoggedIn' => true
            ];
        }
        return null;
    }
    
    /**
     * Require user to be logged in to access
     * @return bool Is logged in
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        return true;
    }
}

// Create global Auth instance for easy access
$auth = new Auth();

// Keep compatibility functions for legacy code
function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}

function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

function loginUser($username, $password) {
    global $auth;
    $result = $auth->login($username, $password);
    return $result['success'];
}

function registerUser($username, $email, $password) {
    global $auth;
    return $auth->register($username, $email, $password);
}

function logoutUser() {
    global $auth;
    $auth->logout();
}
?>
