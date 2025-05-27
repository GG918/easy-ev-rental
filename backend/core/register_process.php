<?php
// 处理注册请求的页面

// 加载认证函数
require_once 'auth.php';
require_once '../includes/utils.php';

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST数据
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 尝试注册
    $result = register($username, $email, $password);
    
    // 如果是AJAX请求
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // 普通表单提交
    if ($result['success']) {
        // 注册成功，重定向到首页并显示成功消息
        header('Location: ' . url('/view/index') . '?register_success=1');
        exit;
    } else {
        // 注册失败，返回错误
        $error = urlencode($result['message']);
        $username = urlencode($username);
        $email = urlencode($email);
        header("Location: " . url('/view/index') . "?register_error=$error&username=$username&email=$email");
        exit;
    }
} else {
    // 非POST请求，重定向到首页
    header('Location: ' . url('/view/index'));
    exit;
} 