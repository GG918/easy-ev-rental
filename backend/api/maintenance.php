<?php
/**
 * 维护记录API端点
 * 处理维护记录的添加、完成和获取列表功能
 */

// 包含必要文件
require_once '../core/Database.php';
require_once '../core/auth.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 验证用户是否已登录
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => '需要登录后才能访问'
    ]);
    exit;
}

// 检查是否为管理员
$isAdmin = isAdmin();

// 初始化数据库连接
$db = new Database();

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';

// 处理请求
try {
    switch ($method) {
        case 'GET':
            // 获取维护记录列表
            if ($pathInfo === '' || $pathInfo === '/') {
                getMaintenance();
            } elseif (preg_match('/^\/(\d+)$/', $pathInfo, $matches)) {
                // 获取特定维护记录
                getMaintenanceById($matches[1]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => '未找到请求的资源'
                ]);
            }
            break;
            
        case 'POST':
            // 添加新维护记录
            if ($pathInfo === '' || $pathInfo === '/') {
                addMaintenance();
            } elseif (preg_match('/^\/(\d+)\/complete$/', $pathInfo, $matches)) {
                // 完成维护
                completeMaintenance($matches[1]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => '未找到请求的资源'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => '不支持的请求方法'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("维护API错误: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '处理请求时发生错误',
        'details' => $e->getMessage()
    ]);
}

/**
 * 获取维护记录列表
 */
function getMaintenance() {
    global $db;
    
    try {
        $sql = "SELECT 
                    m.id, 
                    m.vehicle_id, 
                    m.description, 
                    m.maintenance_date, 
                    m.created_at,
                    m.completed_at 
                FROM maintenance m 
                ORDER BY m.created_at DESC";
        
        $maintenance = $db->fetchAll($sql);
        
        echo json_encode([
            'status' => 'success',
            'data' => $maintenance
        ]);
    } catch (Exception $e) {
        throw new Exception("获取维护记录失败: " . $e->getMessage());
    }
}

/**
 * 获取特定维护记录
 * @param int $id 维护记录ID
 */
function getMaintenanceById($id) {
    global $db;
    
    try {
        $sql = "SELECT 
                    m.id, 
                    m.vehicle_id, 
                    m.description, 
                    m.maintenance_date, 
                    m.created_at,
                    m.completed_at 
                FROM maintenance m 
                WHERE m.id = ?";
        
        $maintenance = $db->fetchOne($sql, [$id]);
        
        if (!$maintenance) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => '未找到维护记录'
            ]);
            return;
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $maintenance
        ]);
    } catch (Exception $e) {
        throw new Exception("获取维护记录失败: " . $e->getMessage());
    }
}

/**
 * 添加新维护记录
 */
function addMaintenance() {
    global $db, $isAdmin;
    
    // 验证权限 - 只有管理员可以添加维护记录
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => '没有权限执行此操作'
        ]);
        return;
    }
    
    // 获取和验证请求数据
    $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $maintenance_date = filter_input(INPUT_POST, 'maintenance_date', FILTER_SANITIZE_STRING);
    
    if (!$vehicle_id || !$description || !$maintenance_date) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => '缺少必要的参数'
        ]);
        return;
    }
    
    try {
        // 检查车辆是否存在
        $vehicle = $db->fetchOne("SELECT id FROM Locations WHERE id = ? LIMIT 1", [$vehicle_id]);
        
        if (!$vehicle) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => '未找到指定的车辆'
            ]);
            return;
        }
        
        // 添加维护记录
        $maintenance_id = $db->recordMaintenance($vehicle_id, $description, $maintenance_date);
        
        echo json_encode([
            'status' => 'success',
            'message' => '维护记录已添加',
            'data' => [
                'id' => $maintenance_id,
                'vehicle_id' => $vehicle_id
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("添加维护记录失败: " . $e->getMessage());
    }
}

/**
 * 完成维护
 * @param int $id 维护记录ID
 */
function completeMaintenance($id) {
    global $db, $isAdmin;
    
    // 验证权限 - 只有管理员可以完成维护
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => '没有权限执行此操作'
        ]);
        return;
    }
    
    try {
        // 获取维护记录
        $maintenance = $db->fetchOne("SELECT id, vehicle_id, completed_at FROM maintenance WHERE id = ?", [$id]);
        
        if (!$maintenance) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => '未找到维护记录'
            ]);
            return;
        }
        
        // 检查是否已完成
        if ($maintenance['completed_at']) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => '该维护记录已标记为完成'
            ]);
            return;
        }
        
        // 完成维护
        $db->completeMaintenance($id, $maintenance['vehicle_id']);
        
        echo json_encode([
            'status' => 'success',
            'message' => '维护已标记为完成',
            'data' => [
                'id' => $id,
                'vehicle_id' => $maintenance['vehicle_id']
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception("完成维护失败: " . $e->getMessage());
    }
}
?> 