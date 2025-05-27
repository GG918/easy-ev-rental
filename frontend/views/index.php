<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "Debug Info from frontend/views/index.php:\n";
echo "------------------------------------------\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'Not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Not set') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "\n";
echo "\n";
// 确保 utils.php 被正确加载
$utils_path = __DIR__ . '/../../backend/includes/utils.php';
if (file_exists($utils_path)) {
    require_once $utils_path;
    echo "utils.php loaded successfully from: " . realpath($utils_path) . "\n";
    echo "get_base_path() returns: '" . get_base_path() . "'\n";
    echo "url('/frontend/public/css/index.css') returns: '" . url('/frontend/public/css/index.css') . "'\n";
} else {
    echo "ERROR: utils.php not found at: " . $utils_path . "\n";
}
echo "</pre>";

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
$returnUrl = $_GET['return_url'] ?? url('/view/locations');

// Get form data for repopulation after errors
$formUsername = $_GET['username'] ?? '';
$formEmail = $_GET['email'] ?? '';

// Print path debug info (development only)
$isDebug = isset($_GET['debug']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>欢迎使用 eASY</title>
    <link rel="stylesheet" href="../public/css/index.css">
    <script src="../public/js/auth-service.js"></script>
</head>
<body>
    <header>
        <div class="logo"><a href="/view/index">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/view/locations">查找车辆</a></li>
            </ul>
        </nav>
        <div class="topright">
            <ul>
                <?php if ($isLoggedIn): ?>
                    <li class="user-menu">
                        <button><?php echo htmlspecialchars($currentUser['username']); ?></button>
                        <div class="user-menu-content">
                            <a href="#">我的资料</a>
                            <a href="/view/my_reservations">我的预订</a>
                            <a href="../../backend/core/logout_process.php">登出</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><button onclick="AuthService.openModal('registerModal')">注册</button></li>
                    <li><button onclick="AuthService.openModal('loginModal')">登录</button></li>
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
        <div class="notification success">注册成功！欢迎使用 eASY。</div>
    <?php endif; ?>

    <!-- Full-screen hero image with minimal overlay text -->
    <div class="main-content">
        <div class="hero">
            <h1>欢迎使用 eASY</h1>
            <p>您的可持续出行选择</p>
            <a href="/view/locations" class="cta-button">立即查找车辆</a>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="AuthService.closeModal('registerModal')">&times;</button>
            <h2>创建账户</h2>
            <form id="registerForm">
                <input type="text" name="username" id="reg_username" placeholder="用户名" value="<?php echo htmlspecialchars($formUsername); ?>" required>
                <input type="email" name="email" id="reg_email" placeholder="邮箱" value="<?php echo htmlspecialchars($formEmail); ?>" required>
                <input type="password" name="password" id="reg_password" placeholder="密码" required>
                <button type="submit">注册</button>
                <div id="registerResult" class="form-result"></div>
            </form>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="AuthService.closeModal('loginModal')">&times;</button>
            <h2>登录</h2>
            <form id="loginForm" data-return-url="<?php echo htmlspecialchars($returnUrl); ?>">
                <input type="text" name="username" id="login_username" placeholder="用户名" value="<?php echo htmlspecialchars($formUsername); ?>" required>
                <input type="password" name="password" id="login_password" placeholder="密码" required>
                <button type="submit">登录</button>
                <div id="loginResult" class="form-result"></div>
                <p class="modal-register-link">没有账户？ <a href="#" onclick="AuthService.closeModal('loginModal'); AuthService.openModal('registerModal'); return false;">点击注册</a></p>
            </form>
        </div>
    </div>

    <!-- Maintenance Modal -->
    <div id="maintenanceModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="AuthService.closeModal('maintenanceModal')">&times;</button>
            <h2>Record Maintenance</h2>
            <form action="<?php echo url('/api/maintenance'); ?>" method="POST">
                <input type="number" name="vehicle_id" placeholder="Vehicle ID" required>
                <textarea name="description" placeholder="Maintenance Details" required></textarea>
                <input type="date" name="maintenance_date" value="<?php echo date('Y-m-d'); ?>" required>
                <button type="submit">Submit Record</button>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - 您的可持续出行选择</p>
            <div class="footer-nav">
                <a href="#">帮助</a>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 如果有show_login参数，自动打开登录模态框
        <?php if ($showLogin): ?>
        AuthService.openModal('loginModal');
        <?php endif; ?>
    });
    </script>
    
    <?php if ($isDebug): ?>
    <div style="margin: 50px; padding: 20px; background: #f8f8f8; border: 1px solid #ddd; font-family: monospace;">
        <h3>Path Debug Information</h3>
        <pre><?php 
            echo "BASE_PATH: " . get_base_path() . "\n";
            echo "URL('/view/index'): " . url('/view/index') . "\n";
            echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "\n";
            echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'Not set') . "\n";
            echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
        ?></pre>
        <p>
            <a href="<?php echo url('/debug/path_test.php'); ?>">View Complete Path Information</a> | 
            <a href="<?php echo url('/debug/apache_test.php'); ?>">Apache Configuration Test</a>
        </p>
    </div>
    <?php endif; ?>
</body>
</html> 