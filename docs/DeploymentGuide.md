# Deployment and Configuration Guide

This guide provides step-by-step instructions for setting up and configuring the Electric Vehicle Rental System on your server and for the Arduino tracking devices.

## Part 1: Server Setup and Configuration

### Prerequisites

- PHP 7.4+ or PHP 8.0+
- MySQL/MariaDB 10.4+
- Apache or Nginx web server
- Composer (for dependency management)
- Git (for version control)

### Server Installation

#### 1. Clone the Repository

```bash
git clone https://github.com/gg918/easy-ev-rental.git
cd easy-ev-rental
```

#### 2. Database Setup

1.  Create a new MySQL/MariaDB database:

    ```sql
    CREATE DATABASE ev_rental_db;
    CREATE USER 'ev_user'@'localhost' IDENTIFIED BY 'your_secure_password';
    GRANT ALL PRIVILEGES ON ev_rental_db.* TO 'ev_user'@'localhost';
    FLUSH PRIVILEGES;
    ```
2.  Import the database schema:

    ```bash
    mysql -u ev_user -p ev_rental_db < ev_rental_db.sql
    ```

#### 3. Web Server Configuration

##### Apache

Create or modify `.htaccess` file in the project root:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

##### Nginx

Configure the server block (example):

```nginx
server {
    listen 80;
    server_name yourdomain.com; # Replace with your domain
    root /path/to/your/project/www; # Adjust to your project's root
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \\.php$ {
        include snippets/fastcgi-php.conf; # Standard Nginx PHP FPM config snippet
        fastcgi_pass unix:/var/run/php/phpX.X-fpm.sock; # Adjust to your PHP-FPM socket
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\\. {
        deny all;
    }
}
```
*Note: A more detailed Nginx example was previously in `docs/nginx-config.conf` but has been removed by user request. Refer to Nginx documentation for advanced configurations.*


#### 4. Permissions

Ensure proper permissions for web server access:

```bash
# Adjust paths and user/group as necessary for your environment
# sudo chown -R www-data:www-data /path/to/your/project/www
sudo chmod -R 755 /path/to/your/project/www
# If you have specific writable directories (e.g., for uploads or cache, though not explicitly used in current core app):
# sudo chmod -R 777 /path/to/your/project/www/writable_directory
```

### Server Configuration Details

This section outlines all the settings that need to be customized when deploying the eASY Electric Vehicle Rental System in your environment.

#### 1. Database Connection (`backend/config/config.php`)

Ensure your `backend/config/config.php` file contains the correct database credentials:

```php
<?php
/**
 * Database configuration file
 */
return [
    'db' => [
        'host' => 'localhost',        // Change to your database host
        'username' => 'ev_user',      // Change to your database username
        'password' => 'your_secure_password', // Change to your database password
        'database' => 'ev_rental_db', // Change to your database name
        'port' => 3306,
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'debug' => false, // Set to true for development, false for production
        'timezone' => 'UTC', // Adjust to your server's timezone, e.g., 'Europe/London'
        'session_lifetime' => 7200 // Session timeout in seconds (e.g., 2 hours)
    ]
];
```
*(Note: The actual `Database.php` class reads this configuration file. The example in the original `installation.md` showing direct credentials in `Database.php` was illustrative of an older setup; the current system uses `backend/config/config.php`.)*

#### 2. Server Domain Settings

If you're using a custom domain, ensure all client-side and server-side references point to it.
The `backend/includes/utils.php` file contains a `get_base_path()` function to help generate relative URLs correctly.

- **Client-Side (JavaScript)**:
    - `frontend/public/js/data-service.js`: The `API_BASE_URL` is typically relative (e.g., `backend/api/api.php`). Ensure this resolves correctly based on your domain and server setup.
    If an absolute path is needed, it would be:
      ```javascript
      // const API_BASE_URL = 'https://yourdomain.com/backend/api/api.php';
      ```

- **Server-Side (`.htaccess` for Apache)**:
    - If your application is installed in a subdirectory, you might need to adjust `RewriteBase` in your `.htaccess` file (though the current `utils.php` aims to handle subdirectories automatically):
      ```apache
      # RewriteBase /your-subdirectory/
      ```

#### 3. Security Configuration

##### HTTPS Configuration

If using HTTPS (recommended for production):

1.  Configure SSL certificates in your web server (Apache/Nginx).
2.  Ensure all client-side (JavaScript) and server-side URL generations use `https://`.
3.  Update Arduino code (`arduino/ev_tracker.ino`) to use HTTPS if your server is configured for it:
    ```cpp
    // const int serverPort = 443; // HTTPS port
    // WiFiClientSecure client; // If using HTTPS, you might need to use WiFiClientSecure
    ```
    *(Note: Using `WiFiClientSecure` often requires additional setup for SSL certificates on the Arduino.)*

##### API Key (Not currently implemented for core API)

The system's core API (`api.php`) primarily uses session-based authentication. The `store.php` endpoint (for Arduino data) does not currently enforce API key authentication but relies on device identifiers.
If you were to implement API key authentication for other parts or enhance `store.php`:
  - You would typically define an API key in a configuration file (e.g., `backend/config/config.php`) or environment variable.
  - And check it in the relevant PHP scripts. Example (conceptual):
    ```php
    // define('API_KEY', 'your-secret-api-key');
    // ...
    // if ($_SERVER['HTTP_X_API_KEY'] !== API_KEY) { /* deny access */ }
    ```

#### 4. Map Configuration (Client-Side)

- **Leaflet.js API Key**: Leaflet.js itself is open-source and doesn't require an API key for its core library. However, if you use specific tile providers (map layers) that require an API key, you would configure that in your JavaScript map initialization code (e.g., in `frontend/public/js/map-service.js` or `init.js`).

- **Default Map Center** (`frontend/public/js/init.js` or `map-service.js`):
  Adjust the default map center coordinates and zoom level to suit your primary operational area.
  ```javascript
  // Example, actual variable names might differ
  const DEFAULT_LAT = 53.3811;  // Change to your location's latitude
  const DEFAULT_LNG = -1.4701;  // Change to your location's longitude
  const DEFAULT_ZOOM = 15;      // Change default zoom level
  ```

#### 5. Email Configuration (If implementing email features)

The current core system does not have extensive email features. If you add email notifications (e.g., for registration, booking confirmations):
  - You would typically configure SMTP settings in `backend/config/config.php` or use a mail library that allows such configuration.
  ```php
  // Example for config.php:
  /*
  'mail' => [
      'driver' => 'smtp',
      'host' => 'smtp.yourdomain.com',
      'port' => 587,
      'username' => 'notifications@yourdomain.com',
      'password' => 'your-email-password',
      'encryption' => 'tls', // or 'ssl'
      'from_address' => 'noreply@yourdomain.com',
      'from_name' => 'eASY System'
  ]
  */
  ```

#### 6. Additional Settings

- **Debug Mode**:
  Controlled in `backend/config/config.php` via `'app' => ['debug' => false,]`. Set to `true` for development to see detailed errors, and `false` for production.

- **Session Configuration**:
  Session lifetime is managed in `backend/config/config.php` via `'app' => ['session_lifetime' => 7200,]`.
  PHP's session garbage collection (`session.gc_maxlifetime`, etc.) can also be configured in `php.ini` or at runtime if needed, though `session_lifetime` in the app config is the primary control for user session expiry.

### Final Server Setup Steps

1.  Visit your domain in a web browser.
2.  Register an admin account (the first registered user might not automatically be admin; this needs to be set manually in the database or via an admin creation script if not implemented).
3.  Test all functionalities.

### Server Troubleshooting

If you encounter any issues:

1.  Check PHP error logs (location depends on your PHP and web server setup).
2.  Check web server error logs (Apache/Nginx).
3.  Ensure database connection details in `backend/config/config.php` are correct and the database user has permissions.
4.  Verify web server configuration (rewrite rules, PHP handler).
5.  Test API endpoints directly using tools like Postman or cURL.
6.  Ensure file permissions are correct.

## Part 2: Arduino Tracking Device Setup and Configuration

This section covers setting up the Arduino tracking device. For more details on the device's hardware, features, operation, and troubleshooting beyond setup, see `ArduinoDeviceGuide.md`.

### Arduino Hardware Requirements

- Arduino Uno R4 WiFi
- AT6668 GPS SMA module (or compatible)
- LED Matrix (built into the Arduino R4, or external if using a different board)
- Connecting wires
- Secure enclosure for outdoor use
- Power supply (5-12V, regulated for Arduino input)

### Arduino Wiring

Connect the GPS module to the Arduino (referencing `arduino/ev_tracker.ino` for pin definitions):
- GPS TX → Arduino Digital Pin specified for `gpsSerial` RX (e.g., D3)
- GPS RX → Arduino Digital Pin specified for `gpsSerial` TX (e.g., D4)
- GPS VCC → Arduino 5V
- GPS GND → Arduino GND

### Arduino Code Installation & Configuration

1.  **Install the Arduino IDE**: Download from the official Arduino website (version 2.0 or later recommended).
2.  **Install Required Libraries**:
    Open the Arduino IDE and go to Sketch > Include Library > Manage Libraries. Install the following:
    - `SoftwareSerial` (usually built-in for Uno-like boards)
    - `TinyGPSPlus` by Mikal Hart
    - `ArduinoGraphics` (for Arduino UNO R4 WiFi's LED Matrix)
    - `Arduino_LED_Matrix` (for Arduino UNO R4 WiFi's LED Matrix)
    - `WiFiS3` (for Arduino UNO R4 WiFi's networking)
    - `ArduinoJson` by Benoit Blanchon (version 6.x recommended)

3.  **Open the Sketch**:
    Open the `arduino/ev_tracker.ino` file from this project in the Arduino IDE.

4.  **Configure Sketch Parameters**:
    Modify the following constants at the beginning of `arduino/ev_tracker.ino`:

    ```cpp
    // WiFi credentials
    const char* ssid       = "YOUR_WIFI_SSID";      // Replace with your WiFi network SSID
    const char* password   = "YOUR_WIFI_PASSWORD";  // Replace with your WiFi network password

    // Server settings
    const char* serverHost = "yourdomain.com"; // Replace with your server's domain or IP address
    const int   serverPort = 80;              // Default HTTP port. Use 443 for HTTPS (requires WiFiClientSecure and certs)

    // Device identification
    const char* deviceId   = "1"; // Assign a unique ID to each tracking device

    // Reporting interval (milliseconds)
    const unsigned long reportInterval = 60UL * 1000;  // Default: 60 seconds (data upload frequency)
    ```

5.  **Upload the Sketch**:
    - Connect your Arduino board to your computer via USB.
    - In the Arduino IDE, select the correct board (e.g., "Arduino UNO R4 WiFi") from Tools > Board.
    - Select the correct Port from Tools > Port.
    - Click the "Upload" button (right arrow icon).

### Testing the Arduino Tracker

1.  **Serial Monitor**:
    Open the Arduino Serial Monitor (Tools > Serial Monitor) and set the baud rate to 115200.
    You should observe:
    - Initialization messages.
    - WiFi connection status.
    - GPS data (once a fix is obtained).
    - Confirmation of data transmission to the server.

2.  **LED Matrix**:
    The on-board LED matrix (if using Arduino UNO R4 WiFi) should display status:
    - "G" (Green): GPS fix obtained and operating normally.
    - "X" (Red): No GPS data received or an error.
    - Scrolling Dots (Yellow): Waiting for GPS fix.
    *(Refer to `showSymbol()` and `showScrollingDots()` in `ev_tracker.ino`)*

3.  **Server-Side Verification**:
    - Check for new entries in the `Locations` table in your `ev_rental_db` database.
    - If you have logging enabled on the server for the `/store.php` endpoint, check those logs.
    - The vehicle should appear on the map interface of the web application once it sends data.

### Deploying the Arduino Tracker

1.  **Enclosure**: Place the Arduino and GPS module in a weatherproof and durable case.
2.  **Antenna Placement**: Ensure the GPS antenna has a clear view of the sky for optimal signal reception.
3.  **Power**: Connect to a stable and reliable power source from the vehicle (e.g., vehicle battery via a suitable voltage regulator to provide 5V to the Arduino).
4.  **Mounting**: Securely mount the device on the vehicle in a location that protects it from damage and tampering, while allowing GPS and WiFi signals.
5.  **Field Test**: Test the complete system in real-world operating conditions.

### Server Configuration Checklist (Summary)

Before launching to production, ensure you have:

- [ ] Updated database connection details in `backend/config/config.php`.
- [ ] Configured correct domain names/IPs for server and client-side if necessary.
- [ ] Disabled debug mode (`'debug' => false`) in `backend/config/config.php`.
- [ ] Set appropriate session timeouts in `backend/config/config.php`.
- [ ] Configured SSL/HTTPS on your web server if used, and updated client/Arduino references.
- [ ] Adjusted default map center coordinates in JavaScript if needed.

---

This guide should provide a comprehensive overview for deploying and configuring the system. For specific component details, refer to other documents in this `docs` folder. 