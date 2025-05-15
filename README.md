# eASY Electric Vehicle Rental System

[中文版本](#easy-电动车租赁系统)

## Overview

eASY is an Electric Vehicle Rental System built with PHP, JavaScript, and MySQL. The system provides real-time vehicle tracking, online booking, and trip monitoring functionality, making it convenient for users to rent electric vehicles. It also includes various debugging and data query tools for administrators to monitor vehicle status and system operation.

## Project Architecture

- **Frontend**  
  - HTML/CSS: Interface display and responsive layout  
  - JavaScript: Handles user interactions, map display (using Leaflet), booking requests, and other functions  
  - Modular code files: Such as `reservation.js`, `my_reservations.js`, `ui-service.js`, etc.

- **Backend**  
  - PHP: Handles business logic including user registration, login, booking, trip start/end, maintenance records, etc.  
  - Database access: Uses PDO to operate MySQL database, connecting and querying through `Database.php`  
  - Debug files: Such as `debug_api.php`, `debug_db.php`, `debug_location.php` for system debugging and troubleshooting

- **Database**  
  - MySQL: Stores user information, real-time vehicle locations, booking records, and maintenance logs  
  - Database tables include: `users`, `booking`, `Locations`, `maintenances`, etc.

## Features

- **User Management**  
  - Registration, login, logout  
  - User information maintenance and permission verification

- **Vehicle Booking**  
  - Real-time view of nearby available vehicles  
  - Simple booking and advanced booking (supporting specified start and end times)  
  - Booking conflict detection and status management (reserved, in progress, completed, canceled)

- **Trip Tracking**  
  - Real-time tracking of vehicle location  
  - Display of travel route and speed information  
  - Map display (based on Leaflet)

- **Debugging and Data Query Tools**  
  - API debugging tools (e.g., debug_api.php)  
  - Database connection and query status detection (e.g., debug_db.php)
  - Location testing tools (e.g., debug_location.php)

- **Maintenance Records**  
  - Administrators can record vehicle maintenance information  
  - Both frontend and backend provide maintenance log interfaces

## Installation and Configuration

1. **Clone the repository**  
   ```bash
   git clone https://github.com/yourusername/easy-ev-rental.git
   ```

2. **Configure the Web server**  
   - Deploy the project to a web server supporting PHP (such as Apache, Nginx, etc.).
   - Set the root directory to `\var\www\my_webapp\www`.

3. **Create database and import scripts**  
   - Use MySQL to create a database (e.g., `ev_rental_db`), then run the provided database scripts to generate necessary tables and views.

4. **Configure database connection**  
   - Edit the `Database.php` file, update the database host, database name, username, and password to match your environment.

5. **Set file permissions**  
   - Ensure the web server has appropriate read/write permissions for project files.

## Operation Logic

1. **User Access**  
   - Users can access `index.php` via a browser. The homepage provides login and registration entries, as well as a vehicle query entry (linked to `locations.php`).

2. **Vehicle Location and Booking**  
   - On the `locations.php` page, the system queries nearby vehicles based on the user's current geographic location or entered location.
   - After selecting a vehicle, the user makes a booking through an AJAX request to `api.php?action=reserveScooter`. Upon successful booking, the system returns the booking number and time information.

3. **Trip Tracking**  
   - After starting the trip, the user enters the `track.php` page. The system uses Leaflet to draw the vehicle's route on the map, showing real-time location and speed.
   - The control panel can be used to control trip animation, pause, reset, etc.

4. **Debugging and Data Monitoring**  
   - The system has built-in debug pages (such as `debug_api.php` and `debug_db.php`), allowing administrators to view real-time API debug information, database connection status, and vehicle status.
   - Logs are output by PHP's error_log for troubleshooting.

5. **Maintenance Management**  
   - The maintenance page (`maintenance.php`) allows administrators to enter and query vehicle maintenance records. After submitting the form, the system records the maintenance information in the database and returns appropriate prompts.

## Usage Guide

- **User Operations**  
  - **Registration/Login**: Click the registration or login button on the homepage, enter the necessary information to enter the system.
  - **Vehicle Query and Booking**: View nearby vehicle information on the `locations.php` page, select a desired vehicle and submit a booking request.
  - **Trip Tracking**: After a successful booking, click the start trip button to enter the tracking page and view real-time vehicle dynamics and trip information.

- **Administrator Operations**  
  - Access debugging tools and database monitoring pages to ensure the system is running properly.
  - Record vehicle maintenance information by entering repair logs via `maintenance.php`.

- **Development and Contribution**  
  - The project uses a modular design with frontend JavaScript files and backend PHP files handling specific functions.
  - Submit issues or pull requests to help fix problems and extend functionality.

## Database Structure

For detailed database structure documentation, please see [Database Documentation](docs/database-doc.md).

## API Reference

For API endpoints documentation, please see [API Reference](docs/api-reference.md).

## Development Notes

- Ensure all API requests go through authentication to prevent unauthorized access.
- Strictly validate all input parameters to avoid SQL injection and XSS attacks.
- Debugging tools should only be enabled in development and testing environments; disable or restrict access in production.
- Update the README file to reflect any new features or major changes.

## License

This project is licensed under the [MIT License](LICENSE). See the LICENSE file for details.

## References

- [PHP Official Documentation](https://www.php.net/manual/en/)
- [Leaflet Official Documentation](https://leafletjs.com/)
- [MySQL Query Optimization](https://dev.mysql.com/doc/)

---

# eASY 电动车租赁系统

[English Version](#easy-electric-vehicle-rental-system)

## 概述

eASY 是一个基于 PHP、JavaScript 和 MySQL 构建的电动租赁系统。该系统旨在提供实时车辆定位、在线预订和行程追踪等功能，方便用户租赁电动车辆。同时，系统内置多种调试和数据查询工具，便于管理员监控车辆状态与系统运行情况。

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

## 安装与配置

1. **克隆代码仓库**  
   ```bash
   git clone https://github.com/yourusername/easy-ev-rental.git
   ```

2. **配置 Web 服务器**  
   - 将项目部署到支持 PHP 的 Web 服务器（如 Apache、Nginx 等）。
   - 将根目录设置为 `\var\www\my_webapp\www`。

3. **创建数据库并导入脚本**  
   - 使用 MySQL 创建数据库（例如 `ev_rental_db`），然后运行提供的数据库脚本，生成必要的数据表和视图。

4. **配置数据库连接**  
   - 编辑 `Database.php` 文件，更新数据库主机、数据库名、用户名和密码配置以匹配您的环境。

5. **设置文件权限**  
   - 确保 Web 服务器对项目文件具有适当的读取/写入权限。

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

## 数据库结构

详细的数据库结构文档，请参阅 [数据库文档](docs/database-doc.md)。

## API 参考

有关 API 端点的文档，请参阅 [API 参考](docs/api-reference.md)。

## 开发注意事项

- 确保所有 API 请求均经过身份验证，防止未授权访问。
- 对所有输入参数进行严格验证，以避免 SQL 注入和 XSS 攻击。
- 调试工具仅在开发和测试环境中启用，生产环境请关闭或限制访问。
- 更新 README 文件以反映任何新特性或重大变更。

## 许可证

本项目采用 [MIT 许可证](LICENSE)。详细内容请查看 LICENSE 文件。

## 参考资料

- [PHP 官方文档](https://www.php.net/manual/zh/)
- [Leaflet 官方文档](https://leafletjs.com/)
- [MySQL 查询优化](https://dev.mysql.com/doc/) 