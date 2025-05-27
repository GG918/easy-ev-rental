<?php
// 包含认证文件
require_once '../../backend/core/auth.php';
// 引入必要的工具函数
require_once '../../backend/includes/utils.php';

$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();

// 未登录则重定向
if (!$isLoggedIn) {
    header('Location: /view/index?show_login=1&return_url=/view/maintenance');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>车辆维护 - eASY</title>
    <link rel="stylesheet" href="../public/css/index.css">
    <link rel="stylesheet" href="../public/css/maintenance.css">
</head>
<body>
    <header>
        <div class="logo"><a href="/view/index">eASY</a></div>
        <nav class="header">
            <ul>
                <li><a href="/view/locations">查找车辆</a></li>
                <li><a href="/view/maintenance" class="active">车辆维护</a></li>
            </ul>
        </nav>
        <div class="topright">
            <ul>
                <?php if ($isLoggedIn): ?>
                    <li class="user-menu">
                        <button><?php echo htmlspecialchars($currentUser['username']); ?></button>
                        <div class="user-menu-content">
                            <a href="#">我的资料</a>
                            <a href="/view/my_reservations">我的预订</a>
                            <a href="../../backend/core/logout_process.php">登出</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><button onclick="AuthService.openModal('registerModal')">注册</button></li>
                    <li><button onclick="AuthService.openModal('loginModal')">登录</button></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <div class="main-content">
        <div class="page-header">
            <h1>车辆维护记录</h1>
            <?php if ($isAdmin): ?>
            <button id="add-maintenance-btn" class="primary-button">添加维护记录</button>
            <?php endif; ?>
        </div>

        <div class="notification-container">
            <div id="notification" class="notification hidden"></div>
        </div>

        <div class="maintenance-list">
            <div class="filters">
                <select id="status-filter">
                    <option value="all">所有状态</option>
                    <option value="pending">待处理</option>
                    <option value="completed">已完成</option>
                </select>
                <input type="date" id="date-filter" placeholder="按日期筛选">
                <button id="apply-filters" class="secondary-button">应用筛选</button>
                <button id="reset-filters" class="secondary-button">重置</button>
            </div>

            <div class="table-container">
                <table id="maintenance-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>车辆ID</th>
                            <th>日期</th>
                            <th>说明</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="maintenance-data">
                        <!-- 将通过JavaScript填充 -->
                        <tr>
                            <td colspan="6" class="loading-message">正在加载维护记录...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 添加维护记录模态框 -->
    <div id="maintenance-modal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="MaintenanceService.closeModal()">&times;</button>
            <h2>添加维护记录</h2>
            <form id="maintenance-form">
                <div class="form-group">
                    <label for="vehicle-id">车辆ID</label>
                    <input type="number" id="vehicle-id" name="vehicle_id" required min="1">
                </div>
                <div class="form-group">
                    <label for="maintenance-date">维护日期</label>
                    <input type="date" id="maintenance-date" name="maintenance_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="description">维护说明</label>
                    <textarea id="description" name="description" required rows="4"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-button">提交记录</button>
                    <button type="button" class="cancel-button" onclick="MaintenanceService.closeModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 确认完成维护模态框 -->
    <div id="complete-modal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="MaintenanceService.closeConfirmModal()">&times;</button>
            <h2>确认完成维护</h2>
            <p>您确定要将此维护记录标记为已完成吗？</p>
            <p>车辆状态将更改为"可用"。</p>
            <div class="form-actions">
                <button id="confirm-complete-btn" class="submit-button">确认完成</button>
                <button class="cancel-button" onclick="MaintenanceService.closeConfirmModal()">取消</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2025 eASY - 您的可持续出行选择</p>
            <div class="footer-nav">
                <a href="#">帮助</a>
                <a href="/view/maintenance">记录维护</a>
            </div>
        </div>
    </footer>

    <script src="../public/js/auth-service.js"></script>
    <script src="../public/js/maintenance-service.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            MaintenanceService.init();
        });
    </script>
</body>
</html> 