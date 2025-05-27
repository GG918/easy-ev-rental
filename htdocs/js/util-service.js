/**
 * Utility Service - Provides common functionality
 */
const UtilService = (function() {
    // Storage management
    const storage = {
        keys: {
            RESERVATION: 'scooterReservation',
            USER_PREFERENCES: 'userPreferences', 
            MAP_STATE: 'mapState'
        },
        
        // Save data to local storage
        save: function(key, data) {
            try {
                localStorage.setItem(key, JSON.stringify(data));
                return true;
            } catch (e) {
                console.error('Storage save error:', e);
                return false;
            }
        },
        
        // Get data from local storage 
        get: function(key, defaultValue = null) {
            try {
                const data = localStorage.getItem(key);
                return data ? JSON.parse(data) : defaultValue;
            } catch (e) {
                console.error('Storage get error:', e);
                return defaultValue;
            }
        },
        
        // Delete data from local storage
        remove: function(key) {
            try {
                localStorage.removeItem(key);
                return true;
            } catch (e) {
                console.error('Storage remove error:', e);
                return false;
            }
        },
        
        // Clear all local storage
        clear: function() {
            try {
                localStorage.clear();
                return true;
            } catch (e) {
                console.error('Storage clear error:', e);
                return false;
            }
        }
    };

    // Timer management 
    const timerManager = {
        timers: {},
        
        // Create timer
        create: function(id, callback, interval, immediate = false) {
            this.clear(id);
            
            if (immediate && typeof callback === 'function') {
                callback();
            }
            
            this.timers[id] = setInterval(callback, interval);
            return this.timers[id];
        },
        
        // Clear timer
        clear: function(id) {
            if (this.timers[id]) {
                clearInterval(this.timers[id]);
                delete this.timers[id];
                return true;
            }
            return false;
        },
        
        // Clear all timers
        clearAll: function() {
            for (const id in this.timers) {
                clearInterval(this.timers[id]);
                delete this.timers[id];
            }
        }
    };

    // Formatting utilities
    const formatter = {
        // Format distance
        distance: function(meters) {
            return meters < 1000 ? 
                `${Math.round(meters)}m` : 
                `${(meters / 1000).toFixed(1)}km`;
        },
        
        // Format relative time
        timeAgo: function(timestamp) {
            if (!timestamp) return 'Unknown';
            const diffSec = Math.floor((Date.now() - new Date(timestamp))/1000);
            if (diffSec < 60) return `${diffSec}s ago`;
            if (diffSec < 3600) return `${Math.floor(diffSec/60)}m ago`;
            if (diffSec < 86400) return `${Math.floor(diffSec/3600)}h ago`;
            return `${Math.floor(diffSec/86400)}d ago`;
        },
        
        // Format booking status
        bookingStatus: function(status) {
            const statusMap = {
                'reserved': 'Reserved',
                'in_progress': 'In Progress',
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };
            return statusMap[status] || status;
        },
        
        // Get status CSS class
        bookingStatusClass: function(status) {
            return `status-${status}`;
        },
        
        // Format battery level
        batteryHTML: function(level) {
            level = Math.max(0, Math.min(100, parseInt(level) || 0));
            const color = level < 20 ? '#f44336' : level < 50 ? '#ff9800' : '#4CAF50';
            return `<div class="battery-indicator">
                <div class="battery-level" style="width:${level}%;background:${color}"></div>
                <div class="battery-tip"></div>
                <div class="battery-text">${level}%</div>
            </div>`;
        },
        
        // Format reservation time
        reservationTime: function(startTime, endTime) {
            try {
                const start = new Date(startTime);
                const end = new Date(endTime);
                
                const dateOptions = { year: 'numeric', month: 'short', day: 'numeric' };
                const timeOptions = { hour: '2-digit', minute: '2-digit' };
                
                const dateStr = start.toLocaleDateString('en-US', dateOptions);
                const startTimeStr = start.toLocaleTimeString('en-US', timeOptions);
                const endTimeStr = end.toLocaleTimeString('en-US', timeOptions);
                
                return `${dateStr} ${startTimeStr} - ${endTimeStr}`;
            } catch (e) {
                console.error('Time formatting error:', e);
                return 'Time information unavailable';
            }
        }
    };

    // API utilities
    const apiUtils = {
        // Handle API response
        handleResponse: async function(response, defaultValue = null, errorMessage = 'API request failed') {
            if (!response.ok) {
                console.warn(`API error (${response.status}): ${errorMessage}`);
                return defaultValue;
            }
            
            try {
                const responseText = await response.text();
                if (!responseText || responseText.trim() === '') {
                    console.warn('Empty API response');
                    return defaultValue;
                }
                
                return JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parsing error:', parseError);
                return defaultValue;
            }
        }
    };

    // Public interface
    return {
        storage: storage,
        timers: timerManager,
        format: formatter,
        api: apiUtils,
        
        // Navigate to location
        startNavigation: function(lat, lng) {
            // Determine device type and choose appropriate navigation app
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                // iOS devices, use Apple Maps
                window.open(`maps://maps.apple.com/maps?daddr=${lat},${lng}&dirflg=d`);
            } else {
                // Other devices, use Google Maps
                window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`);
            }
        }
    };
})();

// Export for Node.js environment
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UtilService;
}
