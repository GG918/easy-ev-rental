/**
 * Location Service - Handles map and vehicle location related functions
 */
const LocationsService = {
    map: null,
    markers: [],
    vehicleData: [],
    selectedVehicle: null,
    userLocation: null,
    
    /**
     * Initialize map
     */
    initMap: function() {
        // Initial default location (Shanghai city center)
        const defaultLocation = [31.230416, 121.473701];
        
        // Create map
        this.map = L.map('map').setView(defaultLocation, 14);
        
        // Add map tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);
        
        // Attempt to get user location
        this.getUserLocation();
        
        // Load vehicle data
        this.loadVehicleData();
        
        // Set map event listeners
        this.setupMapEventListeners();
    },
    
    /**
     * Get user location
     */
    getUserLocation: function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                // Success callback
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    this.userLocation = [lat, lng];
                    
                    // Move map to user location
                    this.map.setView(this.userLocation, 15);
                    
                    // Add user location marker
                    L.marker(this.userLocation, {
                        icon: L.divIcon({
                            className: 'user-marker',
                            html: '<div class="user-marker-icon"></div>',
                            iconSize: [20, 20]
                        })
                    }).addTo(this.map)
                      .bindPopup('<b>Your Location</b>');
                },
                // Error callback
                (error) => {
                    console.error('Failed to get location:', error.message);
                },
                // Options
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
        } else {
            console.error('Browser does not support geolocation');
        }
    },
    
    /**
     * Load vehicle data
     */
    loadVehicleData: function() {
        // Send request to get vehicle data
        fetch('/api/locations')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data) {
                    this.vehicleData = data.data;
                    this.displayVehicles(this.vehicleData);
                }
            })
            .catch(error => {
                console.error('Failed to load vehicle data:', error);
            });
    },
    
    /**
     * Display vehicles on the map
     * @param {Array} vehicles - Vehicle data
     */
    displayVehicles: function(vehicles) {
        // Clear existing markers
        this.clearMarkers();
        
        // Add new markers
        vehicles.forEach(vehicle => {
            if (vehicle.latitude && vehicle.longitude) {
                const marker = this.createVehicleMarker(vehicle);
                this.markers.push(marker);
            }
        });
    },
    
    /**
     * Create vehicle marker
     * @param {Object} vehicle - Vehicle data
     * @returns {Object} - Leaflet marker object
     */
    createVehicleMarker: function(vehicle) {
        const markerIcon = L.divIcon({
            className: `vehicle-marker ${vehicle.status || 'available'}`,
            html: `<div class="vehicle-marker-icon" data-battery="${vehicle.battery_level || 0}%"></div>`,
            iconSize: [30, 30]
        });
        
        const marker = L.marker([vehicle.latitude, vehicle.longitude], { icon: markerIcon })
            .addTo(this.map)
            .bindPopup(this.createVehiclePopup(vehicle))
            .on('click', () => {
                this.onVehicleSelect(vehicle, marker);
            });
            
        return marker;
    },
    
    /**
     * Create vehicle popup content
     * @param {Object} vehicle - Vehicle data
     * @returns {String} - HTML content
     */
    createVehiclePopup: function(vehicle) {
        const batteryClass = this.getBatteryClass(vehicle.battery_level);
        const statusText = this.getStatusText(vehicle.status);
        
        return `
            <div class="vehicle-popup">
                <h3>Vehicle #${vehicle.id}</h3>
                <div class="vehicle-details">
                    <p><strong>Status:</strong> <span class="status-${vehicle.status}">${statusText}</span></p>
                    <p><strong>Battery:</strong> <span class="battery ${batteryClass}">${vehicle.battery_level || 0}%</span></p>
                </div>
                <button class="book-btn" onclick="LocationsService.openBookingPanel(${vehicle.id})">Book Vehicle</button>
            </div>
        `;
    },
    
    /**
     * Get CSS class based on battery level
     * @param {Number} level - Battery level
     * @returns {String} - CSS class name
     */
    getBatteryClass: function(level) {
        if (level >= 70) return 'high';
        if (level >= 30) return 'medium';
        return 'low';
    },
    
    /**
     * Get status text
     * @param {String} status - Status code
     * @returns {String} - Status text
     */
    getStatusText: function(status) {
        const statusMap = {
            'available': 'Available',
            'reserved': 'Reserved',
            'in_use': 'In Use',
            'maintenance': 'Maintenance',
            'offline': 'Offline'
        };
        
        return statusMap[status] || status;
    },
    
    /**
     * Clear all markers
     */
    clearMarkers: function() {
        this.markers.forEach(marker => {
            this.map.removeLayer(marker);
        });
        this.markers = [];
    },
    
    /**
     * Vehicle selection handler
     * @param {Object} vehicle - Vehicle data
     * @param {Object} marker - Marker object
     */
    onVehicleSelect: function(vehicle, marker) {
        this.selectedVehicle = vehicle;
    },
    
    /**
     * Open booking panel
     * @param {Number} vehicleId - Vehicle ID
     */
    openBookingPanel: function(vehicleId) {
        // Find vehicle data
        const vehicle = this.vehicleData.find(v => v.id == vehicleId);
        if (!vehicle) return;
        
        // Update booking panel data
        document.getElementById('vehicle-id').textContent = vehicle.id;
        document.getElementById('vehicle-type-info').textContent = vehicle.type || 'Electric Scooter';
        document.getElementById('vehicle-status-info').textContent = this.getStatusText(vehicle.status);
        document.getElementById('vehicle-battery').textContent = vehicle.battery_level || 0;
        
        // Set vehicle ID for booking form
        document.getElementById('booking-vehicle-id').value = vehicle.id;
        
        // Set booking date default to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('booking-date').value = today;
        document.getElementById('booking-date').min = today;
        
        // Generate time options
        this.generateTimeOptions();
        
        // Show booking panel
        document.getElementById('booking-panel').classList.remove('hidden');
    },
    
    /**
     * Generate time options
     */
    generateTimeOptions: function() {
        const timeSelect = document.getElementById('booking-time');
        timeSelect.innerHTML = '';
        
        // Get current time and round up to the nearest half hour
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        let startHour = currentHour;
        let startMinute = currentMinute >= 30 ? 30 : 0;
        
        if (currentMinute >= 30) {
            startHour += 1;
            startMinute = 0;
        }
        
        // Generate time options, from current time to end of day
        for (let hour = startHour; hour < 24; hour++) {
            for (let minute = (hour === startHour ? startMinute : 0); minute < 60; minute += 30) {
                const timeValue = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                const option = document.createElement('option');
                option.value = timeValue;
                option.textContent = timeValue;
                timeSelect.appendChild(option);
            }
        }
    },
    
    /**
     * Set up map event listeners
     */
    setupMapEventListeners: function() {
        // Booking form submission
        const bookingForm = document.getElementById('booking-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submitBooking();
            });
        }
        
        // Close booking panel
        const closeButton = document.getElementById('close-booking-panel');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                document.getElementById('booking-panel').classList.add('hidden');
            });
        }
        
        // Apply filters
        const filterButton = document.getElementById('apply-filters');
        if (filterButton) {
            filterButton.addEventListener('click', () => {
                this.applyFilters();
            });
        }
    },
    
    /**
     * Submit booking
     */
    submitBooking: function() {
        // Check if user is logged in
        if (typeof AuthService !== 'undefined' && !AuthService.isAuthenticated()) {
            // Show login prompt
            alert('Please log in before booking a vehicle');
            // Redirect to login page
            window.location.href = '/view/index?show_login=1&return_url=/view/locations';
            return;
        }
        
        // Get form data
        const vehicleId = document.getElementById('booking-vehicle-id').value;
        const bookingDate = document.getElementById('booking-date').value;
        const bookingTime = document.getElementById('booking-time').value;
        const bookingDuration = document.getElementById('booking-duration').value;
        
        // Calculate start and end times
        const startTime = `${bookingDate}T${bookingTime}:00`;
        const endDateTime = new Date(startTime);
        endDateTime.setHours(endDateTime.getHours() + parseInt(bookingDuration));
        const endTime = endDateTime.toISOString().slice(0, 16).replace('T', ' ');
        
        // Create request data
        const bookingData = {
            vehicle_id: vehicleId,
            start_time: startTime.replace('T', ' '),
            end_time: endTime
        };
        
        // Send booking request
        fetch('/api/reservations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bookingData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Booking successful');
                // Hide booking panel
                document.getElementById('booking-panel').classList.add('hidden');
                // Reload vehicle data
                this.loadVehicleData();
                // Redirect to reservations page
                window.location.href = '/view/my_reservations';
            } else {
                alert('Booking failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Booking request failed:', error);
            alert('Booking failed, please try again later');
        });
    },
    
    /**
     * Apply filters
     */
    applyFilters: function() {
        const vehicleType = document.getElementById('vehicle-type').value;
        const vehicleStatus = document.getElementById('vehicle-status').value;
        
        // Filter vehicle data
        const filteredVehicles = this.vehicleData.filter(vehicle => {
            // Type filter
            if (vehicleType !== 'all' && vehicle.type !== vehicleType) {
                return false;
            }
            
            // Status filter
            if (vehicleStatus !== 'all' && vehicle.status !== vehicleStatus) {
                return false;
            }
            
            return true;
        });
        
        // Display filtered vehicles
        this.displayVehicles(filteredVehicles);
    }
};

// Initialize map after page load
document.addEventListener('DOMContentLoaded', function() {
    LocationsService.initMap();
}); 