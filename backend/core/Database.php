<?php
class Database
{
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    private $error;

    public function __construct()
    {
        // 从配置文件获取数据库设置
        $config_file = dirname(__DIR__) . '/config/config.php';
        if (!file_exists($config_file)) {
            die(json_encode([
                'error' => '配置文件不存在',
                'path' => $config_file
            ]));
        }
        
        // 使用include获取配置，避免输出干扰
        ob_start();
        $config = include $config_file;
        ob_end_clean();
        
        if (!is_array($config)) {
            die(json_encode([
                'error' => '配置文件格式错误',
                'detail' => '配置文件必须返回数组',
                'type' => gettype($config)
            ]));
        }
        
        if (!isset($config['db'])) {
            die(json_encode([
                'error' => '数据库配置缺失',
                'detail' => '未能找到数据库配置项',
                'config' => print_r($config, true)
            ]));
        }

        $this->host = $config['db']['host'] ?? 'localhost';
        $this->dbname = $config['db']['database'] ?? '';
        $this->username = $config['db']['username'] ?? '';
        $this->password = $config['db']['password'] ?? '';
        $this->charset = $config['db']['charset'] ?? 'utf8';

        // 使用host连接替代socket，不包含charset参数
        $dsn = "mysql:host={$this->host};dbname={$this->dbname}";

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 设置字符集
            $this->pdo->exec("SET NAMES {$this->charset}");

            // 设置时区
            date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die(json_encode([
                'error' => '数据库连接失败',
                'message' => $this->error
            ]));
        }
    }

    // 获取PDO连接实例
    public function getConnection()
    {
        return $this->pdo;
    }

    // 通用查询方法，带可选参数
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die(json_encode([
                'error' => '数据库查询错误',
                'message' => $e->getMessage()
            ]));
        }
    }

    // 获取所有结果为关联数组
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    // 获取一行结果为关联数组
    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    // 执行插入/更新/删除并返回受影响行数
    public function execute($sql, $params = [])
    {
        return $this->query($sql, $params)->rowCount();
    }

    // 获取最后插入ID
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    // 获取车辆状态
    public function getVehicleStatus($vehicle_id)
    {
        try {
            // 从Locations表获取最新状态
            $vehicle = $this->fetchOne(
                "SELECT 
                    id, 
                    CASE WHEN status = 'available' THEN 1 ELSE 0 END as availability,
                    battery_level,
                    ST_X(location) as longitude,
                    ST_Y(location) as latitude,
                    status
                FROM Locations 
                WHERE id = ? 
                ORDER BY timestamp DESC 
                LIMIT 1", 
                [$vehicle_id]
            );
            
            return $vehicle;
        } catch (Exception $e) {
            // 记录错误并重新抛出
            error_log("获取车辆状态错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    // 获取用户活跃预订
    public function getUserActiveReservation($user_id)
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
             WHERE user_id = ? 
             AND status = 'reserved' 
             AND (expiry_time IS NULL OR expiry_time > NOW())",
            [$user_id]
        );
    }
    
    // 创建预订
    public function createReservation($user_id, $vehicle_id, $start_time, $end_time, $expiry_time)
    {
        try {
            // 开始事务
            $this->getConnection()->beginTransaction();
            
            // 插入预订记录
            $this->execute(
                "INSERT INTO booking (user_id, vehicle_id, start_date, end_date, expiry_time, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, 'reserved', NOW())",
                [
                    $user_id, 
                    $vehicle_id, 
                    $start_time,
                    $end_time,
                    $expiry_time
                ]
            );
            
            $booking_id = $this->getConnection()->lastInsertId();
            
            // 更新车辆状态 - 创建新的Locations记录而不是更新
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'in_use', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // 提交事务
            $this->getConnection()->commit();
            
            return $booking_id;
        } catch (Exception $e) {
            // 回滚事务
            $this->getConnection()->rollBack();
            error_log("预订创建错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    // 取消预订
    public function cancelReservation($booking_id, $vehicle_id)
    {
        try {
            // 开始事务
            $this->getConnection()->beginTransaction();
            
            // 更新预订状态
            $this->execute(
                "UPDATE booking SET status = 'cancelled' WHERE id = ?",
                [$booking_id]
            );
            
            // 更新车辆状态 - 创建新的Locations记录而不是更新
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'available', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // 提交事务
            $this->getConnection()->commit();
            
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $this->getConnection()->rollBack();
            error_log("预订取消错误: " . $e->getMessage());
            throw $e;
        }
    }

    // 获取最新车辆位置数据
    public function getLatestLocations() 
    {
        try {
            $locations = $this->fetchAll(
                "SELECT 
                    id, 
                    speed_mph,
                    timestamp,
                    status,
                    ST_X(location) as longitude, 
                    ST_Y(location) as latitude,
                    battery_level
                FROM Locations l1
                WHERE timestamp = (
                    SELECT MAX(timestamp) 
                    FROM Locations l2 
                    WHERE l2.id = l1.id
                )"
            );
            
            return $locations;
        } catch (Exception $e) {
            error_log("获取最新位置错误: " . $e->getMessage());
            throw $e;
        }
    }

    // 获取车辆预订时间冲突
    public function getBookingConflicts($vehicle_id, $start_time, $end_time) 
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
            WHERE vehicle_id = ? AND (status = 'reserved')
            AND ((start_date <= ? AND end_date > ?) 
                OR (start_date < ? AND end_date >= ?))",
            [
                $vehicle_id, 
                $start_time, 
                $start_time,
                $end_time,
                $end_time
            ]
        );
    }
    
    // 获取可用时间段
    public function getAvailableTimeSlots($vehicle_id, $date)
    {
        // 获取已预订时间段
        $bookedSlots = $this->fetchAll(
            "SELECT start_date, end_date FROM booking 
            WHERE vehicle_id = ? AND DATE(start_date) = ? AND status != 'cancelled'",
            [$vehicle_id, $date]
        );
        
        // 生成时间段
        $timeSlots = [];
        $startHour = 9; // 从早上9点开始
        $endHour = 22;  // 到晚上10点结束
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            // 检查整点和半点
            $slotTimes = [
                ['hour' => $hour, 'minute' => 0],
                ['hour' => $hour, 'minute' => 30]
            ];
            
            foreach ($slotTimes as $slotTime) {
                $slotDateTime = new DateTime($date);
                $slotDateTime->setTime($slotTime['hour'], $slotTime['minute']);
                
                // 检查是否有冲突
                $hasConflict = false;
                foreach ($bookedSlots as $bookedSlot) {
                    $startDate = new DateTime($bookedSlot['start_date']);
                    $endDate = new DateTime($bookedSlot['end_date']);
                    
                    if ($slotDateTime >= $startDate && $slotDateTime < $endDate) {
                        $hasConflict = true;
                        break;
                    }
                }
                
                if (!$hasConflict) {
                    $timeSlots[] = [
                        'time' => $slotDateTime->format('H:i'),
                        'datetime' => $slotDateTime->format('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        return $timeSlots;
    }

    // 获取用户预订历史
    public function getUserReservationHistory($user_id, $limit = 20)
    {
        try {
            return $this->fetchAll(
                "SELECT 
                    b.id, 
                    b.vehicle_id, 
                    b.start_date, 
                    b.end_date, 
                    b.status, 
                    b.created_at,
                    CONCAT('电动滑板车 #', b.vehicle_id) as vehicle_name
                FROM booking b
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC
                LIMIT ?",
                [$user_id, $limit]
            );
        } catch (Exception $e) {
            error_log("获取用户预订历史错误: " . $e->getMessage());
            throw $e;
        }
    }

    // 开始车辆使用
    public function startVehicleOrder($booking_id, $vehicle_id)
    {
        try {
            // 开始事务
            $this->getConnection()->beginTransaction();
            
            // 更新预订状态
            $this->execute(
                "UPDATE booking SET status = 'active', start_time_actual = NOW() WHERE id = ?",
                [$booking_id]
            );
            
            // 更新车辆状态 - 创建新的Locations记录
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'in_use', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // 提交事务
            $this->getConnection()->commit();
            
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $this->getConnection()->rollBack();
            error_log("开始车辆使用错误: " . $e->getMessage());
            throw $e;
        }
    }

    // 完成车辆使用
    public function completeVehicleOrder($booking_id, $vehicle_id)
    {
        try {
            // 开始事务
            $this->getConnection()->beginTransaction();
            
            // 获取预订信息计算费用
            $booking = $this->fetchOne(
                "SELECT start_time_actual FROM booking WHERE id = ?", 
                [$booking_id]
            );
            
            if (!$booking) {
                throw new Exception("未找到预订记录");
            }
            
            $startTime = new DateTime($booking['start_time_actual']);
            $endTime = new DateTime();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            $hours = $duration / 3600;
            $totalCost = $hours * 10; // 每小时10元
            
            // 更新预订状态
            $this->execute(
                "UPDATE booking 
                SET status = 'completed', 
                    end_time_actual = NOW(),
                    duration_seconds = ?,
                    total_cost = ?
                WHERE id = ?",
                [$duration, $totalCost, $booking_id]
            );
            
            // 更新车辆状态 - 创建新的Locations记录
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                 SELECT id, 'available', location, battery_level, speed_mph 
                 FROM Locations 
                 WHERE id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT 1", 
                [$vehicle_id]
            );
            
            // 提交事务
            $this->getConnection()->commit();
            
            return [
                'success' => true,
                'duration' => $duration,
                'total_cost' => $totalCost
            ];
        } catch (Exception $e) {
            // 回滚事务
            $this->getConnection()->rollBack();
            error_log("完成车辆使用错误: " . $e->getMessage());
            throw $e;
        }
    }

    // 获取用户活跃订单
    public function getUserActiveOrder($user_id)
    {
        return $this->fetchOne(
            "SELECT * FROM booking 
             WHERE user_id = ? 
             AND status = 'active'",
            [$user_id]
        );
    }

    // 获取预订详情
    public function getBookingDetails($booking_id)
    {
        return $this->fetchOne(
            "SELECT 
                b.*, 
                CONCAT('电动滑板车 #', b.vehicle_id) as vehicle_name
             FROM booking b 
             WHERE b.id = ?",
            [$booking_id]
        );
    }
    
    // 记录车辆维护
    public function recordMaintenance($vehicle_id, $description, $maintenance_date)
    {
        try {
            // 记录维护
            $this->execute(
                "INSERT INTO maintenance (vehicle_id, description, maintenance_date, created_at) 
                VALUES (?, ?, ?, NOW())",
                [$vehicle_id, $description, $maintenance_date]
            );
            
            // 更新车辆状态
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                SELECT id, 'maintenance', location, battery_level, speed_mph 
                FROM Locations 
                WHERE id = ? 
                ORDER BY timestamp DESC 
                LIMIT 1", 
                [$vehicle_id]
            );
            
            return $this->getLastInsertId();
        } catch (Exception $e) {
            error_log("记录维护错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    // 完成维护
    public function completeMaintenance($maintenance_id, $vehicle_id)
    {
        try {
            // 更新维护记录
            $this->execute(
                "UPDATE maintenance SET completed_at = NOW() WHERE id = ?",
                [$maintenance_id]
            );
            
            // 更新车辆状态
            $this->execute(
                "INSERT INTO Locations (id, status, location, battery_level, speed_mph) 
                SELECT id, 'available', location, battery_level, speed_mph 
                FROM Locations 
                WHERE id = ? 
                ORDER BY timestamp DESC 
                LIMIT 1", 
                [$vehicle_id]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("完成维护错误: " . $e->getMessage());
            throw $e;
        }
    }
}

// 创建数据库连接实例
$db = new Database();
?> 