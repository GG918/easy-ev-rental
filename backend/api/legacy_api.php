<?php
session_start();
require_once '../core/Database.php';
require_once '../core/auth.php';

// 创建数据库连接
$db = new Database();

// 设置响应类型为JSON
header('Content-Type: application/json');

// 获取请求的操作
$action = $_GET['action'] ?? '';

// 登录检查函数
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Please log in first']);
        exit;
    }
    return $_SESSION['user_id'];
}

// 处理请求
try {
    switch ($action) {
        case 'getLatestLocations':
            // 获取最新车辆位置数据
            $data = $db->getLatestLocations();
            echo json_encode($data);
            break;
            
        case 'reserveScooter':
            // 预订车辆
            $user_id = checkLogin();
            
            // 获取POST数据
            $postData = file_get_contents('php://input');
            error_log("Received reservation data: " . $postData); // 调试日志
            
            $data = json_decode($postData, true);
            
            if (!isset($data['scooter_id'])) {
                throw new Exception('Missing scooter_id parameter');
            }
            
            $vehicle_id = $data['scooter_id'];
            
            // 检查车辆是否可用
            $vehicleStatus = $db->getVehicleStatus($vehicle_id);
            
            if (!$vehicleStatus) {
                throw new Exception('Vehicle not found');
            }
            
            if ($vehicleStatus['availability'] != 1) {
                throw new Exception('Vehicle is not available for reservation');
            }
            
            if (isset($vehicleStatus['battery_level']) && $vehicleStatus['battery_level'] < 15) {
                throw new Exception('Vehicle battery too low for reservation');
            }
            
            // 检查用户是否有活跃预订
            $existingBooking = $db->getUserActiveReservation($user_id);
            
            if ($existingBooking) {
                throw new Exception('You already have an active reservation. Please cancel it first.');
            }
            
            // 确定是简单预订还是高级预订
            $isAdvancedBooking = isset($data['start_time']) && isset($data['end_time']);
            
            // 设置预订时间
            $now = new DateTime();
            
            if ($isAdvancedBooking) {
                // 高级预订 - 使用指定的开始和结束时间
                $start_time = new DateTime($data['start_time']);
                $end_time = new DateTime($data['end_time']);
                
                // 验证时间参数
                if ($start_time >= $end_time) {
                    throw new Exception('End time must be after start time');
                }
                
                // 检查时间冲突
                $conflictBooking = $db->getBookingConflicts($vehicle_id, $data['start_time'], $data['end_time']);
                if ($conflictBooking) {
                    throw new Exception('This time slot is already booked');
                }
                
                // 对于高级预订，设置expiry_time为NULL
                $expiry_time = null;
            } else {
                // 简单预订 - 从当前时间开始
                $start_time = $now;
                $end_time = (clone $now)->modify('+30 minutes');
                
                // 预订过期时间（15分钟后）
                $expiry_time = (clone $now)->modify('+15 minutes');
            }
            
            try {
                // 创建预订
                $booking_id = $db->createReservation(
                    $user_id,
                    $vehicle_id,
                    $start_time->format('Y-m-d H:i:s'),
                    $end_time->format('Y-m-d H:i:s'),
                    $expiry_time ? $expiry_time->format('Y-m-d H:i:s') : null
                );
                
                // 返回成功响应
                echo json_encode([
                    'success' => true,
                    'message' => 'Vehicle reserved successfully',
                    'booking_id' => $booking_id,
                    'start_time' => $start_time->format('Y-m-d H:i:s'),
                    'end_time' => $end_time->format('Y-m-d H:i:s'),
                    'expiry_time' => $expiry_time ? $expiry_time->format('Y-m-d H:i:s') : null
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to create reservation: ' . $e->getMessage());
            }
            break;
            
        case 'cancelReservation':
            // 取消预订
            $user_id = checkLogin();
            
            // 获取POST数据
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['scooter_id'])) {
                throw new Exception('Missing scooter_id parameter');
            }
            
            $vehicle_id = $data['scooter_id'];
            
            try {
                // 获取用户的活跃预订
                $activeBooking = $db->getUserActiveReservation($user_id);
                
                if (!$activeBooking || $activeBooking['vehicle_id'] != $vehicle_id) {
                    throw new Exception('No active reservation found for this vehicle');
                }
                
                // 取消预订
                $db->cancelReservation($activeBooking['id'], $vehicle_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Reservation cancelled successfully'
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to cancel reservation: ' . $e->getMessage());
            }
            break;
            
        case 'verifyReservation':
            // 验证预订状态
            if (!isset($_GET['id'])) {
                throw new Exception('Missing vehicle id parameter');
            }
            
            $scooterId = $_GET['id'];
            $bookingId = $_GET['booking_id'] ?? null;
            
            $query = "SELECT 
                      b.id, b.user_id, b.vehicle_id, b.start_date, b.end_date, b.expiry_time, b.status,
                      u.username,
                      v.battery_level, v.latitude, v.longitude
                    FROM booking b
                    JOIN users u ON b.user_id = u.id
                    LEFT JOIN (
                        SELECT id, battery_level, 
                               ST_Y(location) as latitude, 
                               ST_X(location) as longitude
                        FROM Locations l1
                        WHERE timestamp = (SELECT MAX(timestamp) FROM Locations l2 WHERE l2.id = l1.id)
                    ) v ON b.vehicle_id = v.id
                    WHERE b.vehicle_id = ? AND b.status = 'reserved'";
                    
            $params = [$scooterId];
            
            if ($bookingId) {
                $query .= " AND b.id = ?";
                $params[] = $bookingId;
            } else {
                $query .= " AND (b.expiry_time IS NULL OR b.expiry_time > NOW())";
            }
            
            $result = $db->fetchOne($query, $params);
            
            $isValid = false;
            if ($result && isset($result['id']) && ($result['user_id'] == $_SESSION['user_id'] || !isset($_SESSION['user_id']))) {
                $isValid = true;
            }
            
            echo json_encode([
                'is_valid' => $isValid,
                'data' => $result ? $result : null
            ]);
            break;
            
        case 'getAvailableTimeSlots':
            // 获取车辆可用时间段
            if (!isset($_GET['scooter_id']) || !isset($_GET['date'])) {
                throw new Exception('Missing required parameters');
            }
            
            $scooterId = $_GET['scooter_id'];
            $date = $_GET['date'];
            
            // 验证日期格式
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD');
            }
            
            // 获取时间段
            $result = $db->getAvailableTimeSlots($scooterId, $date);
            
            echo json_encode([
                'success' => true,
                'timeSlots' => $result['timeSlots'],
                'bookedSlots' => $result['bookedSlots']
            ]);
            break;
            
        case 'getUserReservations':
            // 获取用户预订历史
            $user_id = checkLogin();
            
            $reservations = $db->getUserReservationHistory($user_id);
            
            echo json_encode([
                'success' => true,
                'reservations' => $reservations
            ]);
            break;
            
        case 'verifyVehicleStatus':
            // 验证车辆状态
            if (!isset($_GET['id'])) {
                throw new Exception('Missing vehicle id parameter');
            }
            
            $vehicleId = $_GET['id'];
            $vehicleStatus = $db->getVehicleStatus($vehicleId);
            
            if (!$vehicleStatus) {
                throw new Exception('Vehicle not found');
            }
            
            echo json_encode([
                'success' => true,
                'vehicle' => $vehicleStatus
            ]);
            break;
        
        case 'startTrip':
            // 开始行程
            $user_id = checkLogin();
            
            // 获取POST数据
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['booking_id']) || !isset($data['scooter_id'])) {
                throw new Exception('Missing required parameters');
            }
            
            $booking_id = $data['booking_id'];
            $vehicle_id = $data['scooter_id'];
            
            // 验证预订是否属于当前用户
            $booking = $db->getBookingDetails($booking_id);
            
            if (!$booking || $booking['user_id'] != $user_id) {
                throw new Exception('Invalid reservation');
            }
            
            if ($booking['status'] != 'reserved') {
                throw new Exception('This reservation cannot be started in its current state');
            }
            
            // 开始行程
            $result = $db->startVehicleOrder($booking_id, $vehicle_id);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Trip started successfully' : 'Failed to start trip'
            ]);
            break;
            
        case 'endTrip':
            // 结束行程
            $user_id = checkLogin();
            
            // 获取POST数据
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['booking_id']) || !isset($data['scooter_id'])) {
                throw new Exception('Missing required parameters');
            }
            
            $booking_id = $data['booking_id'];
            $vehicle_id = $data['scooter_id'];
            
            // 验证预订是否属于当前用户
            $booking = $db->getBookingDetails($booking_id);
            
            if (!$booking || $booking['user_id'] != $user_id) {
                throw new Exception('Invalid reservation');
            }
            
            if ($booking['status'] != 'in_progress') {
                throw new Exception('This trip cannot be completed in its current state');
            }
            
            // 结束行程
            $result = $db->completeVehicleOrder($booking_id, $vehicle_id);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Trip completed successfully' : 'Failed to complete trip'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $action);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?> 