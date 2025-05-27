<?php
/**
 * 工具函数库，提供基础路径获取等功能
 */

/**
 * 获取应用的基础路径
 * 适用于YunoHost等环境，会自动检测环境并适配路径
 * 
 * @return string 包含前导斜杠的基础路径，如果在根目录则返回空字符串
 */
function get_base_path() {
    // 尝试从不同环境变量中获取基础路径
    
    // 1. 首先尝试从PHP_SELF确定
    $path = dirname($_SERVER['PHP_SELF']);
    
    // 2. 或者从SCRIPT_NAME确定 
    if ($path == '/' || empty($path)) {
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    }
    
    // 3. 如果仍然为根目录，尝试从REQUEST_URI获取
    if ($path == '/' || empty($path)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('?', $uri);
        $path = dirname($path_parts[0]);
    }
    
    // 4. 检查是否是YunoHost环境 - 通常会有特定的路径格式
    $yunohost_path = '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // YunoHost的应用通常以/app形式部署，比如/my_webapp
    if (preg_match('#^/([^/]+)/#', $request_uri, $matches)) {
        $yunohost_path = '/' . $matches[1];
    }
    
    // 使用YunoHost路径，如果检测到
    if (!empty($yunohost_path) && $yunohost_path != '/') {
        $path = $yunohost_path;
    }
    
    // 如果上述都失败，检查当前执行的脚本路径
    if ($path == '/' || empty($path)) {
        $current_file = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        
        if (!empty($document_root) && !empty($current_file) && strpos($current_file, $document_root) === 0) {
            $relative_path = dirname(substr($current_file, strlen($document_root)));
            if ($relative_path != '/' && !empty($relative_path)) {
                $path = $relative_path;
            }
        }
    }
    
    // 清理并格式化路径
    $path = rtrim($path, '/');
    
    // 如果是根路径，返回空字符串
    if ($path == '/' || empty($path)) {
        return '';
    }
    
    return $path;
}

/**
 * 生成带有正确基础路径的URL
 * 
 * @param string $path 相对于应用根目录的路径
 * @return string 完整的URL路径
 */
function url($path) {
    $base = get_base_path();
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}
?> 