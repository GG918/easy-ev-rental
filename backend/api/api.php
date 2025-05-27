<?php
// 包含必要的文件
require_once '../core/Database.php';
require_once '../core/auth.php';

// 启用跨域支持
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// 获取HTTP请求方法和路径
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$pathSegments = explode('/', trim($requestPath, '/'));

// 找到API部分的索引
$apiIndex = array_search('api.php', $pathSegments);
$apiPath = array_slice($pathSegments, $apiIndex + 1);

// 如果没有具体路径，设置为root
$resource = $apiPath[0] ?? 'root';
$id = $apiPath[1] ?? null;

// 连接数据库
$db = new Database();

// 根据请求路径和方法处理请求
try {
    switch ($resource) {
        case 'root':
            // API根路径
            echo json_encode([
                'status' => 'success',
                'message' => 'eASY API服务',
                'endpoints' => [
                    'vehicles' => '/api.php/vehicles',
                    'reservations' => '/api.php/reservations',
                    'locations' => '/api.php/locations',
                    'users' => '/api.php/users'
                ]
            ]);
            break;
            
        case 'vehicles':
            handleVehiclesRequest($requestMethod, $id, $db);
            break;
            
        case 'reservations':
            handleReservationsRequest($requestMethod, $id, $db);
            break;
            
        case 'locations':
            handleLocationsRequest($requestMethod, $id, $db);
            break;
            
        case 'users':
            handleUsersRequest($requestMethod, $id, $db);
            break;
            
        default:
            // 未知资源
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => '请求的资源不存在'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '服务器内部错误',
        'error' => $e->getMessage()
    ]);
}

// 处理车辆相关请求
function handleVehiclesRequest($method, $id, $db) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // 获取单个车辆
                $vehicle = $db->getVehicleById($id);
                if ($vehicle) {
                    echo json_encode([
                        'status' => 'success',
                        'data' => $vehicle
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => '未找到该车辆'
                    ]);
                }
            } else {
                // 获取所有车辆
                $vehicles = $db->getAllVehicles();
                echo json_encode([
                    'status' => 'success',
                    'data' => $vehicles
                ]);
            }
            break;
            
        case 'POST':
            // 检查用户是否有权限
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => '没有权限执行此操作'
                ]);
                return;
            }
            
            // 添加新车辆
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '无效的请求数据'
                ]);
                return;
            }
            
            $result = $db->addVehicle($data);
            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => '车辆已添加',
                    'data' => ['id' => $result]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '添加车辆失败'
                ]);
            }
            break;
            
        case 'PUT':
            // 检查用户是否有权限
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => '没有权限执行此操作'
                ]);
                return;
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '缺少车辆ID'
                ]);
                return;
            }
            
            // 更新车辆信息
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '无效的请求数据'
                ]);
                return;
            }
            
            $result = $db->updateVehicle($id, $data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '车辆信息已更新'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '更新车辆信息失败'
                ]);
            }
            break;
            
        case 'DELETE':
            // 检查用户是否有权限
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => '没有权限执行此操作'
                ]);
                return;
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '缺少车辆ID'
                ]);
                return;
            }
            
            // 删除车辆
            $result = $db->deleteVehicle($id);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '车辆已删除'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '删除车辆失败'
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
}

// 处理预订相关请求
function handleReservationsRequest($method, $id, $db) {
    // 检查用户是否已登录
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => '请先登录'
        ]);
        return;
    }
    
    $currentUser = getCurrentUser();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // 获取单个预订
                $reservation = $db->getReservationById($id);
                
                // 检查权限（只能查看自己的预订，除非是管理员）
                if (!isAdmin() && $reservation['user_id'] != $currentUser['id']) {
                    http_response_code(403);
                    echo json_encode([
                        'status' => 'error',
                        'message' => '没有权限查看此预订'
                    ]);
                    return;
                }
                
                if ($reservation) {
                    echo json_encode([
                        'status' => 'success',
                        'data' => $reservation
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => '未找到该预订'
                    ]);
                }
            } else {
                // 获取用户的所有预订
                if (isAdmin() && isset($_GET['all'])) {
                    // 管理员可以查看所有预订
                    $reservations = $db->getAllReservations();
                } else {
                    // 普通用户只能查看自己的预订
                    $reservations = $db->getUserReservations($currentUser['id']);
                }
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $reservations
                ]);
            }
            break;
            
        case 'POST':
            // 创建新预订
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '无效的请求数据'
                ]);
                return;
            }
            
            // 设置用户ID
            $data['user_id'] = $currentUser['id'];
            
            $result = $db->createReservation($data);
            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => '预订已创建',
                    'data' => ['id' => $result]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '创建预订失败'
                ]);
            }
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '缺少预订ID'
                ]);
                return;
            }
            
            // 获取预订信息，检查权限
            $reservation = $db->getReservationById($id);
            if (!$reservation) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => '未找到该预订'
                ]);
                return;
            }
            
            if (!isAdmin() && $reservation['user_id'] != $currentUser['id']) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => '没有权限修改此预订'
                ]);
                return;
            }
            
            // 更新预订
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '无效的请求数据'
                ]);
                return;
            }
            
            $result = $db->updateReservation($id, $data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '预订已更新'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '更新预订失败'
                ]);
            }
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '缺少预订ID'
                ]);
                return;
            }
            
            // 获取预订信息，检查权限
            $reservation = $db->getReservationById($id);
            if (!$reservation) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => '未找到该预订'
                ]);
                return;
            }
            
            if (!isAdmin() && $reservation['user_id'] != $currentUser['id']) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => '没有权限取消此预订'
                ]);
                return;
            }
            
            // 取消预订
            $result = $db->cancelReservation($id, $reservation['vehicle_id']);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '预订已取消'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '取消预订失败'
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
}

// 处理位置相关请求
function handleLocationsRequest($method, $id, $db) {
    switch ($method) {
        case 'GET':
            // 获取车辆位置
            $locations = $db->getVehicleLocations();
            echo json_encode([
                'status' => 'success',
                'data' => $locations
            ]);
            break;
            
        case 'POST':
            // 检查权限
            if (!isLoggedIn()) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => '请先登录'
                ]);
                return;
            }
            
            // 上传车辆位置
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '无效的请求数据'
                ]);
                return;
            }
            
            $result = $db->updateVehicleLocation($data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '位置已更新'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '更新位置失败'
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
}

// 处理用户相关请求
function handleUsersRequest($method, $id, $db) {
    // 检查权限
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => '请先登录'
        ]);
        return;
    }
    
    $currentUser = getCurrentUser();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // 只能查看自己的信息，除非是管理员
                if (!isAdmin() && $id != $currentUser['id']) {
                    http_response_code(403);
                    echo json_encode([
                        'status' => 'error',
                        'message' => '没有权限查看此用户信息'
                    ]);
                    return;
                }
                
                // 获取单个用户
                $user = $db->getUserById($id);
                if ($user) {
                    // 移除敏感信息
                    unset($user['password']);
                    echo json_encode([
                        'status' => 'success',
                        'data' => $user
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => '未找到该用户'
                    ]);
                }
            } else {
                // 获取用户列表（仅管理员）
                if (!isAdmin()) {
                    http_response_code(403);
                    echo json_encode([
                        'status' => 'error',
                        'message' => '没有权限查看用户列表'
                    ]);
                    return;
                }
                
                $users = $db->getAllUsers();
                // 移除敏感信息
                foreach ($users as &$user) {
                    unset($user['password']);
                }
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $users
                ]);
            }
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '缺少用户ID'
                ]);
                return;
            }
            
            // 只能修改自己的信息，除非是管理员
            if (!isAdmin() && $id != $currentUser['id']) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => '没有权限修改此用户信息'
                ]);
                return;
            }
            
            // 更新用户信息
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => '无效的请求数据'
                ]);
                return;
            }
            
            $result = $db->updateUser($id, $data);
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '用户信息已更新'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => '更新用户信息失败'
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
}
?> 