<?php
// 处理注销请求的页面

// 加载认证函数和工具函数
require_once 'auth.php';
require_once '../includes/utils.php';

// 执行注销
$result = logout();

// 重定向到首页
header('Location: ' . url('/view/index?logout=true'));
exit; 