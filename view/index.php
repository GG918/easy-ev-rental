<?php
/**
 * 前端路由文件
 * 将/view/页面名请求路由到正确的前端视图
 */

// 获取请求路径 
$requestUri = $_SERVER['REQUEST_URI'];

// 解析请求的页面名称
preg_match('~^/view/([^/\?]+)~', $requestUri, $matches);

// 如果没有匹配到页面名称，默认使用index
$page = isset($matches[1]) ? $matches[1] : 'index';

// 构建实际的文件路径
$filePath = __DIR__ . '/../frontend/views/' . $page . '.php';

// 检查请求的页面文件是否存在
if (file_exists($filePath)) {
    // 包含页面文件
    include $filePath;
} else {
    // 页面不存在，显示404错误
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/../frontend/views/404.php';
} 