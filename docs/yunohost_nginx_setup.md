# eASY系统 - YunoHost Nginx配置指南

## 问题概述

我们发现系统在YunoHost环境中出现了路径问题，这是因为YunoHost默认使用**Nginx**而不是Apache服务器。所有之前基于`.htaccess`的配置都不会生效，需要使用Nginx配置来实现相同的URL重写功能。

## 解决方案步骤

### 1. 确认Nginx配置文件位置

在YunoHost环境中，Nginx配置文件通常位于以下位置：

```
/etc/nginx/sites-available/my_webapp.conf
```

对应的软链接存在于：

```
/etc/nginx/sites-enabled/my_webapp.conf
```

### 2. 修改Nginx配置

请将以下配置添加到您的应用配置文件中，或者请管理员帮您修改：

```nginx
# eASY系统Nginx配置
location __PATH__ {
    alias __INSTALL_DIR__/;
    
    index index.php;
    
    # 重写规则 - 类似.htaccess中的规则
    location ~ ^__PATH__/view/([^/]+)/?$ {
        try_files $uri $uri/ __PATH__/frontend/views/$1.php?$args;
    }
    
    location ~ ^__PATH__/api/([^/]+)/?$ {
        try_files $uri $uri/ __PATH__/backend/api/api.php?endpoint=$1&$args;
    }
    
    location ~ ^__PATH__/api/([^/]+)/([^/]+)/?$ {
        try_files $uri $uri/ __PATH__/backend/api/api.php?endpoint=$1&id=$2&$args;
    }
    
    # PHP处理
    location ~ [^/]\.php$ {
        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        fastcgi_pass unix:/var/run/php/php__PHPVERSION__-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param REMOTE_USER $remote_user;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
    
    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
    }
    
    # 阻止访问隐藏文件
    location ~ /\. {
        deny all;
    }
}
```

**注意**：YunoHost使用变量占位符（如`__PATH__`）来管理应用路径。保留这些变量，YunoHost安装脚本会自动将它们替换为正确的值。

### 3. 应用配置和重启服务器

修改配置后：

```bash
# 检查Nginx配置是否有语法错误
sudo nginx -t

# 如果没有错误，重新加载Nginx配置
sudo systemctl reload nginx

# 或者重启Nginx服务
sudo systemctl restart nginx
```

## 进一步修复

如果使用上述配置后仍然出现问题，请考虑：

1. **检查用户和权限**：
   ```bash
   # 确保文件所有权正确
   sudo chown -R www-data:www-data /var/www/my_webapp/www/
   
   # 设置适当的权限
   sudo find /var/www/my_webapp/www/ -type d -exec chmod 755 {} \;
   sudo find /var/www/my_webapp/www/ -type f -exec chmod 644 {} \;
   ```

2. **检查PHP-FPM配置**：
   ```bash
   # 重启PHP-FPM服务
   sudo systemctl restart php7.4-fpm  # 根据您的PHP版本调整
   ```

3. **检查日志文件**以获取详细错误信息：
   ```bash
   # Nginx错误日志
   sudo tail -f /var/log/nginx/error.log
   
   # PHP错误日志
   sudo tail -f /var/log/php7.4-fpm.log  # 根据您的PHP版本调整
   ```

## 在PHP代码中适配YunoHost环境

我们已经创建了适用于Nginx环境的路径处理函数。以下是使用指南：

1. 所有URL路径都应该使用`url()`函数生成
2. URL规则是基于目录结构的，如`/view/index`会被重写到`/frontend/views/index.php`
3. 如需调试，可访问`/debug/path_test.php`查看当前环境信息

## 注意事项

- YunoHost管理的应用通常有一个专门的安装目录和URL路径前缀
- 确保所有内部链接使用相对路径或通过`url()`函数生成
- 不要依赖于`.htaccess`文件，它在Nginx环境中不起作用 