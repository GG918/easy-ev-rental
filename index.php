<?php
/**
 * 主入口文件
 * 检测URL路径并路由到相应的视图
 */

// 获取请求URI
$requestUri = $_SERVER['REQUEST_URI'];

// 检查是否是view路径请求
if (preg_match('~^/view/~', $requestUri)) {
    // 路由到视图路由器
    include_once __DIR__ . '/view/index.php';
} else {
    // 直接包含前端首页
    include_once __DIR__ . '/frontend/views/index.php';
}
?>