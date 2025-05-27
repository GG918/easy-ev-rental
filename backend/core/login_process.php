<?php
// 处理登录请求的页面

// 加载认证函数和工具函数
require_once 'auth.php';
require_once '../includes/utils.php';

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST数据
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $return_url = $_POST['return_url'] ?? url('/view/index');
    
    // 尝试登录
    $result = login($username, $password);
    
    // 如果是AJAX请求
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // 普通表单提交
    if ($result['success']) {
        // 登录成功，重定向
        header("Location: $return_url");
        exit;
    } else {
        // 登录失败，返回错误
        $error = urlencode($result['message']);
        $username = urlencode($username);
        header("Location: " . url("/view/index?show_login=1&login_error=$error&username=$username&return_url=$return_url"));
        exit;
    }
} else {
    // 非POST请求，重定向到首页
    header('Location: ' . url('/view/index'));
    exit;
} 