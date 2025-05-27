/**
 * Auth Service Module - Handles user authentication and session management
 */
const AuthService = {
    /**
     * Common request handler
     * @param {string} url - API endpoint
     * @param {string} method - HTTP method
     * @param {Object} data - Request data
     * @param {string} contentType - Content type
     * @returns {Promise<Object>} - Request result
     */
    async sendRequest(url, method = 'GET', data = null, contentType = 'application/x-www-form-urlencoded') {
        try {
            const options = {
                method: method,
                headers: { 
                    'Content-Type': contentType,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            // Handle different request body types
            if (data) {
                if (contentType === 'application/json') {
                    options.body = JSON.stringify(data);
                } else if (contentType === 'application/x-www-form-urlencoded') {
                    const params = new URLSearchParams();
                    Object.keys(data).forEach(key => params.append(key, data[key]));
                    options.body = params;
                }
            }
            
            const response = await fetch(url, options);
            
            // Try to parse as JSON, return text if failed
            let responseData;
            const text = await response.text();
            try {
                responseData = JSON.parse(text);
            } catch (e) {
                // Handle non-JSON response
                const isSuccess = text.includes("successful");
                responseData = {
                    success: isSuccess,
                    message: text
                };
            }
            
            return responseData;
        } catch (error) {
            console.error(`Request error (${url}):`, error);
            return {
                success: false,
                message: "System error: " + error.message
            };
        }
    },

    /**
     * Check if user is authenticated
     * @returns {boolean} - True if user is authenticated
     */
    isAuthenticated() {
        return window.USER_IS_LOGGED_IN === true;
    },
    
    /**
     * Get current user information
     * @returns {Object|null} - User information or null if not authenticated
     */
    getCurrentUser() {
        if (!this.isAuthenticated()) {
            return null;
        }
        return {
            id: window.USER_ID,
            username: window.USER_NAME,
            isLoggedIn: true
        };
    },
    
    /**
     * Redirect to login page if not authenticated
     * @param {string} redirectUrl - URL to redirect to after login
     * @param {string} message - Optional message to display
     * @returns {boolean} - True if authenticated, false if redirected
     */
    requireAuthentication(redirectUrl = '/web/frontend/views/index.php', message = 'Please login to continue') {
        if (!this.isAuthenticated()) {
            if (typeof ReservationService !== 'undefined') {
                ReservationService.showErrorNotification('Authentication Required', message);
            } else {
                alert(message);
            }
            
            setTimeout(() => {
                window.location.href = redirectUrl + '?show_login=1';
            }, 2000);
            return false;
        }
        return true;
    },
    
    /**
     * Login user
     * @param {string} username - Username
     * @param {string} password - Password
     * @returns {Promise<Object>} - Login result
     */
    async login(username, password) {
        return this.sendRequest('/web/backend/core/login_process.php', 'POST', { username, password });
    },
    
    /**
     * Register new user
     * @param {string} username - Username
     * @param {string} email - Email
     * @param {string} password - Password
     * @returns {Promise<Object>} - Registration result
     */
    async register(username, email, password) {
        return this.sendRequest('/web/backend/core/register_process.php', 'POST', { username, email, password });
    },
    
    /**
     * Logout user
     * @returns {Promise<boolean>} - True if logout was successful
     */
    async logout() {
        try {
            const result = await this.sendRequest('/web/backend/core/logout_process.php', 'POST');
            if (result.success) {
                window.location.href = '/web/frontend/views/index.php?logout=true';
                return true;
            } else {
                console.error("Logout error:", result.message);
                window.location.href = '/web/frontend/views/index.php?logout=true';
                return false;
            }
        } catch (error) {
            console.error("Logout error:", error);
            window.location.href = '/web/frontend/views/index.php?logout=true';
            return false;
        }
    },
    
    /**
     * Request password reset
     * @param {string} email - User email
     * @returns {Promise<Object>} - Request result
     */
    async requestPasswordReset(email) {
        return this.sendRequest('reset_password.php', 'POST', { email });
    },
    
    /**
     * Verify auth token (for email verification, password reset, etc.)
     * @param {string} token - Auth token
     * @param {string} action - Action type (verify_email, reset_password)
     * @returns {Promise<Object>} - Verification result
     */
    async verifyToken(token, action) {
        return this.sendRequest(`verify.php?token=${token}&action=${action}`, 'GET');
    },
    
    /**
     * Update user profile
     * @param {Object} userData - User data to update
     * @returns {Promise<Object>} - Update result
     */
    async updateProfile(userData) {
        if (!this.isAuthenticated()) {
            return {
                success: false,
                message: "You must be logged in to update your profile"
            };
        }
        
        return this.sendRequest('update_profile.php', 'POST', userData, 'application/json');
    },
    
    // UI related functions
    
    /**
     * Open modal dialog
     * @param {string} id - Modal ID
     */
    openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        
        modal.style.display = "flex";
        
        // Add fade-in animation
        modal.style.opacity = "0";
        setTimeout(() => {
            modal.style.transition = "opacity 0.3s ease";
            modal.style.opacity = "1";
        }, 10);
    },
    
    /**
     * Close modal dialog
     * @param {string} id - Modal ID
     */
    closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        
        // Add fade-out animation
        modal.style.transition = "opacity 0.3s ease";
        modal.style.opacity = "0";
        
        setTimeout(() => {
            modal.style.display = "none";
            
            // Reset modal state
            if (id === 'registerModal' || id === 'loginModal') {
                const formId = id === 'registerModal' ? 'registerForm' : 'loginForm';
                const resultId = id === 'registerModal' ? 'registerResult' : 'loginResult';
                
                const form = document.getElementById(formId);
                const resultDiv = document.getElementById(resultId);
                
                if (form) form.reset();
                if (resultDiv) {
                    resultDiv.innerHTML = "";
                    resultDiv.className = "form-result";
                }
            }
        }, 300);
    },
    
    /**
     * Submit login form
     * @param {Event} event - Form submission event
     * @param {string} redirectUrl - URL to redirect after login, defaults to refresh current page
     */
    async submitLoginForm(event, redirectUrl = null) {
        if (event) event.preventDefault();
        
        const usernameField = document.getElementById("login_username") || document.getElementById("username");
        const passwordField = document.getElementById("login_password") || document.getElementById("password");
        const resultDiv = document.getElementById("loginResult") || document.getElementById("loginMessage");
        
        if (!usernameField || !passwordField) {
            console.error("Username or password field not found");
            return;
        }
        
        const username = usernameField.value;
        const password = passwordField.value;
        
        if (resultDiv) {
            resultDiv.innerHTML = "<div class='processing-message'>Processing...</div>";
            resultDiv.className = "form-result";
        }
        
        try {
            const result = await this.login(username, password);
            
            if (result.success) {
                if (resultDiv) {
                    resultDiv.innerHTML = "<strong>Login successful!</strong> Redirecting...";
                    resultDiv.className = "form-result success";
                } else {
                    alert("Login Successful!");
                }
                
                // Redirect after successful login
                setTimeout(() => {
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                if (resultDiv) {
                    resultDiv.innerHTML = "<strong>Error:</strong> " + result.message;
                    resultDiv.className = "form-result error";
                } else {
                    alert("Login Failed: " + result.message);
                }
            }
        } catch (error) {
            console.error("Login error:", error);
            if (resultDiv) {
                resultDiv.innerHTML = "<strong>System Error:</strong> Please try again later.";
                resultDiv.className = "form-result error";
            } else {
                alert("An error occurred. Please try again.");
            }
        }
    },
    
    /**
     * Submit registration form
     * @param {Event} event - Form submission event
     * @param {string} redirectUrl - URL to redirect after registration, defaults to refresh current page
     */
    async submitRegisterForm(event, redirectUrl = null) {
        if (event) event.preventDefault();
        
        const usernameField = document.getElementById("reg_username") || document.getElementById("username");
        const emailField = document.getElementById("reg_email") || document.getElementById("email");
        const passwordField = document.getElementById("reg_password") || document.getElementById("password");
        const resultDiv = document.getElementById("registerResult") || document.getElementById("registerMessage");
        
        if (!usernameField || !emailField || !passwordField) {
            console.error("Registration form fields not found");
            return;
        }
        
        const username = usernameField.value;
        const email = emailField.value;
        const password = passwordField.value;
        
        if (resultDiv) {
            resultDiv.innerHTML = "<div class='processing-message'>Processing...</div>";
            resultDiv.className = "form-result";
        }
        
        try {
            const result = await this.register(username, email, password);
            
            if (result.success) {
                if (resultDiv) {
                    resultDiv.innerHTML = "<strong>Registration successful!</strong> Welcome...";
                    resultDiv.className = "form-result success";
                } else {
                    alert("Registration Successful!");
                }
                
                // Redirect after successful registration
                setTimeout(() => {
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                if (resultDiv) {
                    resultDiv.innerHTML = "<strong>Error:</strong> " + result.message;
                    resultDiv.className = "form-result error";
                } else {
                    alert("Registration Failed: " + result.message);
                }
            }
        } catch (error) {
            console.error("Registration error:", error);
            if (resultDiv) {
                resultDiv.innerHTML = "<strong>System Error:</strong> Please try again later.";
                resultDiv.className = "form-result error";
            } else {
                alert("An error occurred. Please try again.");
            }
        }
    },
    
    /**
     * Initialize notification messages auto-close
     * @param {number} timeout - Auto-close timeout (milliseconds)
     */
    initNotifications(timeout = 5000) {
        setTimeout(function() {
            var notifications = document.getElementsByClassName('notification');
            for(var i = 0; i < notifications.length; i++) {
                notifications[i].style.display = 'none';
            }
        }, timeout);
    },
    
    /**
     * Initialize authentication related functions
     */
    init() {
        // Auto-close notifications
        this.initNotifications();
        
        // Automatically open corresponding modal if there is an error
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('login_error')) {
            this.openModal('loginModal');
        }
        if (urlParams.has('register_error')) {
            this.openModal('registerModal');
        }
        
        // Bind login form submission event
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (event) => this.submitLoginForm(event));
        }
        
        // Bind registration form submission event
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', (event) => this.submitRegisterForm(event));
        }
    }
};

// Initialize after page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AuthService !== 'undefined' && typeof AuthService.init === 'function') {
        AuthService.init();
    }
});

// Export for Node.js environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthService;
}
