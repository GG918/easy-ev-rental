# Coding Standards and Guidelines

This document outlines the coding standards and best practices for the Electric Vehicle Rental System project. Adhering to these guidelines ensures code consistency, maintainability, and collaboration efficiency.

## General Guidelines

1. Write clean, readable, and self-documenting code
2. Follow the DRY (Don't Repeat Yourself) principle
3. Keep functions and methods short and focused on a single responsibility
4. Use meaningful variable and function names
5. Add comments for complex logic, but avoid unnecessary comments
6. Implement proper error handling with meaningful error messages
7. Write unit tests for critical functionality

## Naming Conventions

### PHP

- **Classes**: Use PascalCase
  ```php
  class DatabaseConnection {}
  class UserController {}
  ```

- **Functions/Methods**: Use snake_case
  ```php
  function get_user_details() {}
  function update_vehicle_status() {}
  ```

- **Variables**: Use snake_case
  ```php
  $user_id = 1;
  $vehicle_status = 'available';
  ```

- **Constants**: Use UPPER_SNAKE_CASE
  ```php
  define('API_KEY', 'abc123');
  const MAX_BOOKING_HOURS = 24;
  ```

### JavaScript

- **Functions**: Use camelCase
  ```javascript
  function getUserLocation() {}
  function updateMapMarkers() {}
  ```

- **Variables**: Use camelCase
  ```javascript
  let userId = 1;
  const mapCenter = [53.3811, -1.4701];
  ```

- **Classes**: Use PascalCase
  ```javascript
  class LocationService {}
  class BookingManager {}
  ```

- **Constants**: Use UPPER_SNAKE_CASE
  ```javascript
  const API_ENDPOINT = 'https://example.com/api';
  const MAX_ZOOM_LEVEL = 18;
  ```

### CSS/SCSS

- **Classes and IDs**: Use kebab-case
  ```css
  .map-container {}
  .vehicle-marker {}
  #booking-form {}
  ```

## File Organization

### PHP

- Place PHP backend files in the root directory
- Use the includes/ directory for shared helper functions
- Store debugging tools in the debug/ directory

```
/
├── api.php
├── auth.php
├── index.php
├── Database.php
├── includes/
│   └── helpers.php
└── debug/
    └── debug_api.php
```

### JavaScript

- Organize files by functionality
- Use modular patterns with separate service files

```
/js
├── auth-service.js
├── data-service.js
├── location-service.js
├── map-service.js
├── reservation.js
└── ui-service.js
```

### CSS

- Separate CSS files by functional modules
- Use consistent naming patterns

```
/css
├── index.css
├── login.css
├── map.css
└── booking.css
```

## PHP Standards

1. **Security**:
   - Always use prepared statements for database queries
   - Validate and sanitize all user inputs
   - Implement proper authentication and authorization checks
   - Use password hashing with bcrypt

2. **Database Operations**:
   - Use the Database.php class for all database operations
   - Implement transactions for operations that modify multiple tables
   - Use proper error handling for database operations

3. **API Design**:
   - Follow RESTful principles for API endpoints
   - Use consistent response formats
   - Implement proper status codes
   - Document all endpoints

## JavaScript Standards

1. **General Practices**:
   - Use ES6+ features where appropriate
   - Avoid global variables
   - Use strict equality (`===`) for comparisons
   - Handle promise rejections and errors properly

2. **DOM Manipulation**:
   - Minimize direct DOM manipulation
   - Use event delegation for dynamic elements
   - Cache DOM references when appropriate
   - Clean up event listeners when components are destroyed

3. **API Interactions**:
   - Use fetch API or axios for HTTP requests
   - Handle loading states and errors appropriately
   - Implement proper error feedback to users

## Map and Location Features

1. Use Leaflet.js for map visualization
2. Standardize on WGS84 coordinate system for GPS coordinates
3. Follow a consistent marker styling approach
4. Implement efficient marker updates to minimize redraws

## Documentation

1. **Code Documentation**:
   - Add JSDoc or PHPDoc comments for functions and classes
   - Document parameters, return types, and exceptions
   - Explain complex algorithms or business logic

2. **API Documentation**:
   - Maintain up-to-date API documentation
   - Document request/response formats and examples
   - Include authentication requirements

3. **README and Installation**:
   - Provide clear setup instructions
   - Document dependencies and requirements
   - Include troubleshooting guidance

## Version Control

1. **Commits**:
   - Write clear and descriptive commit messages
   - Keep commits focused on single changes or features
   - Reference issue numbers in commit messages when applicable

2. **Branching**:
   - Use feature branches for new development
   - Create pull/merge requests for code review
   - Delete branches after merging

## Performance and Optimization

1. **Frontend**:
   - Optimize assets (images, CSS, JavaScript)
   - Minimize HTTP requests
   - Implement lazy loading where appropriate
   - Consider mobile performance

2. **Backend**:
   - Optimize database queries
   - Implement caching where appropriate
   - Control API response size
   - Monitor and optimize server response times

## Testing

1. **Unit Testing**:
   - Write tests for critical business logic
   - Aim for good test coverage of backend services
   - Test edge cases and error handling

2. **Manual Testing**:
   - Test on various devices and browsers
   - Verify responsive design
   - Test with real-world scenarios

## Accessibility

1. Use semantic HTML
2. Ensure proper contrast for text
3. Provide alternative text for images
4. Ensure keyboard navigation works properly
5. Test with screen readers when possible

---

Following these guidelines will help maintain code quality and consistency across the project. This document may be updated as the project evolves. 