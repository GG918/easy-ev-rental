<?php
include_once '../Database.php';

header('Content-Type: text/html; charset=utf-8');

// 安全检查 - 如果需要，可以添加一个安全令牌
$debugEnabled = true; // 在生产环境中设为 false

if (!$debugEnabled) {
    die("Debug mode is disabled");
}

// 函数来格式化结果为可读的HTML
function formatResult($data) {
    if (is_array($data)) {
        $output = "<ul>";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $output .= "<li><strong>$key</strong>: " . formatResult($value) . "</li>";
            } else {
                $output .= "<li><strong>$key</strong>: " . htmlspecialchars($value) . "</li>";
            }
        }
        $output .= "</ul>";
        return $output;
    } else {
        return htmlspecialchars($data);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location API Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"] { padding: 8px; width: 300px; }
        button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .result { margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Location API Debug Tool</h1>
    
    <div class="form-group">
        <label for="lat">Latitude:</label>
        <input type="text" id="lat" name="lat" placeholder="e.g. 53.3814">
    </div>
    
    <div class="form-group">
        <label for="lng">Longitude:</label>
        <input type="text" id="lng" name="lng" placeholder="e.g. -1.4746">
    </div>
    
    <div class="form-group">
        <label for="radius">Search Radius (meters):</label>
        <input type="number" id="radius" name="radius" value="500">
    </div>
    
    <button id="testButton">Test Nearby Scooters API</button>
    
    <div class="result" id="result">
        <p>Results will appear here after testing...</p>
    </div>
    
    <script>
    document.getElementById('testButton').addEventListener('click', async function() {
        const lat = document.getElementById('lat').value;
        const lng = document.getElementById('lng').value;
        const radius = document.getElementById('radius').value;
        const resultDiv = document.getElementById('result');
        
        resultDiv.innerHTML = '<p>Testing API...</p>';
        
        try {
            // 直接构建API URL
            const url = `api.php?action=getNearbyScooters&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}&radius=${encodeURIComponent(radius)}`;
            
            resultDiv.innerHTML += `<p>Requesting: ${url}</p>`;
            
            const response = await fetch(url);
            const contentType = response.headers.get("content-type");
            
            resultDiv.innerHTML += `<p>Status: ${response.status} ${response.statusText}</p>`;
            resultDiv.innerHTML += `<p>Content-Type: ${contentType}</p>`;
            
            const responseText = await response.text();
            
            // 尝试解析为JSON
            try {
                const data = JSON.parse(responseText);
                resultDiv.innerHTML += `<p class="success">Response parsed successfully as JSON</p>`;
                resultDiv.innerHTML += `<p>Found ${Array.isArray(data) ? data.length : 0} scooters</p>`;
                resultDiv.innerHTML += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            } catch (e) {
                resultDiv.innerHTML += `<p class="error">Invalid JSON response: ${e.message}</p>`;
                resultDiv.innerHTML += `<p>Raw response:</p><pre>${responseText}</pre>`;
            }
        } catch (error) {
            resultDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
        }
    });
    
    // 尝试获取当前位置
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('lat').value = position.coords.latitude;
                document.getElementById('lng').value = position.coords.longitude;
            },
            (error) => {
                console.error("Error getting location:", error);
            }
        );
    }
    </script>
</body>
</html>
