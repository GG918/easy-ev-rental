# eASY Electric Vehicle Rental System

eASY 是一个基于 PHP、JavaScript 和 MySQL 构建的电动租赁系统。该系统旨在提供实时车辆定位、在线预订和行程追踪等功能，方便用户租赁电动车辆。同时，系统内置多种调试和数据查询工具，便于管理员监控车辆状态与系统运行情况。

---

## 项目架构

- **前端**  
  - HTML/CSS：界面展示与响应式布局  
  - JavaScript：处理用户交互、地图显示（使用 Leaflet）、预约请求等功能  
  - 模块化代码文件：如 `reservation.js`、`my_reservations.js`、`ui-service.js` 等

- **后端**  
  - PHP：处理用户注册、登录、预约、行程开始与结束、维护记录等业务逻辑  
  - 数据库访问：利用 PDO 操作 MySQL 数据库，通过 `Database.php` 进行连接与查询  
  - 调试文件：如 `debug_api.php`, `debug_db.php`, `debug_location.php` 用于系统调试与问题排查

- **数据库**  
  - MySQL：存储用户信息、车辆实时位置、预约记录和维护日志等数据  
  - 数据表设计包括：`users`, `booking`, `Locations`, `maintenances` 等

---

## 功能概览

- **用户管理**  
  - 注册、登录、登出  
  - 用户信息维护和权限验证

- **车辆预订**  
  - 实时查看附近可用车辆  
  - 简单预约和高级预约（支持指定开始与结束时间）  
  - 预约冲突检测与状态管理（预订、进行中、完成、取消）

- **行程追踪**  
  - 实时跟踪车辆位置  
  - 显示行驶轨迹与速度信息  
  - 地图展示（基于 Leaflet）

- **调试与数据查询工具**  
  - API 调试工具（如 debug_api.php）  
  - 数据库连接和查询状态检测（如 debug_db.php）
  - 位置测试工具（如 debug_location.php）

- **维护记录**  
  - 管理员可记录车辆维护信息  
  - 前端与后端均提供维护日志接口

---

## 安装与配置

1. **克隆代码仓库**  
   ```bash
   git clone https://github.com/yourusername/easy-ev-rental.git
   ```

2. **配置 Web 服务器**  
   - 将项目部署到支持 PHP 的 Web 服务器（如 Apache、Nginx 等）。
   - 将根目录设置为 `\var\www\my_webapp\www`。

3. **创建数据库并导入脚本**  
   - 使用 MySQL 创建数据库（例如 `ev_rental_db`），然后运行提供的数据库脚本（文件路径见项目中相关文档），生成必要的数据表和视图。

4. **配置数据库连接**  
   - 编辑 `Database.php` 文件，更新数据库主机、数据库名、用户名和密码配置以匹配您的环境。

5. **设置文件权限**  
   - 确保 Web 服务器对项目文件具有适当的读取/写入权限。

---

## 运行逻辑

1. **用户访问**  
   - 用户可通过浏览器访问 `index.php`。首页提供登录和注册入口，以及车辆查询入口（链接到 `locations.php`）。

2. **车辆定位与预订**  
   - 在 `locations.php` 页面，系统根据用户当前地理位置或输入的位置查询附近车辆。
   - 用户选择一个车辆后，通过 AJAX 请求调用 `api.php?action=reserveScooter` 进行预约。预约成功后，系统返回预约编号及预约时间信息。

3. **行程追踪**  
   - 用户开始行程后，进入 `track.php` 页面。系统利用 Leaflet 在地图上绘制车辆轨迹，显示车辆实时位置和跑行速度。
   - 可通过控制面板控制行程动画、暂停、重置等。

4. **调试与数据监控**  
   - 系统内置调试页面（如 `debug_api.php` 和 `debug_db.php`），管理员可以通过这些页面查看实时 API 调试信息、数据库连接状态及车辆状态等。
   - 日志记录由 PHP 的 error_log 输出，便于排查故障。

5. **维护管理**  
   - 维护页面（`maintenance.php`）允许管理员录入并查询车辆维护记录。填写表单后，系统记录维护信息至数据库，并返回相应提示。

---

## 使用说明

- **用户操作**  
  - **注册/登录**：在首页点击注册或登录按钮，填写必要信息后进入系统。
  - **车辆查询与预订**：在 `locations.php` 页面查看附近车辆信息，选择心仪车辆并提交预订请求。
  - **行程跟踪**：预约成功后点击开始行程按钮进入追踪页面，实时查看车辆动态及行程信息。

- **管理员操作**  
  - 访问调试工具和数据库监控页面，以确保系统正常运行。
  - 记录车辆维护信息，通过 `maintenance.php` 录入维修日志。

- **开发与贡献**  
  - 项目采用模块化设计，前端 JavaScript 文件和后端 PHP 文件均分散处理具体功能。
  - 提交 issue 或 pull request 以协助问题修复和功能扩展。

---
# 数据库架构设计文档

## 表结构说明

### users 表
用户信息表，存储系统用户数据。

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active'
);
```

### booking 表
预约记录表，记录所有车辆预订信息。

```sql
CREATE TABLE booking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('reserved','in_progress','completed','cancelled') NOT NULL,
    expiry_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status_dates (status, start_date, end_date),
    INDEX idx_vehicle (vehicle_id)
);
```

### Locations 表
车辆位置信息表，记录实时位置数据。

```sql
CREATE TABLE Locations (
    id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    location POINT NOT NULL SRID 4326,
    battery_level INT NOT NULL,
    speed_mph FLOAT,
    course FLOAT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SPATIAL INDEX idx_location (location),
    INDEX idx_timestamp (timestamp),
    INDEX idx_status (status)
);
```

### maintenances 表
维护记录表，记录车辆维护历史。

```sql
CREATE TABLE maintenances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    description TEXT NOT NULL,
    maintenance_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_vehicle_date (vehicle_id, maintenance_date)
);
```

## 视图定义

### latest_scooter_data
获取每个车辆的最新位置信息。

```sql
CREATE VIEW latest_scooter_data AS
SELECT 
    l.id,
    l.status,
    ST_X(l.location) as longitude,
    ST_Y(l.location) as latitude,
    l.battery_level,
    l.speed_mph,
    l.timestamp
FROM Locations l
INNER JOIN (
    SELECT id, MAX(timestamp) as max_time
    FROM Locations
    GROUP BY id
) latest ON l.id = latest.id AND l.timestamp = latest.max_time;
```

## 索引策略

1. 主键索引：所有表都使用自增主键
2. 外键索引：预约表和维护表关联用户表
3. 复合索引：针对常用查询场景优化
4. 空间索引：优化地理位置查询性能

## 数据完整性约束

1. 用户名和邮箱唯一性约束
2. 预约状态枚举限制
3. 电池电量范围检查(0-100)
4. 维护记录必须关联到有效用户
```

### \var\www\my_webapp\www\docs\api-reference.md

这个文件将详细说明 API 接口。

````markdown
// filepath: \var\www\my_webapp\www\docs\api-reference.md
# API 接口文档

## 认证
所有 API 请求需要在 Session 中有有效的用户登录状态，否则返回 401 错误。

## 通用响应格式
```json
{
    "success": true|false,
    "message": "操作结果描述",
    "data": {} // 可选，具体数据
}
```

## 用户接口

### 登录
- 路径: `api.php?action=login`
- 方法: POST
- 参数:
```json
{
    "username": "用户名",
    "password": "密码"
}
```
- 响应:
```json
{
    "success": true,
    "message": "登录成功",
    "user": {
        "id": 1,
        "username": "用户名"
    }
}
```

### 注册
- 路径: `api.php?action=register`
- 方法: POST
- 参数:
```json
{
    "username": "用户名",
    "email": "邮箱",
    "password": "密码"
}
```

## 车辆接口

### 获取附近车辆
- 路径: `api.php?action=getNearbyScooters`
- 方法: GET
- 参数:
  - lat: 纬度
  - lng: 经度
  - radius: 搜索半径(米)
- 响应:
```json
{
    "success": true,
    "vehicles": [
        {
            "id": 1,
            "latitude": 53.3814,
            "longitude": -1.4778,
            "battery_level": 85,
            "status": "available",
            "distance": 120
        }
    ]
}
```

### 预约车辆
- 路径: `api.php?action=reserveScooter`
- 方法: POST
- 参数:
```json
{
    "scooter_id": 1,
    "start_time": "2024-01-20 14:00:00", // 可选
    "end_time": "2024-01-20 14:30:00"    // 可选
}
```

### 开始行程
- 路径: `api.php?action=startTrip`
- 方法: POST
- 参数:
```json
{
    "booking_id": 1,
    "scooter_id": 1
}
```

### 结束行程
- 路径: `api.php?action=endTrip`
- 方法: POST
- 参数:
```json
{
    "booking_id": 1,
    "scooter_id": 1
}
```

## 数据追踪接口

### 获取行程轨迹
- 路径: `api.php?action=getTripTrack`
- 方法: GET
- 参数:
  - booking_id: 预约ID
- 响应:
```json
{
    "success": true,
    "track": [
        {
            "latitude": 53.3814,
            "longitude": -1.4778,
            "speed": 15.5,
            "timestamp": "2024-01-20 14:15:30"
        }
    ]
}
```

## 错误码说明

- 400: 请求参数错误
- 401: 未授权访问
- 403: 权限不足
- 404: 资源不存在
- 409: 资源冲突(如预约冲突)
- 500: 服务器内部错误
```

这两个文件分别提供了详细的数据库设计文档和 API 接口说明，可以帮助开发者更好地理解和使用系统。它们应该被放在项目的 docs 目录下，方便查阅。



## 开发注意事项

- 确保所有 API 请求均经过身份验证，防止未授权访问。
- 对所有输入参数进行严格验证，以避免 SQL 注入和 XSS 攻击。
- 调试工具仅在开发和测试环境中启用，生产环境请关闭或限制访问。
- 更新 README 文件以反映任何新特性或重大变更。

---

## 许可证

本项目采用 [MIT 许可证](LICENSE)。详细内容请查看 LICENSE 文件。

---

## 参考资料

- [PHP 官方文档](https://www.php.net/manual/zh/)
- [Leaflet 官方文档](https://leafletjs.com/)
- [MySQL 查询优化](https://dev.mysql.com/doc/)
