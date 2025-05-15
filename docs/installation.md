# Installation Guide

This guide provides step-by-step instructions for setting up the Electric Vehicle Rental System on your server.

## Prerequisites

- PHP 7.4+ or PHP 8.0+
- MySQL/MariaDB 10.4+
- Apache or Nginx web server
- Composer (for dependency management)
- Git (for version control)

## Server Setup

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/ev-rental-system.git
cd ev-rental-system
```

### 2. Database Setup

1. Create a new MySQL/MariaDB database:

```sql
CREATE DATABASE ev_rental_db;
CREATE USER 'ev_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON ev_rental_db.* TO 'ev_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Import the database schema:

```bash
mysql -u ev_user -p ev_rental_db < ev_rental_db.sql
```

### 3. Configure Database Connection

Create or modify the database configuration file:

```php
// Database.php
<?php
class Database {
    private $host = "localhost";
    private $db_name = "ev_rental_db";
    private $username = "ev_user";
    private $password = "your_secure_password";
    public $conn;
    
    // Get database connection
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
```

### 4. Web Server Configuration

#### Apache

Create or modify `.htaccess` file:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

#### Nginx

Configure the server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/ev-rental-system;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
    }
}
```

### 5. Permissions

Ensure proper permissions for web server access:

```bash
chmod -R 755 /path/to/ev-rental-system
chmod -R 777 /path/to/ev-rental-system/tmp  # If you have a temp directory
```

## Arduino Setup (For Tracking Devices)

### 1. Hardware Requirements

- Arduino Uno R4 WiFi
- AT6668 GPS module
- Connecting wires

### 2. Wiring

Connect the GPS module to the Arduino:
- GPS TX → Arduino RX
- GPS RX → Arduino TX
- GPS VCC → Arduino 5V
- GPS GND → Arduino GND

### 3. Arduino Code Installation

1. Install the Arduino IDE
2. Install required libraries:
   - WiFiNINA (for Arduino R4 WiFi)
   - TinyGPSPlus (for GPS parsing)
   - ArduinoHttpClient (for HTTP requests)

3. Upload the tracking code from the `arduino/` directory to your Arduino board.

4. Configure WiFi credentials in the Arduino sketch:

```cpp
// Replace with your network credentials
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// Server API endpoint
const char* serverUrl = "http://your-domain.com/api.php";
```

## Final Steps

1. Visit your domain in a web browser
2. Register an admin account
3. Configure the system settings

## Troubleshooting

If you encounter any issues:

1. Check PHP error logs
2. Ensure database connection is working properly
3. Verify web server configuration
4. Test API endpoints using tools like Postman

For more detailed troubleshooting, refer to the `docs/troubleshooting.md` file. 