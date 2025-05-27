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
        case 'invalid_credentials':
            $error = '用户名或密码不正确';
            break;
        case 'empty_fields':
            $error = '请填写所有必填字段';
            break;
        default:
            $error = '登录时发生错误，请重试';
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - eASY</title>
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
                <h2>登录</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form action="/backend/core/login_process.php" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">登录</button>
                    </div>
                </form>
                
                <div class="auth-links">
                    <p>还没有账号？<a href="/view/register">注册</a></p>
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