<?php
include_once '../Database.php';
header('Content-Type: text/html; charset=utf-8');
// 安全检查
$debugEnabled = true;

if (!$debugEnabled) {
    die("Debug mode is disabled");
}

// 直接测试 getNearbyScooters 函数
function testGetNearby($lat, $lng, $radius) {
    try {
        $db = new Database(); // 使用Database类创建连接
        
        echo "<h3>Testing with parameters:</h3>";
        echo "<ul>";
        echo "<li>Latitude: $lat</li>";
        echo "<li>Longitude: $lng</li>";
        echo "<li>Radius: $radius meters</li>";
        echo "</ul>";
        
        $query = "SELECT id, 
                    longitude, 
                    latitude, 
                    speed_mph, 
                    status, 
                    battery_level, 
                    timestamp,
                    ROUND(
                        ST_Distance_Sphere(
                            POINT(?, ?), 
                            POINT(longitude, latitude)
                        )
                    ) as distance 
                FROM latest_scooter_data 
                WHERE (status = 'available' OR status = 'idle' OR status = 'AVAILABLE' OR status = 'IDLE')
                  AND battery_level > 15
                HAVING distance <= ?
                ORDER BY distance ASC
                LIMIT 10";
        
        echo "<h3>Query:</h3>";
        echo "<pre>$query</pre>";
        
        echo "<h3>Binding parameters:</h3>";
        echo "Values: [$lng, $lat, $radius]<br><br>";
        
        // 使用Database类的方法执行查询
        $data = $db->fetchAll($query, [$lng, $lat, $radius]);
        
        echo "<h3>Results:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Lat</th><th>Lng</th><th>Distance</th><th>Battery</th><th>Status</th></tr>";
        
        $min_distance = PHP_INT_MAX;
        $max_distance = 0;
        
        foreach ($data as $row) {
            // 更新最小/最大距离
            if (isset($row['distance'])) {
                $min_distance = min($min_distance, $row['distance']);
                $max_distance = max($max_distance, $row['distance']);
            }
            
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['latitude']}</td>";
            echo "<td>{$row['longitude']}</td>";
            echo "<td>{$row['distance']}m</td>"; // 明确显示单位为米
            echo "<td>{$row['battery_level']}%</td>";
            echo "<td>{$row['status']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        if (empty($data)) {
            echo "<p>No scooters found within $radius meters.</p>";
            
            // 添加调试信息 - 显示所有距离
            echo "<h3>All Distances (for debugging):</h3>";
            $all_query = "SELECT id, 
                    ROUND(
                        ST_Distance_Sphere(
                            POINT(?, ?), 
                            POINT(longitude, latitude)
                        )
                    ) as distance,
                    status
                FROM latest_scooter_data 
                ORDER BY distance ASC
                LIMIT 5";
                
            $all_data = $db->fetchAll($all_query, [$lng, $lat]);
            
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Distance</th><th>Status</th></tr>";
            
            foreach ($all_data as $row) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['distance']}m</td>";
                echo "<td>{$row['status']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>Found " . count($data) . " scooter(s). Distance range: {$min_distance}m to {$max_distance}m</p>";
        }
        
        return $data;
    } catch (Exception $e) {
        echo "<div style='color: red'>";
        echo "<h3>Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
        return null;
    }
}

// 过滤输入
$lat = isset($_GET['lat']) ? (float) $_GET['lat'] : 53.3814;
$lng = isset($_GET['lng']) ? (float) $_GET['lng'] : -1.4746;
$radius = isset($_GET['radius']) ? (int) $_GET['radius'] : 500;

?>
<!DOCTYPE html>
<html>
<head>
    <title>API Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { margin-bottom: 20px; }
        input, button { padding: 5px; margin-right: 10px; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>API Debugging Tool</h1>
    
    <form method="get">
        <label>Latitude: <input type="text" name="lat" value="<?= htmlspecialchars($lat) ?>"></label>
        <label>Longitude: <input type="text" name="lng" value="<?= htmlspecialchars($lng) ?>"></label>
        <label>Radius (m): <input type="number" name="radius" value="<?= htmlspecialchars($radius) ?>"></label>
        <button type="submit">Test Query</button>
        <button type="button" id="getLocationBtn">Use My Location</button>
    </form>
    
    <hr>
    
    <div id="results">
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lat'])) {
            // 直接执行测试
            testGetNearby($lat, $lng, $radius);
        }
        ?>
    </div>
    
    <script>
    document.getElementById('getLocationBtn').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.querySelector('input[name="lat"]').value = position.coords.latitude;
                document.querySelector('input[name="lng"]').value = position.coords.longitude;
            }, function(error) {
                alert("Error getting location: " + error.message);
            });
        } else {
            alert("Geolocation is not supported by this browser.");
        }
    });
    </script>
</body>
</html>
