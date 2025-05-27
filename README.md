[阅读中文文档](README.zh-CN.md)

# eASY Electric Vehicle Rental System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**eASY** is a comprehensive Electric Vehicle (EV) Rental System designed for real-time vehicle tracking, online booking, and fleet management. It provides a user-friendly platform for renting EVs, supported by an Arduino-based GPS tracking solution.

## Key Features

*   **Real-time GPS Tracking**: Live map view of available vehicles.
*   **Online Booking System**: Users can reserve vehicles for specific time slots.
*   **User Account Management**: Secure registration, login, and profile management.
*   **Admin Dashboard**: Tools for fleet monitoring and maintenance logging.
*   **Arduino-based Tracking**: Custom hardware solution for sending vehicle data.
*   **Responsive Web Interface**: Accessible on desktop and mobile devices.

## Tech Stack

*   **Backend**: PHP, MySQL/MariaDB
*   **Frontend**: HTML, CSS, JavaScript (with Leaflet.js for maps)
*   **Hardware**: Arduino (UNO R4 WiFi or similar) with GPS module

## Project Structure

The project is organized into the following main directories:

*   `arduino/`: Contains the firmware for the GPS tracking device.
*   `backend/`: Houses the server-side PHP logic, API endpoints, and core functionalities.
*   `docs/`: Includes all project documentation.
*   `frontend/`: Contains the client-side user interface (HTML, CSS, JavaScript, and views).
*   `www/`: The web server's document root, containing the entry point `index.php` and structuring the public-facing part of the application.

## Documentation

Detailed documentation is available in the `docs/` directory:


*   **[Feature Status](docs/feature-status.md)**: Current status of implemented features.
*   **[Deployment and Configuration Guide](docs/DeploymentGuide.md)**: Complete setup instructions for server and Arduino.
*   **[System Architecture](docs/system-architecture.md)**: Overview of the system design, components, data flow, and database schema.
*   **[API Documentation](docs/api-documentation.md)**: Details on the available API endpoints.
*   **[Arduino Device Guide](docs/ArduinoDeviceGuide.md)**: Information about the Arduino tracker hardware and software.


## Getting Started

To set up the system, please refer to the **[Deployment and Configuration Guide](docs/DeploymentGuide.md)**.

A brief overview of server setup steps:
1.  Clone this repository.
2.  Set up your web server (Apache/Nginx) and PHP environment.
3.  Create a MySQL/MariaDB database and import the `ev_rental_db.sql` schema (found in the project root).
4.  Configure your database connection and application settings in `backend/config/config.php`.
5.  Configure the Arduino tracker as per the guide and deploy it.



## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details. 