[Return to English documentation (返回英文文档)](README.md)

# eASY 电动汽车租赁系统 (中文版)

[![许可证: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**eASY** 是一个全面的电动汽车 (EV) 租赁系统，专为实时车辆跟踪、在线预订和车队管理而设计。它提供了一个用户友好的电动汽车租赁平台，并辅以基于 Arduino 的 GPS 跟踪解决方案。

## 主要特性

*   **实时 GPS 追踪**: 可用车辆的实时地图视图。
*   **在线预订系统**: 用户可以预订特定时间段的车辆。
*   **用户账户管理**: 安全的注册、登录和个人资料管理。
*   **管理员仪表盘**: 用于车队监控和维护日志记录的工具。
*   **基于 Arduino 的追踪**: 用于发送车辆数据的定制硬件解决方案。
*   **响应式 Web 界面**: 可在桌面和移动设备上访问。

## 技术栈

*   **后端**: PHP, MySQL/MariaDB
*   **前端**: HTML, CSS, JavaScript (使用 Leaflet.js 实现地图功能)
*   **硬件**: Arduino (UNO R4 WiFi 或类似型号) 及 GPS 模块

## 项目结构

项目主要包含以下目录：

*   `arduino/`: 包含 GPS 追踪设备的固件。
*   `backend/`: 包含服务器端 PHP 逻辑、API 端点和核心功能。
*   `docs/`: 包含所有项目文档。
*   `frontend/`: 包含客户端用户界面 (HTML, CSS, JavaScript 和视图)。
*   `www/`: Web 服务器的文档根目录，包含入口文件 `index.php` 并构建面向公众的应用部分。

## 文档

详细文档位于 `docs/` 目录中：

*   **[功能状态 (Feature Status)](docs/feature-status.md)**: 已实现功能的当前状态。
*   **[部署与配置指南 (Deployment and Configuration Guide)](docs/DeploymentGuide.md)**: 服务器和 Arduino 的完整设置说明。
*   **[系统架构 (System Architecture)](docs/system-architecture.md)**: 系统设计、组件、数据流和数据库模式概述。
*   **[API 文档 (API Documentation)](docs/api-documentation.md)**: 可用 API 端点的详细信息。
*   **[Arduino 设备指南 (Arduino Device Guide)](docs/ArduinoDeviceGuide.md)**: 关于 Arduino 追踪器硬件和软件的信息。

##快速入门

要设置此系统，请参阅 **[部署与配置指南 (Deployment and Configuration Guide)](docs/DeploymentGuide.md)**。

服务器设置步骤简述：
1.  克隆此仓库。
2.  设置您的 Web 服务器 (Apache/Nginx) 和 PHP 环境。
3.  创建 MySQL/MariaDB 数据库并导入 `ev_rental_db.sql` 数据库结构文件 (位于项目根目录)。
4.  在 `backend/config/config.php` 中配置您的数据库连接和应用设置。
5.  根据指南配置 Arduino 追踪器并部署。

## 许可证

本项目采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。 