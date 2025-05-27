# eASY 项目清理计划

## 重复文件分析

### 1. htdocs/ 目录 (旧版本完整备份)
**状态**: 可以安全删除，已创建备份 `htdocs_backup_20250527_024938.tar.gz`

**包含的重复文件**:
- `htdocs/Database.php` → 已迁移到 `backend/core/Database.php` (更新版本)
- `htdocs/auth.php` → 已迁移到 `backend/core/auth.php`
- `htdocs/api.php` → 已迁移到 `backend/api/legacy_api.php`
- `htdocs/index.php` → 已迁移到 `frontend/views/index.php`
- `htdocs/locations.php` → 已迁移到 `frontend/views/locations.php`
- `htdocs/my_reservations.php` → 已迁移到 `frontend/views/my_reservations.php`
- `htdocs/js/` → 已迁移到 `frontend/public/js/`
- `htdocs/css/` → 已迁移到 `frontend/public/css/`
- `htdocs/images/` → 已迁移到 `frontend/public/images/`

### 2. debug/ 目录 (调试文件)
**状态**: 生产环境不需要，建议删除

**包含文件**:
- `debug_api.php` - API调试工具
- `debug_db.php` - 数据库调试工具
- `debug_location.php` - 位置调试工具
- `apache_test.php` - Apache配置测试
- `environment.php` - 环境信息
- `path_test.php` - 路径测试
- `php_info.php` - PHP信息
- `arduino_data.log` - Arduino日志文件

### 3. 重复的index.php文件
**需要保留**:
- `index.php` (主入口)
- `view/index.php` (路由文件)
- `frontend/views/index.php` (首页视图)

**可以删除**:
- `frontend/index.php` (简单重定向文件，功能重复)

### 4. 其他可能的重复文件
- 检查是否有其他临时文件或备份文件

## 清理步骤

### 第一步: 删除调试文件 (安全)
```bash
rm -rf debug/
```

### 第二步: 删除重复的frontend/index.php
```bash
rm frontend/index.php
```

### 第三步: 删除htdocs目录 (已备份)
```bash
rm -rf htdocs/
```

### 第四步: 清理其他临时文件
```bash
find . -name "*.tmp" -delete
find . -name "*.bak" -delete
find . -name "*~" -delete
```

## 风险评估

**低风险**:
- debug/ 目录 - 纯调试工具
- frontend/index.php - 简单重定向

**中等风险**:
- htdocs/ 目录 - 已备份，功能已迁移

**建议**:
1. 先删除低风险文件
2. 测试系统功能
3. 确认无问题后删除中等风险文件

## 预期效果

**磁盘空间节省**: 约 3-5MB
**文件数量减少**: 约 50+ 个文件
**维护复杂度降低**: 消除重复代码和配置
**项目结构清晰**: 只保留active的代码 