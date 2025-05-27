/**
 * Map Service Module - Handles all map related operations
 */
const MapService = (function() {
    // Private variables
    let map = null;
    let markers = {};
    let nearbyMarkers = {};
    let userLocationMarker = null;
    let initialViewSet = false;
    let userHasMovedMap = false;
    let currentMapBounds = null;
    
    // Return public methods
    return {
        /**
         * Initialize map
         * @param {HTMLElement} element Map container element
         * @param {Object} initialCenter Initial center coordinates - default is Sheffield city center
         * @param {number} initialZoom Initial zoom level - default is 13
         * @returns {Object} Leaflet map instance
         */
        initMap: function(element, initialCenter = window.CONFIG?.MAP_DEFAULT_CENTER || [53.3814, -1.4746], 
                          initialZoom = window.CONFIG?.MAP_DEFAULT_ZOOM || 13) {
            if (typeof L === 'undefined') {
                console.error('Leaflet library not loaded, cannot initialize map');
                return null;
            }
            
            // Check if element exists
            const mapElement = typeof element === 'string' ? document.getElementById(element) : element;
            if (!mapElement) {
                console.error('Map container element does not exist:', element);
                return null;
            }
            
            try {
                // Initial view set to show Sheffield city center, ensure zoom level is 13
                map = L.map(mapElement, {
                    // Set options to improve performance and zoom experience
                    preferCanvas: true,
                    worldCopyJump: true,
                    zoomControl: true,  // Ensure zoom control is enabled
                    minZoom: 3,        // Minimum zoom level
                    maxZoom: 19,       // Maximum zoom level
                    zoomSnap: 0.5,     // Allow 0.5 level zoom increments
                    zoomDelta: 0.5,    // Change zoom by 0.5 levels each time
                    wheelDebounceTime: 40  // Reduce delay for wheel zoom
                }).setView(initialCenter, initialZoom);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Add map move event listener
                map.on('movestart', () => {
                    if (!window.state.showingNearbyOnly) {
                        userHasMovedMap = true;
                        if (window.state) window.state.userHasMovedMap = true;
                    }
                });
                
                // Add map viewport change listener - listen for move and zoom end events
                map.on('moveend zoomend', () => {
                    this.handleViewportChange();
                });
                
                console.log('Map initialized, showing Sheffield city center view, zoom level:', initialZoom);
                
                // Initial data loading
                if (typeof DataService !== 'undefined' && typeof DataService.refresh === 'function') {
                    setTimeout(() => {
                        console.log('Loading map data on initialization...');
                        DataService.refresh().then(data => {
                            if (data && data.length > 0) {
                                console.log(`Loaded ${data.length} vehicle data`);
                                this.updateVehicleMarkers(data, true, DataService.createVehicleTooltip);
                            } else {
                                console.warn('Initial data load returned empty result');
                            }
                        });
                    }, 1000);
                }
                
                // Record initial zoom level for debugging
                console.log('Initial map zoom level set to:', map.getZoom());
                
                return map;
            } catch (error) {
                console.error('Map initialization failed:', error);
                return null;
            }
        },
        
        /**
         * Handle viewport changes - load vehicles based on current viewport
         */
        handleViewportChange: function() {
            if (!map) return;
            
            // Get current map visible bounds
            currentMapBounds = map.getBounds();
            
            // Update vehicles within the viewport
            if (typeof DataService !== 'undefined') {
                const vehicles = DataService.getVehicleData();
                if (vehicles && vehicles.length > 0) {
                    const visibleVehicles = this.filterVisibleVehicles(vehicles);
                    console.log(`There are ${visibleVehicles.length} vehicles in the viewport, out of ${vehicles.length} total`);
                    
                    // Update markers without forcing view update (to avoid loops)
                    this.updateVehicleMarkers(visibleVehicles, false, DataService.createVehicleTooltip);
                }
            }
        },
        
        /**
         * Filter vehicles within the viewport
         * @param {Array} vehicles All vehicle data
         * @returns {Array} Vehicles within the viewport
         */
        filterVisibleVehicles: function(vehicles) {
            if (!currentMapBounds) return vehicles;
            
            return vehicles.filter(vehicle => {
                return currentMapBounds.contains([vehicle.latitude, vehicle.longitude]);
            });
        },
        
        /**
         * Clear all markers
         */
        clearAllMarkers: function() {
            Object.values(markers).forEach(marker => map.removeLayer(marker));
            Object.values(nearbyMarkers).forEach(marker => map.removeLayer(marker));
            
            // Also clear user location marker
            if (userLocationMarker) {
                if (typeof userLocationMarker.remove === 'function') {
                    userLocationMarker.remove();
                } else {
                    map.removeLayer(userLocationMarker);
                }
                userLocationMarker = null;
            }
            
            markers = {};
            nearbyMarkers = {};
        },
        
        /**
         * Clear nearby markers
         */
        clearNearbyMarkers: function() {
            Object.values(nearbyMarkers).forEach(marker => map.removeLayer(marker));
            nearbyMarkers = {};
        },
        
        /**
         * Update markers on the map
         * @param {Array} vehicles Vehicle data
         * @param {boolean} forceUpdateView Whether to force update the view
         * @param {Function} createTooltipFn Function to create marker tooltip
         */
        updateVehicleMarkers: function(vehicles, forceUpdateView = false, createTooltipFn) {
            if (!map) {
                console.error('Map not initialized, cannot update markers');
                return {};
            }
            
            if (!Array.isArray(vehicles) || vehicles.length === 0) {
                console.warn('No vehicle data to display');
                return markers;
            }
            
            try {
                // Clear existing markers
                Object.values(markers).forEach(marker => map.removeLayer(marker));
                markers = {};
                
                const bounds = L.latLngBounds();
                let validVehicleCount = 0;
                
                vehicles.forEach(vehicle => {
                    // Validate coordinates
                    if (!vehicle.latitude || !vehicle.longitude || 
                        isNaN(parseFloat(vehicle.latitude)) || isNaN(parseFloat(vehicle.longitude))) {
                        console.warn('Invalid vehicle data coordinates:', vehicle);
                        return;
                    }
                    
                    // Create marker and set popup content
                    const marker = L.marker([vehicle.latitude, vehicle.longitude]);
                    
                    // Directly create popup content to avoid nesting
                    const popupContent = createTooltipFn(vehicle);
                    
                    // Bind popup with custom options
                    marker.bindPopup(popupContent, {
                        className: 'scooter-tooltip',
                        closeButton: true,
                        offset: [0, -5],
                        autoPan: true,
                        autoPanPadding: [50, 50]
                    }).addTo(map);
                    
                    markers[vehicle.id] = marker;
                    bounds.extend([vehicle.latitude, vehicle.longitude]);
                    validVehicleCount++;
                });
                
                console.log(`Successfully updated markers for ${validVehicleCount} vehicles`);
                
                // If force update view and bounds are valid, adjust map view
                if (forceUpdateView && !bounds.isEmpty() && !userHasMovedMap && !initialViewSet) {
                    console.log('Forcing map view update');
                    this.fitBounds(bounds);
                }
                
                return markers;
            } catch (error) {
                console.error('Error updating vehicle markers:', error);
                return markers;
            }
        },
        
        /**
         * Create nearby vehicle markers
         * @param {Array} nearbyVehicles Nearby vehicle data
         * @param {Function} createTooltipFn Function to create marker tooltip
         * @param {Object} userLocation User location
         * @returns {Object} Object containing bounds and markers
         */
        createNearbyMarkers: function(nearbyVehicles, createTooltipFn, userLocation) {
            // Clear existing nearby markers
            this.clearNearbyMarkers();
            
            const bounds = L.latLngBounds();
            if (userLocation && userLocation.lat && userLocation.lng) {
                bounds.extend([userLocation.lat, userLocation.lng]);
            }
            
            nearbyVehicles.forEach(vehicle => {
                // Create marker, bind popup in the same way
                const marker = L.marker([vehicle.latitude, vehicle.longitude], {
                    icon: L.divIcon({
                        className: 'scooter-marker available-marker nearby-marker',
                        iconSize: [30, 30]
                    })
                });

                // Directly create popup content to avoid nesting
                const popupContent = createTooltipFn(vehicle, vehicle.distance);
                
                // Same popup settings
                marker.bindPopup(popupContent, {
                    className: 'scooter-tooltip',
                    closeButton: true,
                    offset: [0, -5],
                    autoPan: true,
                    autoPanPadding: [50, 50]
                }).addTo(map);

                nearbyMarkers[vehicle.id] = marker;
                bounds.extend([vehicle.latitude, vehicle.longitude]);
            });
            
            return { bounds, markers: nearbyMarkers };
        },
        
        /**
         * Set map view
         * @param {Array} center Center coordinates
         * @param {number} zoom Zoom level
         */
        setView: function(center, zoom) {
            map.setView(center, zoom);
        },
        
        /**
         * Fit map to bounds
         * @param {Object} bounds Bounds
         * @param {Object} options Options
         */
        fitBounds: function(bounds, options = {padding: [50, 50], maxZoom: 16}) {
            map.fitBounds(bounds, options);
        },
        
        /**
         * Add user location marker
         * @param {Object} position User location
         */
        addUserLocationMarker: function(position) {
            if (!map) {
                console.error('Map not initialized, cannot add user location marker');
                return;
            }
            
            if (!position || !position.lat || !position.lng) {
                console.error('Invalid position data:', position);
                return;
            }
            
            // Remove existing marker
            if (userLocationMarker) {
                map.removeLayer(userLocationMarker);
            }
            
            // Create two-layer marker for enhanced visual effect
            // 1. Outer blue pulsating circle
            const outerCircle = L.circleMarker([position.lat, position.lng], {
                radius: 15,
                fillColor: '#2196F3',
                fillOpacity: 0.4,
                color: '#2196F3',
                weight: 2,
                opacity: 0.7,
                className: 'pulse-animation'
            }).addTo(map);
            
            // 2. Inner solid blue dot
            const innerCircle = L.circleMarker([position.lat, position.lng], {
                radius: 8,
                fillColor: '#2196F3',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(map);
            
            // Combine the two markers into one object
            userLocationMarker = {
                outer: outerCircle,
                inner: innerCircle,
                getLatLng: function() {
                    return innerCircle.getLatLng();
                },
                remove: function() {
                    map.removeLayer(outerCircle);
                    map.removeLayer(innerCircle);
                }
            };
            
            // Add marker click event to zoom on click
            innerCircle.on('click', () => {
                map.setView([position.lat, position.lng], 16);
            });
            
            outerCircle.on('click', () => {
                map.setView([position.lat, position.lng], 16);
            });
            
            console.log('User location marker added:', position);
            
            // If setting position for the first time, zoom to user location
            if (!initialViewSet) {
                map.setView([position.lat, position.lng], 17);
                initialViewSet = true;
            }
            
            return userLocationMarker;
        },
        
        /**
         * Get user location marker
         * @returns {Object} User location marker
         */
        getUserLocationMarker: function() {
            return userLocationMarker;
        },
        
        /**
         * Calculate distance between two points
         * @param {Array} point1 Coordinates of the first point
         * @param {Array} point2 Coordinates of the second point
         * @returns {number} Distance in meters
         */
        calculateDistance: function(point1, point2) {
            return map.distance(point1, point2);
        },
        
        /**
         * Get map instance
         * @returns {Object} Leaflet map instance
         */
        getMap: function() {
            return map;
        },
        
        /**
         * Get markers
         * @returns {Object} All markers
         */
        getMarkers: function() {
            return markers;
        },
        
        /**
         * Get nearby markers
         * @returns {Object} All nearby markers
         */
        getNearbyMarkers: function() {
            return nearbyMarkers;
        },
        
        /**
         * Update marker style (e.g., set reserved status)
         * @param {string} vehicleId Vehicle ID
         * @param {string} className CSS class name to add
         */
        updateMarkerStyle: function(vehicleId, className = 'reserved-marker') {
            const marker = markers[vehicleId];
            if (marker && marker.getElement) {
                marker.getElement().classList.add(className);
            }
        },
        
        /**
         * Reset marker style (remove reserved status)
         * @param {string} vehicleId Vehicle ID
         * @param {string} className CSS class name to remove
         */
        resetMarkerStyle: function(vehicleId, className = 'reserved-marker') {
            const marker = markers[vehicleId];
            if (marker && marker.getElement) {
                marker.getElement().classList.remove(className);
            }
        },
        
        /**
         * Refresh map data - enhanced error handling
         * @param {boolean} forceUpdateView Whether to force update the view
         */
        refreshMapData: function(forceUpdateView = false) {
            console.log('Request to refresh map data, force update:', forceUpdateView);
            
            try {
                if (typeof DataService === 'undefined') {
                    console.error('DataService is undefined, cannot refresh data');
                    return;
                }
                
                if (typeof DataService.refresh !== 'function') {
                    console.error('DataService.refresh is not a function');
                    return;
                }
                
                DataService.refresh().then(data => {
                    if (!data || data.length === 0) {
                        console.warn('Refresh returned empty data');
                        return;
                    }
                    
                    console.log(`Refresh returned ${data.length} data items`);
                    
                    // If there is a current bounds range, filter vehicles within the viewport
                    if (currentMapBounds && !forceUpdateView) {
                        const visibleVehicles = this.filterVisibleVehicles(data);
                        console.log(`There are ${visibleVehicles.length} vehicles in the viewport`);
                        this.updateVehicleMarkers(visibleVehicles, false, DataService.createVehicleTooltip);
                    } else {
                        console.log('Updating all vehicle markers, force update:', forceUpdateView);
                        this.updateVehicleMarkers(data, forceUpdateView, DataService.createVehicleTooltip);
                    }
                }).catch(error => {
                    console.error('Failed to refresh data:', error);
                });
            } catch (e) {
                console.error('Error refreshing map data:', e);
            }
        },
        
        /**
         * Get current zoom level
         * @returns {number} Current zoom level
         */
        getZoom: function() {
            return map ? map.getZoom() : null;
        }
    };
})();

// If in Node environment, export module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MapService;
}
