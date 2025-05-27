/**
 * Application Initialization Module
 */
document.addEventListener('DOMContentLoaded', () => {
    // Avoid duplicate initialization
    if (window.appInitialized) {
        console.log('Application already initialized, skipping');
        return;
    }
    
    try {
        // Global configuration
        window.CONFIG = {
            // General system settings
            REFRESH_INTERVAL: 30000,                    // Data refresh interval
            MAX_RETRY_INTERVAL: 30000,                  // Maximum retry interval
            DEFAULT_SEARCH_RADIUS: 1000,                // Default search radius (meters)
            API_ENDPOINTS: ['api.php?action=getLatestLocations'],
            RESERVATION_TIME: 15 * 60 * 1000,           // Reservation time (15 minutes)
            
            // Map related configuration
            MAP_DEFAULT_CENTER: [53.3814, -1.4746],     // Sheffield city center coordinates
            MAP_DEFAULT_ZOOM: 15,                       // Default map zoom level
        };
        
        // Global state
        window.state = {
            // Map state
            map: null,
            showingNearbyOnly: false,
            userHasMovedMap: false,
            
            // Other states
            nearbyScooters: [],
            currentSearchRadius: 1000,
            reservation: {
                active: false,
                scooterId: null,
                expiryTime: null,
                timerInterval: null,
                scooterData: null,
                bookingId: null,
                status: 'reserved'
            },
            userInfo: {
                isLoggedIn: typeof USER_IS_LOGGED_IN !== 'undefined' ? USER_IS_LOGGED_IN : false,
                userId: typeof USER_ID !== 'undefined' ? USER_ID : null,
                username: typeof USER_NAME !== 'undefined' ? USER_NAME : ''
            }
        };
        
        // Execute initialization sequence
        initializeApp()
            .then(() => {
                // Initialize navigation functionality
                window.startNavigation = UtilService.startNavigation;
                
                // Initialize trip buttons
                initTripButtons();
                
                // Load data immediately if available
                if (typeof INITIAL_VEHICLE_DATA !== 'undefined' && Array.isArray(INITIAL_VEHICLE_DATA)) {
                    console.log('Loading initial vehicle data:', INITIAL_VEHICLE_DATA.length + ' vehicles');
                    DataService.setInitialData(INITIAL_VEHICLE_DATA);
                    
                    // Update map markers
                    MapService.updateVehicleMarkers(
                        DataService.getVehicleData(),
                        true,
                        DataService.createVehicleTooltip
                    );
                } else {
                    console.warn('No initial vehicle data available');
                }
                
                // Set data auto-refresh
                DataService.startAutoRefresh(window.CONFIG.REFRESH_INTERVAL);
                
                // Mark initialization complete
                window.appInitialized = true;
                console.log('Application successfully initialized');
                
                // Globally expose some commonly used methods
                window.openBookingModal = UIService.openBookingModal;
                window.startNavigation = UtilService.startNavigation;
                
                // Ensure ReservationService is also initialized
                if (typeof ReservationService !== 'undefined') {
                    ReservationService.checkExistingReservation();
                }
                
                // Bind booking modal close button event - Improved handling
                const closeBookingBtn = document.querySelector('.booking-close');
                if (closeBookingBtn) {
                    closeBookingBtn.addEventListener('click', function() {
                        const modal = document.getElementById('bookingModal');
                        if (modal) {
                            modal.style.display = 'none';
                        }
                    });
                }
                
                // Close modal on outside click
                window.addEventListener('click', function(event) {
                    const modal = document.getElementById('bookingModal');
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            })
            .catch(err => {
                console.error('Initialization failed:', err);
            });
    } catch (err) {
        console.error('Fatal error during initialization:', err);
    }
});

// Initialize trip button events
function initTripButtons() {
    // Bind start trip button
    const startTripButton = document.getElementById('startVehicle');
    if (startTripButton) {
        startTripButton.addEventListener('click', handleStartTrip);
    }
    
    // Bind end trip button
    const endTripButton = document.getElementById('completeTrip');
    if (endTripButton) {
        endTripButton.addEventListener('click', handleEndTrip);
    }
    
    // Check current trip status to decide which button to show
    if (window.state && window.state.reservation && window.state.reservation.active) {
        const isInProgress = window.state.reservation.status === 'in_progress';
        if (startTripButton) startTripButton.style.display = isInProgress ? 'none' : 'block';
        if (endTripButton) endTripButton.style.display = isInProgress ? 'block' : 'none';
    }
}

// Handle start trip
function handleStartTrip() {
    if (!window.state || !window.state.reservation || !window.state.reservation.active) {
        console.error('No active reservation found');
        return;
    }
    
    const { scooterId, bookingId } = window.state.reservation;
    
    if (typeof ReservationService !== 'undefined' && typeof ReservationService.startTrip === 'function') {
        ReservationService.startTrip(bookingId, scooterId);
    }
}

// Handle end trip
function handleEndTrip() {
    if (!window.state || !window.state.reservation || !window.state.reservation.active) {
        console.error('No active reservation found');
        return;
    }
    
    const { scooterId, bookingId } = window.state.reservation;
    
    if (typeof ReservationService !== 'undefined' && typeof ReservationService.endTrip === 'function') {
        ReservationService.endTrip(bookingId, scooterId);
    }
}

/**
 * Execute initialization tasks in sequence
 * @returns {Promise} Promise of initialization completion
 */
async function initializeApp() {
    try {
        console.log('Starting application initialization...');
        
        // Initialize map first - Use zoom level and center point from configuration
        console.log('Initializing map...');
        const defaultCenter = window.CONFIG?.MAP_DEFAULT_CENTER || [53.3814, -1.4746];
        const defaultZoom = window.CONFIG?.MAP_DEFAULT_ZOOM || 15;
        
        console.log(`Initializing map, center: [${defaultCenter}], zoom level: ${defaultZoom}`);
        window.state.map = MapService.initMap('map', defaultCenter, defaultZoom);
        
        if (!window.state.map) {
            console.error('Map initialization failed');
            throw new Error('Map initialization failed');
        }
        
        // Initialize location service and set location update callback
        console.log('Initializing location service...');
        LocationService.init({
            onLocationUpdate: (location) => {
                console.log('Location updated:', location);
                
                // Update global state
                if (window.state) {
                    window.state.userLocation = location;
                }
                
                // Show user location on the map
                if (MapService && typeof MapService.addUserLocationMarker === 'function') {
                    MapService.addUserLocationMarker(location);
                }
            }
        });
        
        // Initialize UI service
        console.log('Initializing UI service...');
        UIService.init();
        
        // Initialize data service
        console.log('Initializing data service...');
        DataService.init({
            refreshInterval: window.CONFIG.REFRESH_INTERVAL,
            onDataLoaded: (data) => {
                console.log('Data loaded, updating map markers...');
                if (typeof MapService !== 'undefined' && MapService.updateVehicleMarkers) {
                    MapService.updateVehicleMarkers(data, false, DataService.createVehicleTooltip);
                }
            }
        });
        
        // If needed, initialize reservation check
        if (typeof ReservationService !== 'undefined' && ReservationService.checkExistingReservation) {
            console.log('Checking existing reservations...');
            await ReservationService.checkExistingReservation();
        }
        
        console.log('Application initialization successfully completed');
        return Promise.resolve();
    } catch (error) {
        console.error('Application initialization failed:', error);
        return Promise.reject(error);
    }
}

// ReservationService's startTrip and endTrip methods
if (typeof ReservationService !== 'undefined') {
    /**
     * Start trip - Change status from reserved to in progress
     * @param {string} bookingId Booking ID
     * @param {string} scooterId Scooter ID
     */
    ReservationService.startTrip = async function(bookingId, scooterId) {
        try {
            const loadingIndicator = this.showLoadingIndicator('Starting trip...');
            
            const response = await fetch('api.php?action=startTrip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId, scooter_id: scooterId })
            });
            
            this.hideLoadingIndicator(loadingIndicator);
            
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to start trip');
            }
            
            // Update reservation status
            if (window.state && window.state.reservation) {
                // Stop countdown timer
                if (window.state.reservation.timerInterval) {
                    clearInterval(window.state.reservation.timerInterval);
                    window.state.reservation.timerInterval = null;
                }
                
                window.state.reservation.status = 'in_progress';
                this.saveReservationToLocalStorage(window.state.reservation);
            }
            
            // Update UI
            const startBtn = document.getElementById('startVehicle');
            if (startBtn) startBtn.style.display = 'none';
            
            const completeBtn = document.getElementById('completeTrip');
            if (completeBtn) completeBtn.style.display = 'block';
            
            // Update entire reservation status panel
            this.updateReservationUI();
            
            this.showSuccessNotification('Trip Started', 'Your trip has started. Enjoy your ride!');
            
            return true;
        } catch (error) {
            console.error('Start trip failed:', error);
            this.showErrorNotification('Failed to Start Trip', error.message);
            return false;
        }
    };
    
    /**
     * End trip - Change status from in progress to completed
     * @param {string} bookingId Booking ID
     * @param {string} scooterId Scooter ID
     */
    ReservationService.endTrip = async function(bookingId, scooterId) {
        try {
            const loadingIndicator = this.showLoadingIndicator('Completing trip...');
            
            const response = await fetch('api.php?action=endTrip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId, scooter_id: scooterId })
            });
            
            this.hideLoadingIndicator(loadingIndicator);
            
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to complete trip');
            }
            
            // Reset reservation status
            this.resetReservation();
            
            // Show success message
            this.showSuccessNotification('Trip Completed', 'Your trip has been successfully completed. Thank you for using our service!');
            
            // Update map
            if (window.MapService && typeof window.MapService.refreshMapData === 'function') {
                setTimeout(() => window.MapService.refreshMapData(), 1000);
            }
            
            return true;
        } catch (error) {
            console.error('End trip failed:', error);
            this.showErrorNotification('Failed to Complete Trip', error.message);
            return false;
        }
    };
}
