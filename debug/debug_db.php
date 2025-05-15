<?php
include_once '../Database.php';

header('Content-Type: text/plain');
echo "Database Connection Check\n";
echo "------------------------\n\n";

try {
    // 检查主数据库连接
    $db = new Database();
    $pdo = $db->getConnection();
    echo "✅ Main database connection successful\n";
    
    // 显示数据库系统信息 - 修复语法错误，分别查询而不是一次查询多个值
    echo "\nDatabase System Information:\n";
    echo "------------------------\n";
    try {
        // 分别查询各项信息
        $version = $db->fetchOne("SELECT VERSION() as version");
        $dbName = $db->fetchOne("SELECT DATABASE() as db_name");
        $user = $db->fetchOne("SELECT USER() as user_name");
        
        echo "MySQL Version: " . ($version['version'] ?? 'Unknown') . "\n";
        echo "Current Database: " . ($dbName['db_name'] ?? 'Unknown') . "\n";
        echo "Current User: " . ($user['user_name'] ?? 'Unknown') . "\n";
    } catch (Exception $e) {
        echo "❌ Error getting system info: " . $e->getMessage() . "\n";
    }
    
    // 获取并显示所有数据库表
    echo "\nDatabase Tables in ev_rental_db:\n";
    echo "------------------------\n";
    try {
        $tables = $db->fetchAll("SHOW TABLES");
        foreach ($tables as $table) {
            $tableName = reset($table); // 获取第一个值
            echo "➤ {$tableName}\n";
        }
    } catch (Exception $e) {
        echo "❌ Error getting tables: " . $e->getMessage() . "\n";
    }
    
    // 1. 检查users表
    echo "\nChecking users table:\n";
    echo "------------------------\n";
    try {
        // 查询users表中的记录数
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM users");
        echo "✅ Found users table with {$count['count']} records\n";
        
        // 显示表结构
        echo "\nTable structure:\n";
        $columns = $db->fetchAll("DESCRIBE users");
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})" . ($col['Key'] == 'PRI' ? " [PRIMARY KEY]" : "") . "\n";
        }
        
        // 显示示例记录
        if ($count['count'] > 0) {
            echo "\nSample users (up to 3):\n";
            $samples = $db->fetchAll("SELECT id, username, email, created_at FROM users LIMIT 3");
            foreach ($samples as $idx => $sample) {
                echo "\nUser " . ($idx + 1) . ":\n";
                echo "- ID: {$sample['id']}\n";
                echo "- Username: {$sample['username']}\n";
                echo "- Email: {$sample['email']}\n";
                echo "- Created: {$sample['created_at']}\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
    // 2. 检查booking表
    echo "\nChecking booking table:\n";
    echo "------------------------\n";
    try {
        // 查询booking表中的记录数
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM booking");
        echo "✅ Found booking table with {$count['count']} records\n";
        
        // 显示表结构
        echo "\nTable structure:\n";
        $columns = $db->fetchAll("DESCRIBE booking");
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})" . ($col['Key'] == 'PRI' ? " [PRIMARY KEY]" : "") . "\n";
        }
        
        // 显示预订状态统计
        echo "\nBooking status statistics:\n";
        $stats = $db->fetchAll("SELECT status, COUNT(*) as count FROM booking GROUP BY status");
        foreach ($stats as $stat) {
            echo "- {$stat['status']}: {$stat['count']} bookings\n";
        }
        
        // 显示示例记录
        if ($count['count'] > 0) {
            echo "\nSample bookings (up to 3):\n";
            $samples = $db->fetchAll("SELECT * FROM booking ORDER BY id DESC LIMIT 3");
            foreach ($samples as $idx => $sample) {
                echo "\nBooking " . ($idx + 1) . ":\n";
                echo "- ID: {$sample['id']}\n";
                echo "- User ID: {$sample['user_id']}\n";
                echo "- Vehicle ID: {$sample['vehicle_id']}\n";
                echo "- Start Date: {$sample['start_date']}\n";
                echo "- End Date: {$sample['end_date']}\n";
                echo "- Status: {$sample['status']}\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
    // 3. 检查Locations.sensor_data表
    echo "\nChecking Locations.sensor_data table:\n";
    echo "------------------------\n";
    try {
        // 查询sensor_data表中的记录数
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM Locations.sensor_data");
        echo "✅ Found Locations.sensor_data table with {$count['count']} records\n";
        
        // 显示表结构
        echo "\nTable structure:\n";
        $columns = $db->fetchAll("DESCRIBE Locations.sensor_data");
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})" . ($col['Key'] == 'PRI' ? " [PRIMARY KEY]" : "") . "\n";
        }
        
        // 显示车辆ID统计
        echo "\nVehicle ID statistics:\n";
        $stats = $db->fetchAll("SELECT id, COUNT(*) as count FROM Locations.sensor_data GROUP BY id ORDER BY id LIMIT 10");
        foreach ($stats as $stat) {
            echo "- Vehicle #{$stat['id']}: {$stat['count']} data points\n";
        }
        
        // 显示状态统计
        echo "\nStatus statistics:\n";
        $stats = $db->fetchAll("SELECT status, COUNT(*) as count FROM Locations.sensor_data GROUP BY status");
        foreach ($stats as $stat) {
            echo "- {$stat['status']}: {$stat['count']} records\n";
        }
        
        // 显示最新数据
        echo "\nLatest sensor data (up to 3):\n";
        $latest = $db->fetchAll("SELECT id, ST_X(location) as longitude, ST_Y(location) as latitude, 
                                speed_mph, status, battery_level, timestamp 
                                FROM Locations.sensor_data ORDER BY timestamp DESC LIMIT 3");
        foreach ($latest as $idx => $record) {
            echo "\nRecord " . ($idx + 1) . ":\n";
            echo "- Vehicle ID: {$record['id']}\n";
            echo "- Location: {$record['latitude']}, {$record['longitude']}\n";
            echo "- Speed: {$record['speed_mph']} mph\n";
            echo "- Status: {$record['status']}\n";
            echo "- Battery: {$record['battery_level']}%\n";
            echo "- Timestamp: {$record['timestamp']}\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
    // 4. 检查latest_scooter_data视图
    echo "\nChecking latest_scooter_data view:\n";
    echo "------------------------\n";
    try {
        // 查询视图中的记录数
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM latest_scooter_data");
        echo "✅ Found latest_scooter_data view with {$count['count']} records\n";
        
        // 显示视图结构
        echo "\nView structure:\n";
        $viewInfo = $db->fetchAll("SHOW COLUMNS FROM latest_scooter_data");
        foreach ($viewInfo as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        // 显示示例数据
        if ($count['count'] > 0) {
            echo "\nSample data (up to 5 vehicles):\n";
            $samples = $db->fetchAll("SELECT * FROM latest_scooter_data LIMIT 5");
            foreach ($samples as $idx => $sample) {
                echo "\nVehicle " . ($idx + 1) . " (ID: {$sample['id']}):\n";
                echo "- Location: {$sample['latitude']}, {$sample['longitude']}\n";
                echo "- Speed: {$sample['speed_mph']} mph\n";
                echo "- Status: {$sample['status']}\n";
                echo "- Battery: {$sample['battery_level']}%\n";
                echo "- Last Update: {$sample['timestamp']}\n";
            }
        }
        
        // 显示状态统计
        echo "\nStatus distribution:\n";
        $statusStats = $db->fetchAll("SELECT status, COUNT(*) as count FROM latest_scooter_data GROUP BY status");
        foreach ($statusStats as $stat) {
            echo "- {$stat['status']}: {$stat['count']} vehicles\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
    // 5. 检查数据库索引
    echo "\nChecking database indexes:\n";
    echo "------------------------\n";
    try {
        // 获取sensor_data表的索引
        echo "Indexes on Locations.sensor_data:\n";
        $indexes = $db->fetchAll("SHOW INDEX FROM Locations.sensor_data");
        $currentIndex = '';
        foreach ($indexes as $idx) {
            if ($currentIndex != $idx['Key_name']) {
                $currentIndex = $idx['Key_name'];
                echo "\n- {$currentIndex}:\n";
            }
            echo "  • Column: {$idx['Column_name']}, Seq: {$idx['Seq_in_index']}\n";
        }
        
        // 获取booking表的索引
        echo "\nIndexes on booking:\n";
        $indexes = $db->fetchAll("SHOW INDEX FROM booking");
        $currentIndex = '';
        foreach ($indexes as $idx) {
            if ($currentIndex != $idx['Key_name']) {
                $currentIndex = $idx['Key_name'];
                echo "\n- {$currentIndex}:\n";
            }
            echo "  • Column: {$idx['Column_name']}, Seq: {$idx['Seq_in_index']}\n";
        }
    } catch (Exception $e) {
        echo "❌ Error getting indexes: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n------------------------\n";
echo "Script completed at: " . date('Y-m-d H:i:s');
?>
