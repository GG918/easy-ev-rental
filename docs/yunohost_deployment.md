# eASY 系统在 YunoHost 环境中的部署说明

## 概述

本文档介绍了 eASY 电动车租赁系统在 YunoHost 环境中的部署和配置步骤。YunoHost 是一个基于 Debian 的开源服务器操作系统，旨在简化网络应用的部署和管理。

## 环境要求

- YunoHost 4.x 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本 (MariaDB 10.x 也可以)
- 已启用的 PHP 扩展：pdo, pdo_mysql, json, curl, mbstring

## 部署步骤

### 1. 准备 YunoHost 环境

- 确保 YunoHost 系统已正确安装并配置
- 检查系统是否满足上述环境要求

### 2. 应用安装

YunoHost 部署方式有两种选择：

#### 方式一：从 YunoHost 官方应用商店安装（如可用）

1. 登录 YunoHost 管理界面
2. 进入应用页面，搜索 "eASY"
3. 点击安装，填写必要信息

#### 方式二：手动安装

1. 将系统文件上传到服务器的 `/var/www/my_webapp/www` 目录
2. 导入初始数据库结构（找到项目根目录下的 SQL 文件）
3. 配置数据库连接（编辑 `backend/config/config.php` 文件）
4. 设置适当的权限和所有者

```bash
# 设置权限
sudo chown -R www-data:www-data /var/www/my_webapp/www
sudo chmod -R 755 /var/www/my_webapp/www
```

### 3. 路径兼容性配置

本系统已经内置了对 YunoHost 路径前缀的自动检测和适配功能。主要通过以下机制实现：

- 通过 `.htaccess` 文件中的 `RewriteRule` 检测应用路径前缀
- 使用 `backend/includes/utils.php` 中的 `get_base_path()` 和 `url()` 函数自动生成正确的URL

如果系统仍然出现路径问题，可以访问环境检测页面进行诊断：
```
https://yourdomain.tld/path/to/app/debug/environment.php
```

### 4. 数据库配置

编辑 `backend/config/config.php` 文件，配置正确的数据库连接信息：

```php
'db' => [
    'host' => 'localhost',
    'username' => 'your_db_username',
    'password' => 'your_db_password',
    'database' => 'your_db_name',
    'port' => 3306,
    'charset' => 'utf8mb4'
]
```

### 5. 访问权限设置

确保 Web 服务器能够访问应用目录，并设置适当的权限：

```bash
# 设置目录所有权
sudo chown -R www-data:www-data /var/www/my_webapp/www

# 设置合适的目录权限
sudo find /var/www/my_webapp/www -type d -exec chmod 755 {} \;
sudo find /var/www/my_webapp/www -type f -exec chmod 644 {} \;
```

## 故障排查

### 常见问题

1. **页面无法加载或路径错误**
   - 检查 `.htaccess` 文件是否被正确处理
   - 确认 Apache 的 `mod_rewrite` 模块已启用
   - 访问 `debug/environment.php` 查看环境信息

2. **数据库连接失败**
   - 验证数据库凭据是否正确
   - 确认数据库服务是否运行
   - 检查数据库用户权限

3. **权限问题**
   - 确保 Web 服务器用户（通常是 www-data）拥有适当的文件访问权限

### 诊断工具

系统提供了环境诊断工具，帮助识别和解决问题：

- `/debug/environment.php` - 显示系统环境信息
- `/debug/php_info.php` - 显示详细的 PHP 配置（仅限本地或内部网络访问）

## 更新过程

更新 eASY 系统在 YunoHost 环境中的步骤：

1. 备份当前系统和数据库
2. 替换系统文件（保留配置文件）
3. 若有数据库更新，执行相应的迁移脚本
4. 清除缓存并检查权限

## 联系与支持

如果遇到问题或需要帮助，请通过以下方式联系我们：

- 项目主页：[项目链接]
- 问题跟踪：[问题跟踪链接]
- 邮件支持：[support@email.com]

---

*本文档最后更新于 2025 年 5 月* 