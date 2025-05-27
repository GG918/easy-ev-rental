<?php
// 500 错误页面
require_once '../../backend/includes/utils.php';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器错误 - eASY</title>
    <link rel="stylesheet" href="<?php echo url('/frontend/public/css/index.css'); ?>">
    <style>
        .error-container {
            text-align: center;
            margin: 50px auto;
            max-width: 800px;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #e74c3c;
            margin: 0;
            line-height: 1;
        }
        
        .error-message {
            font-size: 24px;
            margin: 20px 0;
            color: #333;
        }
        
        .error-desc {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .back-button:hover {
            background-color: #0d8aee;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo"><a href="<?php echo url('/view/index'); ?>">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="<?php echo url('/view/locations'); ?>">查找车辆</a></li>
            </ul>
        </nav>
    </header>

    <div class="main-content">
        <div class="error-container">
            <h1 class="error-code">500</h1>
            <h2 class="error-message">服务器内部错误</h2>
            <p class="error-desc">抱歉，服务器遇到了意外情况，无法完成您的请求。请稍后再试。</p>
            <a href="<?php echo url('/view/index'); ?>" class="back-button">返回首页</a>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - 您的可持续出行选择</p>
            <div class="footer-nav">
                <a href="#">帮助</a>
                <a href="<?php echo url('/view/maintenance'); ?>">记录维护</a>
            </div>
        </div>
    </footer>
</body>
</html> 