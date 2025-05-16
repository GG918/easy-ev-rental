# Configuration Guide

This document outlines all the settings and addresses that need to be customized when deploying the eASY Electric Vehicle Rental System in your environment.

## Server Configuration

### Database Connection (`Database.php`)

```php
private $host = "localhost";         // Change to your database host
private $db_name = "ev_rental_db";   // Change to your database name
private $username = "ev_user";       // Change to your database username
private $password = "your_password"; // Change to your database password
```

### Server Domain Settings

If you're using a custom domain, update the following files:

- `js/data-service.js` - Update API base URL
  ```javascript
  const API_BASE_URL = 'https://yourdomain.com/api.php';
  ```

- `.htaccess` - Update RewriteBase if needed
  ```apache
  RewriteBase /your-subdirectory/   # If installed in a subdirectory
  ```

## Arduino Tracker Configuration

### WiFi and Server Settings (`arduino/ev_tracker.ino`)

```cpp
// WiFi credentials
const char* ssid       = "YOUR_WIFI_SSID";      // Change to your WiFi SSID
const char* password   = "YOUR_WIFI_PASSWORD";  // Change to your WiFi password

// Server settings
const char* serverHost = "example.com";         // Change to your domain
const int   serverPort = 80;                    // Change port if needed

// Device identification
const char* deviceId   = "1";                   // Assign unique ID to each device
```

### Upload Interval

```cpp
// Reporting interval (milliseconds)
const unsigned long reportInterval = 60UL * 1000;  // Default: 60 seconds
```

## Security Configuration

### API Key (if using API key authentication)

Add your generated API key to the appropriate files:

- `includes/helpers.php`
  ```php
  define('API_KEY', 'your-secret-api-key');
  ```

### HTTPS Configuration

If using HTTPS (recommended for production):

1. Configure SSL certificates in your web server
2. Update JavaScript references to use `https://` instead of `http://`
3. Update Arduino code to use HTTPS if supported:
   ```cpp
   const int serverPort = 443;  // HTTPS port
   ```

## Map Configuration

### Leaflet.js API Key (if using premium features)

Update the map API key in:

- `js/map-service.js`
  ```javascript
  const MAP_API_KEY = 'your-map-api-key';
  ```

### Default Map Center

```javascript
// Default map center coordinates
const DEFAULT_LAT = 53.3811;  // Change to your location's latitude
const DEFAULT_LNG = -1.4701;  // Change to your location's longitude
const DEFAULT_ZOOM = 15;      // Change default zoom level
```

## Email Configuration (if applicable)

If your system sends email notifications:

```php
// In relevant PHP files
$mail_host = 'smtp.yourdomain.com';
$mail_username = 'notifications@yourdomain.com';
$mail_password = 'your-email-password';
$mail_port = 587;
```

## Additional Settings

### Debug Mode

```php
// In includes/helpers.php or similar file
define('DEBUG_MODE', false);  // Set to true for development, false for production
```

### Session Configuration

```php
// In auth.php or session management file
ini_set('session.cookie_lifetime', 3600);     // Session timeout in seconds
ini_set('session.gc_maxlifetime', 3600);      // Session garbage collection
```

## Configuration Checklist

Before launching to production, ensure you have:

- [ ] Updated database connection details
- [ ] Configured proper domain names
- [ ] Updated Arduino WiFi credentials
- [ ] Set unique device IDs for each tracker
- [ ] Configured SSL/HTTPS if needed
- [ ] Updated map center coordinates
- [ ] Disabled debug mode
- [ ] Set appropriate session timeouts

---

For advanced configuration options or environment-specific settings, please refer to the appropriate documentation sections or contact the system administrator. 