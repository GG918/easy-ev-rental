# eASY Electric Vehicle Rental System

[中文版本](#easy-电动车租赁系统)

## Overview

eASY is an Electric Vehicle Rental System built with PHP, JavaScript, and MySQL. The system provides real-time vehicle tracking, online booking, and trip monitoring functionality, making it convenient for users to rent electric vehicles.

## Project Architecture

- **Frontend**  
  - HTML/CSS: Interface display and responsive layout  
  - JavaScript: Handles user interactions, map display (using Leaflet), booking requests, and other functions  
  - Modular code files: Such as `reservation.js`, `my_reservations.js`, `ui-service.js`, etc.

- **Backend**  
  - PHP: Handles business logic including user registration, login, booking, trip management, etc.  
  - MySQL/MariaDB: Stores user data, vehicle information, booking records, and GPS location data
  - RESTful API: Provides endpoints for frontend and IoT device communication

- **Tracking Hardware**
  - Arduino UNO R4 WiFi: Processes GPS data and communicates with server
  - AT6668 GPS SMA module: Provides real-time location data
  - Power management systems for energy efficiency
  - LED Matrix display for status indication

## Key Features

- **Real-time Vehicle Tracking**: View available vehicles on an interactive map
- **User Account Management**: Registration, login, profile management
- **Booking System**: Reserve vehicles with specified time periods
- **Trip History**: View past trips and usage statistics
- **Admin Tools**: Monitor fleet status, manage maintenance records
- **Responsive Design**: Works on both desktop and mobile devices

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- [Installation Guide](docs/installation.md)
- [Configuration Guide](docs/configuration.md)
- [API Documentation](docs/api-documentation.md)
- [System Architecture](docs/system-architecture.md)
- [Database Schema](docs/database-schema.md)
- [Coding Standards](docs/code-standards.md)
- [Arduino Tracker Documentation](docs/arduino-tracker.md)
- [Feature Status](docs/feature-status.md)

## Installation

See the [detailed installation guide](docs/installation.md) for complete setup instructions. Quick start:

1. Clone the repository
2. Import `ev_rental_db.sql` into your MySQL/MariaDB database
3. Configure database connection in `Database.php`
4. Set up your web server (Apache or Nginx)
5. Access the application via your web browser

## Contributing

We welcome contributions to improve the eASY Electric Vehicle Rental System! Please read our [contribution guidelines](CONTRIBUTING.md) before submitting pull requests.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

# eASY 电动车租赁系统

[English Version](#easy-electric-vehicle-rental-system)

## 概览

eASY 是一个使用 PHP、JavaScript 和 MySQL 构建的电动车租赁系统。该系统提供实时车辆跟踪、在线预订和行程监控功能，方便用户租赁电动车。

## 项目架构

- **前端**  
  - HTML/CSS：界面显示和响应式布局  
  - JavaScript：处理用户交互、地图显示（使用 Leaflet）、预订请求等功能  
  - 模块化代码文件：如 `reservation.js`、`my_reservations.js`、`ui-service.js` 等

- **后端**  
  - PHP：处理业务逻辑，包括用户注册、登录、预订、行程管理等  
  - MySQL/MariaDB：存储用户数据、车辆信息、预订记录和 GPS 位置数据
  - RESTful API：为前端和物联网设备通信提供接口

- **跟踪硬件**
  - Arduino UNO R4 WiFi：处理 GPS 数据并与服务器通信
  - AT6668 GPS SMA 模块：提供实时位置数据
  - 电源管理系统，提高能源效率
  - LED矩阵显示屏，用于状态指示

## 主要功能

- **实时车辆跟踪**：在交互式地图上查看可用车辆
- **用户账户管理**：注册、登录、个人资料管理
- **预订系统**：在指定时间段预订车辆
- **行程历史**：查看过去的行程和使用统计
- **管理工具**：监控车队状态，管理维护记录
- **响应式设计**：同时支持桌面和移动设备

## 文档

详细文档可在 `docs/` 目录中找到：

- [安装指南](docs/installation.md)
- [配置指南](docs/configuration.md)
- [API 文档](docs/api-documentation.md)
- [系统架构](docs/system-architecture.md)
- [数据库架构](docs/database-schema.md)
- [编码标准](docs/code-standards.md)
- [Arduino 跟踪器文档](docs/arduino-tracker.md)
- [功能状态](docs/feature-status.md)

## 安装

完整安装说明请参阅[详细安装指南](docs/installation.md)。快速开始：

1. 克隆仓库
2. 将 `ev_rental_db.sql` 导入到您的 MySQL/MariaDB 数据库
3. 在 `Database.php` 中配置数据库连接
4. 设置您的 Web 服务器（Apache 或 Nginx）
5. 通过 Web 浏览器访问应用程序

## 贡献

我们欢迎贡献来改进 eASY 电动车租赁系统！提交拉取请求前，请阅读我们的[贡献指南](CONTRIBUTING.md)。

## 许可证

本项目采用 MIT 许可证 - 详情请参阅 [LICENSE](LICENSE) 文件。 