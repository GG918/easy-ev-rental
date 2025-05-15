/**
 * Data Service Module - Handles data retrieval and refresh
 */
const DataService = (function() {
    // Private variables - Unified definition
    let vehicleData = [];
    let nearbyVehicles = [];
    let refreshInterval = null;
    let refreshTimeout = null;
    let dataLoadedCallback = null;
    let apiEndpoints = ['api.php?action=getLatestLocations'];
    let retryCount = 0;
    let lastFetchTime = 0;
    
    // Public methods
    return {
        /**
         * Initialize data service
         * @param {Object} config Configuration object
         * @param {number} config.refreshInterval Data refresh interval in milliseconds
         * @param {Array} config.endpoints API endpoints
         * @param {Function} config.onDataLoaded Data loaded callback
         */
        init: function(config = {}) {
            console.log('Initializing data service...');
            
            if (config.refreshInterval) {
                this.setRefreshInterval(config.refreshInterval);
            }
            
            if (config.endpoints && Array.isArray(config.endpoints)) {
                apiEndpoints = config.endpoints;
            }
            
            if (config.onDataLoaded && typeof config.onDataLoaded === 'function') {
                dataLoadedCallback = config.onDataLoaded;
            }
            
            // Check if initial data is available
            if (typeof INITIAL_VEHICLE_DATA !== 'undefined' && Array.isArray(INITIAL_VEHICLE_DATA)) {
                console.log('Using preloaded vehicle data:', INITIAL_VEHICLE_DATA.length);
                this.setInitialData(INITIAL_VEHICLE_DATA);
            } else {
                console.log('No preloaded data, fetching data immediately');
                // Fetch data immediately on initialization
                this.refresh().then(data => {
                    console.log('Initial data load completed, data count:', data.length);
                }).catch(err => {
                    console.error('Initial data load failed:', err);
                });
            }
            
            // Handle page visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    console.log('Page hidden, pausing refresh');
                    clearInterval(refreshInterval);
                    clearTimeout(refreshTimeout);
                } else {
                    console.log('Page visible, resuming refresh');
                    this.refresh();
                    this.startAutoRefresh();
                }
            });
            
            console.log('Data service initialization completed');
        },
        
        /**
         * Get vehicle data
         * @returns {Array} Array of vehicle data
         */
        getVehicleData: function() {
            return vehicleData;
        },
        
        /**
         * Get nearby vehicles
         * @returns {Array} Array of nearby vehicles
         */
        getNearbyVehicles: function() {
            return nearbyVehicles;
        },
        
        /**
         * Set initial data
         * @param {Array} data Vehicle data array
         */
        setInitialData: function(data) {
            if (!Array.isArray(data)) {
                console.error('Initial data must be an array');
                return vehicleData;
            }
            
            try {
                // Filter available vehicles
                vehicleData = data.filter(v => {
                    // Check required fields
                    if (!v.latitude || !v.longitude) {
                        console.warn('Vehicle data missing coordinates:', v);
                        return false;
                    }
                    
                    // Check status field
                    if (v.status && v.status.toLowerCase() !== 'available') {
                        return false;
                    }
                    
                    return true;
                });
                
                console.log(`Successfully loaded ${vehicleData.length} available vehicles`);
                
                if (dataLoadedCallback) {
                    dataLoadedCallback(vehicleData);
                }
                
                return vehicleData;
            } catch (error) {
                console.error('Error setting initial data:', error);
                return [];
            }
        },
        
        /**
         * Refresh data
         * @returns {Promise} Promise containing vehicle data
         */
        refresh: async function() {
            const now = Date.now();
            // Prevent frequent refresh - at least 1 second interval
            if (now - lastFetchTime < 1000) {
                console.log('Refresh interval too short, skipping this refresh');
                return Promise.resolve(vehicleData);
            }
            
            lastFetchTime = now;
            
            try {
                console.log('Fetching vehicle data...');
                const endpoint = `${apiEndpoints[0]}&_t=${now}`;
                
                const response = await fetch(endpoint);
                if (!response.ok) {
                    console.error(`Server returned error: ${response.status}`);
                    throw new Error(`Server returned error: ${response.status}`);
                }
                
                // Use generic API response handling
                const data = await UtilService.api.handleResponse(
                    response, [], 'Failed to fetch vehicle data'
                );
                
                console.log('Vehicle data fetched successfully, data count:', data?.length || 0);
                
                // Reset retry count
                retryCount = 0;
                
                if (Array.isArray(data) && data.length > 0) {
                    // Filter valid vehicle data
                    vehicleData = data.filter(v => {
                        // Must have latitude and longitude
                        if (!v.latitude || !v.longitude || 
                            isNaN(parseFloat(v.latitude)) || 
                            isNaN(parseFloat(v.longitude))) {
                            return false;
                        }
                        
                        // Check status (if any)
                        if (v.status && v.status.toLowerCase() !== 'available') {
                            return false;
                        }
                        
                        return true;
                    });
                    
                    console.log(`Filtered ${vehicleData.length} available vehicles`);
                    
                    if (dataLoadedCallback) {
                        dataLoadedCallback(vehicleData);
                    }
                } else {
                    console.warn('Fetched 0 vehicles');
                }
                
                return vehicleData;
            } catch (error) {
                console.error('Data refresh failed:', error);
                
                // Increment retry count
                retryCount++;
                
                // Exponential backoff retry
                if (retryCount < 5) {
                    const retryDelay = Math.min(1000 * Math.pow(2, retryCount), 30000);
                    console.log(`Retrying in ${retryDelay}ms, attempt ${retryCount}`);
                    
                    refreshTimeout = setTimeout(() => this.refresh(), retryDelay);
                }
                
                return vehicleData;
            }
        },
        
        /**
         * Start auto-refreshing data
         * @param {number} interval Refresh interval in milliseconds
         */
        startAutoRefresh: function(interval) {
            const refreshTime = interval || 5000;
            
            // Use TimerManager to manage timers
            UtilService.timers.create('dataRefresh', () => this.refresh(), refreshTime);
            refreshInterval = UtilService.timers.timers['dataRefresh'];
        },
        
        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            UtilService.timers.clear('dataRefresh');
            refreshInterval = null;
            
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
                refreshTimeout = null;
            }
        },
        
        /**
         * Set refresh interval
         * @param {number} interval Refresh interval in milliseconds
         */
        setRefreshInterval: function(interval) {
            this.stopAutoRefresh();
            this.startAutoRefresh(interval);
        },
        
        /**
         * Find nearby vehicles
         * @param {Object} userLocation User location
         * @param {number} radius Search radius in meters
         * @param {Function} distanceCalculator Distance calculation function
         * @returns {Array} Array of nearby vehicles
         */
        findNearbyVehicles: function(userLocation, radius, distanceCalculator) {
            if (!userLocation || !userLocation.lat || !userLocation.lng) {
                return [];
            }
            
            nearbyVehicles = [];
            
            vehicleData.forEach(vehicle => {
                const distance = distanceCalculator(
                    [userLocation.lat, userLocation.lng],
                    [vehicle.latitude, vehicle.longitude]
                );
                
                if (distance <= radius) {
                    nearbyVehicles.push({...vehicle, distance});
                }
            });
            
            return nearbyVehicles;
        },
        
        /**
         * Get vehicle by ID
         * @param {string} id Vehicle ID
         * @returns {Object|null} Vehicle object or null
         */
        getVehicleById: function(id) {
            return vehicleData.find(v => v.id == id) || null;
        },
        
        /**
         * Create vehicle tooltip
         * @param {Object} vehicle Vehicle object
         * @param {number|null} distance Distance, optional
         * @returns {string} HTML tooltip content
         */
        createVehicleTooltip: function(vehicle, distance = null) {
            const batteryLevel = parseInt(vehicle.battery_level) || 0;
            const isReserved = window.state?.reservation?.active && window.state.reservation.scooterId == vehicle.id;
            
            try {
                // Use UIService method to generate buttons, ensure Reserve button is always shown
                const buttonsHtml = typeof UIService !== 'undefined' && typeof UIService.createVehicleActionButtons === 'function' ? 
                    UIService.createVehicleActionButtons(vehicle) : 
                    `<p>
                        <a href="#" class="navigate-btn" onclick="event.preventDefault(); startNavigation(${vehicle.latitude}, ${vehicle.longitude});">Navigate</a>
                        <button class="reserve-btn" data-scooter-id="${vehicle.id}">Reserve</button>
                    </p>`;
                
                // Build minimal HTML structure, ensure no unnecessary containers
                const content = `
                    <h4>Vehicle ${isReserved ? '<span style="color:#E91E63">(Reserved)</span>' : ''}</h4>
                    <p><strong>ID:</strong> ${vehicle.id}</p>
                    <p><strong>Battery:</strong> ${UtilService.format.batteryHTML(batteryLevel)}</p>
                    <p><strong>Last Update:</strong> ${UtilService.format.timeAgo(vehicle.timestamp)}</p>
                    ${distance !== null ? `<p><strong>Distance:</strong> ${UtilService.format.distance(distance)}</p>` : ''}
                    ${buttonsHtml}
                `;
                return content;
            } catch (error) {
                console.error('Error creating vehicle tooltip:', error);
                return `<h4>Vehicle ${vehicle.id}</h4><p>Error loading details</p>`;
            }
        },
        
        /**
         * Update nearby vehicles panel
         * @param {HTMLElement} container Container element
         */
        updateNearbyPanel: function(container) {
            if (!container) return;
            
            const tbody = container.querySelector('#nearbyScootersList');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            nearbyVehicles
                .sort((a, b) => a.distance - b.distance)
                .forEach(vehicle => {
                    const tr = document.createElement('tr');
                    tr.classList.add('nearby-scooter');
                    tr.innerHTML = `
                        <td>${UtilService.format.distance(vehicle.distance)}</td>
                        <td>${UtilService.format.batteryHTML(vehicle.battery_level)}</td>
                    `;
                    tr.onclick = () => {
                        window.MapService.setView([vehicle.latitude, vehicle.longitude], 18);
                    };
                    tbody.appendChild(tr);
                });
            
            const statusEl = container.querySelector('#nearbyStatus');
            if (statusEl) {
                const radius = document.getElementById('searchRadius')?.value || 1000;
                statusEl.textContent = `Found ${nearbyVehicles.length} scooters within view`;
            }
        }
    };
})();

// If in Node environment, export module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DataService;
}
