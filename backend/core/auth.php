<?php
/**
 * 认证系统核心文件
 * 提供用户认证、授权和会话管理功能
 */

// 启动会话（如果尚未启动）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 加载配置
$config = require_once dirname(__DIR__) . '/config/config.php';

// 确保数据库类可用
require_once dirname(__DIR__) . '/core/Database.php';
$db = new Database();

/**
 * 检查用户是否已登录
 * @return bool 是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 获取当前登录用户
 * @return array|null 用户信息或null（如果未登录）
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
 * 检查用户是否为管理员
 * @return bool 是否为管理员
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * 验证用户凭据并创建会话
 * @param string $username 用户名
 * @param string $password 密码
 * @return array 响应数组，包含success和message字段
 */
function login($username, $password) {
    global $db;
    
    // 验证输入
    if (empty($username) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Please provide username and password.'
        ];
    }
    
    try {
        // 查询用户 (移除role列)
        $sql = "SELECT id, username, password, email FROM users WHERE username = ?";
        $user = $db->fetchOne($sql, [$username]);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Username or password is incorrect.'
            ];
        }
        
        // 验证密码
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Username or password is incorrect.'
            ];
        }
        
        // 创建会话
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'user'; // 设置默认角色
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
 * 注册新用户
 * @param string $username 用户名
 * @param string $email 电子邮件
 * @param string $password 密码
 * @return array 响应数组，包含success和message字段
 */
function register($username, $email, $password) {
    global $db;
    
    // 验证输入
    if (empty($username) || empty($email) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ];
    }
    
    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format.'
        ];
    }
    
    try {
        // 检查用户名是否已存在
        $sql = "SELECT id FROM users WHERE username = ?";
        $existingUser = $db->fetchOne($sql, [$username]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Username already exists.'
            ];
        }
        
        // 检查邮箱是否已存在
        $sql = "SELECT id FROM users WHERE email = ?";
        $existingEmail = $db->fetchOne($sql, [$email]);
        
        if ($existingEmail) {
            return [
                'success' => false,
                'message' => 'This email is already registered.'
            ];
        }
        
        // 哈希密码
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // 插入新用户，created_at会自动设置为当前时间
        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $db->execute($sql, [$username, $email, $hashedPassword]);
        
        $userId = $db->getLastInsertId();
        
        // 自动登录
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user'; // 使用默认角色
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
 * 注销用户
 * @return bool 是否成功注销
 */
function logout() {
    // 清除所有会话数据
    $_SESSION = [];
    
    // 销毁会话cookie
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
    
    // 销毁会话
    session_destroy();
    
    return true;
}

/**
 * 验证会话是否已过期，并续期或注销
 * @return bool 会话是否有效
 */
function validateSession() {
    global $config;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $sessionLifetime = $config['app']['session_lifetime'] ?? 7200; // 默认2小时
    $currentTime = time();
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    
    // 检查会话是否过期
    if ($currentTime - $lastActivity > $sessionLifetime) {
        logout();
        return false;
    }
    
    // 更新最后活动时间
    $_SESSION['last_activity'] = $currentTime;
    return true;
}

// 当页面加载时自动验证会话
validateSession(); 

// 兼容性函数，使用旧名称调用新函数
// 这些函数是为了与旧代码兼容

/**
 * 兼容函数：使用旧函数名loginUser调用新函数login
 * @param string $username 用户名
 * @param string $password 密码
 * @return array 登录结果
 */
function loginUser($username, $password) {
    return login($username, $password);
}

/**
 * 兼容函数：使用旧函数名registerUser调用新函数register
 * @param string $username 用户名
 * @param string $email 电子邮件
 * @param string $password 密码
 * @return array 注册结果
 */
function registerUser($username, $email, $password) {
    return register($username, $email, $password);
}

/**
 * 兼容函数：使用旧函数名logoutUser调用新函数logout
 * @return bool 是否成功注销
 */
function logoutUser() {
    return logout();
} 