<?php
include_once 'auth.php';

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 使用Auth类处理注册请求
    $result = $auth->register($username, $email, $password);
    
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} else {
    // 如果是GET请求，显示注册页面
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="css/register.css">
    <script src="js/auth-service.js"></script>
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <form id="registerForm">
            <input type="text" id="username" name="username" placeholder="Enter username" required>
            <input type="email" id="email" name="email" placeholder="Enter email" required>
            <input type="password" id="password" name="password" placeholder="Enter password" required>
            <button type="submit">Register</button>
            <p id="registerMessage" class="form-result"></p>
        </form>
    </div>   

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('registerForm').addEventListener('submit', function(event) {
                AuthService.submitRegisterForm(event, 'login.php');
            });
        });
    </script>
</body>  
</html>
<?php
}
?>

