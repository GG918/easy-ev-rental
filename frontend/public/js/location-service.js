/**
 * Location Service Module - Handles user location and permissions
 */
const LocationService = (function() {
    // Private variables
    let userLocation = { lat: null, lng: null };
    let locationCallback = null;
    const LOCATION_PERMISSION_KEY = 'location_permission_status';
    
    // Check permission status
    function checkPermissionStatus() {
        return localStorage.getItem(LOCATION_PERMISSION_KEY);
    }
    
    // Save permission status
    function savePermissionStatus(status) {
        localStorage.setItem(LOCATION_PERMISSION_KEY, status);
    }
    
    // Display permission prompt
    function showPermissionPrompt(callback) {
        const prompt = document.getElementById('locationPermissionPrompt');
        if (!prompt) return callback('denied');
        
        // Show prompt
        prompt.style.display = 'flex';
        
        // Allow button handler
        document.getElementById('allowLocationBtn').onclick = function() {
            prompt.style.display = 'none';
            savePermissionStatus('granted');
            callback('granted');
        };
        
        // Deny button handler
        document.getElementById('denyLocationBtn').onclick = function() {
            prompt.style.display = 'none';
            savePermissionStatus('denied');
            callback('denied');
        };
    }
    
    // Public methods
    return {
        /**
         * Initialize location service
         * @param {Object} config Configuration object
         */
        init: function(config = {}) {
            console.log('Location service initializing');
            
            // Store location update callback
            if (config.onLocationUpdate && typeof config.onLocationUpdate === 'function') {
                locationCallback = config.onLocationUpdate;
            }
            
            // Check previous permission status
            const permissionStatus = checkPermissionStatus();
            
            // Handle permission status
            if (permissionStatus === 'granted') {
                console.log('Previous permission granted, getting position');
                this.getCurrentPosition();
            } else if (permissionStatus === 'denied') {
                console.log('Previous permission denied');
            } else {
                console.log('First visit, showing permission prompt');
                showPermissionPrompt((status) => {
                    if (status === 'granted') {
                        this.getCurrentPosition();
                    }
                });
            }
        },
        
        /**
         * Get current position
         */
        getCurrentPosition: function() {
            if (!navigator.geolocation) {
                console.log('Browser does not support Geolocation API');
                return;
            }
            
            console.log('Requesting location permission...');
            navigator.geolocation.getCurrentPosition(
                // Success callback
                (position) => {
                    const { latitude, longitude, accuracy } = position.coords;
                    userLocation = { 
                        lat: latitude, 
                        lng: longitude,
                        accuracy: accuracy, // Add accuracy information
                        timestamp: new Date().toISOString() // Add timestamp
                    };
                    console.log('User location acquired:', userLocation);
                    
                    // Call location update callback
                    if (locationCallback) {
                        locationCallback(userLocation);
                    }
                },
                // Error callback
                (error) => {
                    console.error('Failed to get location:', error.message);
                    if (error.code === 1) { // User denied location permission
                        savePermissionStatus('denied');
                    }
                },
                // Options
                { 
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        },
        
        /**
         * Get user location
         * @returns {Object} User location
         */
        getUserLocation: function() {
            return userLocation;
        },
        
        /**
         * Reset permission status
         * Used for testing or allowing user to reselect
         */
        resetPermissionStatus: function() {
            localStorage.removeItem(LOCATION_PERMISSION_KEY);
            console.log('Location permission status has been reset');
        }
    };
})();

// Navigation helper function
function startNavigation(lat, lng) {
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`, '_blank');
}

// Export for Node.js environment
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LocationService;
}
