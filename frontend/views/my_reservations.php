<?php
require_once '../../backend/core/Database.php';
require_once '../../backend/core/auth.php';
// 暂时移除这个包含，因为我们还未移动这个文件
// include_once '../../backend/includes/helpers.php';

// 引入必要的工具函数
require_once '../../backend/includes/utils.php';

// 检查用户是否已登录
$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();

// 如果未登录，重定向到登录页面
if (!$isLoggedIn) {
    header('Location: /view/index?login_required=1');
    exit;
}

$errorMessage = '';
$successMessage = '';
$reservations = [];

// 获取筛选参数
$filterVehicleType = $_GET['vehicle_type'] ?? 'all';
$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? 'all';

try {
    // 获取数据库连接
    $db = new Database();
    
    // 获取用户的预订历史 - 带筛选
    $reservations = $db->getUserReservationHistory($currentUser['id']);
    
    // 应用筛选
    if (!empty($reservations)) {
        $reservations = array_filter($reservations, function($reservation) use ($filterVehicleType, $filterDate, $filterStatus) {
            // 车辆类型筛选
            if ($filterVehicleType !== 'all' && $filterVehicleType !== 'scooter') {
                return false;
            }
            
            // 日期筛选
            if ($filterDate !== '') {
                $reservationDate = date('Y-m-d', strtotime($reservation['start_date']));
                if ($reservationDate !== $filterDate) {
                    return false;
                }
            }
            
            // 状态筛选
            if ($filterStatus !== 'all' && $reservation['status'] !== $filterStatus) {
                return false;
            }
            
            return true;
        });
    }
    
    // 获取当前活跃预订
    $activeReservation = $db->getUserActiveReservation($currentUser['id']);
    
    // 获取进行中的行程
    $activeOrder = $db->getUserActiveOrder($currentUser['id']);
} catch (Exception $e) {
    $errorMessage = '加载预订历史失败: ' . $e->getMessage();
}

// 取消预订处理
if (isset($_POST['cancel_reservation']) && isset($_POST['booking_id']) && isset($_POST['vehicle_id'])) {
    $bookingId = $_POST['booking_id'];
    $vehicleId = $_POST['vehicle_id'];
    
    try {
        $result = $db->cancelReservation($bookingId, $vehicleId);
        if ($result) {
            $successMessage = '预订已成功取消';
            // 重新获取预订历史
            $reservations = $db->getUserReservationHistory($currentUser['id']);
            $activeReservation = $db->getUserActiveReservation($currentUser['id']);
        } else {
            $errorMessage = '取消预订失败';
        }
    } catch (Exception $e) {
        $errorMessage = '取消预订时出错: ' . $e->getMessage();
    }
}

// 开始行程处理
if (isset($_POST['start_trip']) && isset($_POST['booking_id']) && isset($_POST['vehicle_id'])) {
    $bookingId = $_POST['booking_id'];
    $vehicleId = $_POST['vehicle_id'];
    
    try {
        $result = $db->startVehicleOrder($bookingId, $vehicleId);
        if ($result) {
            $successMessage = '行程已成功开始';
            // 重新获取预订历史
            $reservations = $db->getUserReservationHistory($currentUser['id']);
            $activeReservation = null;
            $activeOrder = $db->getUserActiveOrder($currentUser['id']);
        } else {
            $errorMessage = '开始行程失败';
        }
    } catch (Exception $e) {
        $errorMessage = '开始行程时出错: ' . $e->getMessage();
    }
}

// 结束行程处理
if (isset($_POST['end_trip']) && isset($_POST['booking_id']) && isset($_POST['vehicle_id'])) {
    $bookingId = $_POST['booking_id'];
    $vehicleId = $_POST['vehicle_id'];
    
    try {
        $result = $db->completeVehicleOrder($bookingId, $vehicleId);
        if ($result) {
            $successMessage = '行程已成功完成';
            // 重新获取预订历史
            $reservations = $db->getUserReservationHistory($currentUser['id']);
            $activeOrder = null;
        } else {
            $errorMessage = '完成行程失败';
        }
    } catch (Exception $e) {
        $errorMessage = '完成行程时出错: ' . $e->getMessage();
    }
}

// 辅助函数：格式化状态显示
function formatStatus($status) {
    $statusClasses = [
        'reserved' => 'status-reserved',
        'in_progress' => 'status-in_progress',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];
    
    $statusLabels = [
        'reserved' => '已预订',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'cancelled' => '已取消'
    ];
    
    $class = isset($statusClasses[$status]) ? $statusClasses[$status] : 'status-unknown';
    $label = isset($statusLabels[$status]) ? $statusLabels[$status] : ucfirst($status);
    
    return "<span class=\"{$class}\">{$label}</span>";
}

// 辅助函数：格式化时间范围
function formatTimeRange($startTime, $endTime) {
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    
    $dateFormat = 'Y年m月j日'; // 日期格式
    $timeFormat = 'H:i'; // 时间格式
    
    // 返回日期和时间数组
    return [
        'date' => $start->format($dateFormat),
        'time' => $start->format($timeFormat) . ' - ' . $end->format($timeFormat)
    ];
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的预订 - eASY</title>
    <link rel="stylesheet" href="../public/css/index.css">
    <link rel="stylesheet" href="../public/css/booking.css">
    <link rel="stylesheet" href="../public/css/my_reservations.css">
</head>
<body>
    <header>
        <div class="logo"><a href="/view/index">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/view/locations">查找车辆</a></li>
            </ul>
        </nav>
        <div class="topright">
            <ul>
                <li class="user-menu">
                    <button><?php echo htmlspecialchars($currentUser['username']); ?></button>
                    <div class="user-menu-content">
                        <a href="#">我的资料</a>
                        <a href="/view/my_reservations">我的预订</a>
                        <a href="../../backend/core/logout_process.php">登出</a>
                    </div>
                </li>
            </ul>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h1>我的预订</h1>

            <!-- 通知消息 -->
            <?php if ($errorMessage): ?>
                <div class="notification error">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="notification success">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <!-- 活跃预订/行程 -->
            <?php if ($activeReservation): ?>
                <div class="active-reservation">
                    <h2>当前预订</h2>
                    <div class="reservation-card active">
                        <div class="reservation-header">
                            <h3>车辆 #<?php echo htmlspecialchars($activeReservation['vehicle_id']); ?></h3>
                            <span class="status-badge status-reserved">已预订</span>
                        </div>
                        <div class="reservation-details">
                            <?php $timeRange = formatTimeRange($activeReservation['start_date'], $activeReservation['end_date']); ?>
                            <p><strong>日期:</strong> <?php echo $timeRange['date']; ?></p>
                            <p><strong>时间:</strong> <?php echo $timeRange['time']; ?></p>
                            <?php if ($activeReservation['expiry_time']): ?>
                                <p><strong>过期时间:</strong> <?php echo date('H:i', strtotime($activeReservation['expiry_time'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="reservation-actions">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $activeReservation['id']; ?>">
                                <input type="hidden" name="vehicle_id" value="<?php echo $activeReservation['vehicle_id']; ?>">
                                <button type="submit" name="start_trip" class="btn btn-primary">开始行程</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $activeReservation['id']; ?>">
                                <input type="hidden" name="vehicle_id" value="<?php echo $activeReservation['vehicle_id']; ?>">
                                <button type="submit" name="cancel_reservation" class="btn btn-danger" 
                                        onclick="return confirm('确定要取消这个预订吗？')">取消预订</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activeOrder): ?>
                <div class="active-trip">
                    <h2>当前行程</h2>
                    <div class="reservation-card active">
                        <div class="reservation-header">
                            <h3>车辆 #<?php echo htmlspecialchars($activeOrder['vehicle_id']); ?></h3>
                            <span class="status-badge status-in_progress">进行中</span>
                        </div>
                        <div class="reservation-details">
                            <?php $timeRange = formatTimeRange($activeOrder['start_date'], $activeOrder['end_date']); ?>
                            <p><strong>开始时间:</strong> <?php echo date('Y年m月j日 H:i', strtotime($activeOrder['start_date'])); ?></p>
                        </div>
                        <div class="reservation-actions">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $activeOrder['id']; ?>">
                                <input type="hidden" name="vehicle_id" value="<?php echo $activeOrder['vehicle_id']; ?>">
                                <button type="submit" name="end_trip" class="btn btn-success">结束行程</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 筛选器 -->
            <div class="filters">
                <h2>预订历史</h2>
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label for="vehicle_type">车辆类型:</label>
                        <select name="vehicle_type" id="vehicle_type">
                            <option value="all" <?php echo $filterVehicleType === 'all' ? 'selected' : ''; ?>>所有类型</option>
                            <option value="scooter" <?php echo $filterVehicleType === 'scooter' ? 'selected' : ''; ?>>电动滑板车</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date">日期:</label>
                        <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filterDate); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">状态:</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>所有状态</option>
                            <option value="reserved" <?php echo $filterStatus === 'reserved' ? 'selected' : ''; ?>>已预订</option>
                            <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>进行中</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>已完成</option>
                            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">应用筛选</button>
                    <a href="/view/my_reservations" class="btn btn-secondary">清除筛选</a>
                </form>
            </div>

            <!-- 预订历史 -->
            <div class="reservations-list">
                <?php if (empty($reservations)): ?>
                    <div class="empty-state">
                        <p>没有找到预订记录。</p>
                        <a href="/view/locations" class="btn btn-primary">立即预订车辆</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <div class="reservation-card">
                            <div class="reservation-header">
                                <h3>车辆 #<?php echo htmlspecialchars($reservation['vehicle_id']); ?></h3>
                                <?php echo formatStatus($reservation['status']); ?>
                            </div>
                            <div class="reservation-details">
                                <?php $timeRange = formatTimeRange($reservation['start_date'], $reservation['end_date']); ?>
                                <p><strong>日期:</strong> <?php echo $timeRange['date']; ?></p>
                                <p><strong>时间:</strong> <?php echo $timeRange['time']; ?></p>
                                <p><strong>预订时间:</strong> <?php echo date('Y年m月j日 H:i', strtotime($reservation['created_at'])); ?></p>
                                <?php if (isset($reservation['battery_level'])): ?>
                                    <p><strong>电池电量:</strong> <?php echo $reservation['battery_level']; ?>%</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - 您的可持续出行选择</p>
        </div>
    </footer>

    <script>
        // 自动隐藏通知消息
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                setTimeout(function() {
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html> 