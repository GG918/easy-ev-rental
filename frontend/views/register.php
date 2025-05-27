<?php
require_once '../../backend/core/auth.php';
require_once '../../backend/includes/utils.php';

// 检查用户是否已登录
$isLoggedIn = isLoggedIn();
if ($isLoggedIn) {
    // 如果已登录，重定向到首页
    header('Location: index.php');
    exit;
}

// 处理错误消息
$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'username_exists':
            $error = '用户名已存在';
            break;
        case 'password_mismatch':
            $error = '两次输入的密码不匹配';
            break;
        case 'empty_fields':
            $error = '请填写所有必填字段';
            break;
        default:
            $error = '注册时发生错误，请重试';
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - eASY</title>
    <link rel="stylesheet" href="../public/css/index.css">
    <link rel="stylesheet" href="../public/css/auth.css">
</head>
<body>
    <header>
        <div class="logo"><a href="/view/index">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/view/locations">查找车辆</a></li>
            </ul>
        </nav>
    </header>

    <div class="main-content">
        <div class="auth-container">
            <div class="auth-card">
                <h2>注册</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form action="/backend/core/register_process.php" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">电子邮箱</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">确认密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">注册</button>
                    </div>
                </form>
                
                <div class="auth-links">
                    <p>已有账号？<a href="/view/login">登录</a></p>
                </div>
            </div>
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
</body>
</html> 