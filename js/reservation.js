/**
 * Reservation Service Module - Handles vehicle reservation, cancellation and status management
 */
const ReservationService = {
    // Add notification list to track active notifications
    activeNotifications: [],
    
    // Unified notification display logic - Simplified to single method
    showNotification(title, message, type = 'success') {
        // Prevent duplicate notifications
        const notificationId = `${title}-${Date.now()}`;
        if (this.activeNotifications.some(n => n.title === title && n.message === message)) {
            console.log('Duplicate notification prevented:', title);
            return;
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `reservation-notification ${type}`;
        notification.dataset.id = notificationId;
        notification.innerHTML = `
            <div class="notification-header">
                <h3>${title}</h3>
                <button class="close-notify" onclick="ReservationService.removeNotification('${notificationId}')">&times;</button>
            </div>
            <div class="notification-body">
                <p>${message}</p>
            </div>
            <button class="notification-btn" onclick="ReservationService.removeNotification('${notificationId}')">OK</button>
        `;
        document.body.appendChild(notification);
        
        // Add notification to active list
        this.activeNotifications.push({
            id: notificationId, element: notification, title, message, type
        });
        
        // Auto dismiss
        setTimeout(() => this.removeNotification(notificationId), 8000);
    },
    
    // Convenience methods - Simplified calls
    showSuccessNotification(title, message) { this.showNotification(title, message, 'success'); },
    showErrorNotification(title, message) { this.showNotification(title, message, 'error'); },
    
    // Remove specified notification
    removeNotification(notificationId) {
        const index = this.activeNotifications.findIndex(n => n.id === notificationId);
        const notification = index !== -1 
            ? this.activeNotifications[index].element 
            : document.querySelector(`.reservation-notification[data-id="${notificationId}"]`);
            
        if (notification && notification.parentNode) {
            notification.classList.add('fade-out');
            setTimeout(() => notification.parentNode && notification.parentNode.removeChild(notification), 500);
        }
        
        if (index !== -1) {
            this.activeNotifications.splice(index, 1);
        }
    },
    
    // Clear all notifications
    clearAllNotifications() {
        [...this.activeNotifications].forEach(n => this.removeNotification(n.id));
        this.activeNotifications = [];
    },
    
    // Loading indicator - Simplified design
    showLoadingIndicator(message) {
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'loading-indicator';
        loadingIndicator.innerHTML = `
            <div class="loading-spinner"></div>
            <div>${message || 'Loading...'}</div>
        `;
        document.body.appendChild(loadingIndicator);
        return loadingIndicator;
    },
    
    hideLoadingIndicator(loadingElement) {
        if (loadingElement && loadingElement.parentNode) {
            loadingElement.parentNode.removeChild(loadingElement);
        }
    },
    
    // Reserve vehicle - Simplified logic flow, ensure direct reservation
    async reserveScooter(scooterId, startTime, endTime) {
        try {
            // 1. Verify user authentication
            try {
                this.checkUserAuthentication();
            } catch (authError) {
                this.showErrorNotification('Authentication Required', authError.message);
                // Redirect to login page
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
                return false;
            }
            
            const loadingIndicator = this.showLoadingIndicator('Reserving vehicle...');
            
            // 2. Verify vehicle status
            let vehicleData;
            try {
                vehicleData = await this.verifyVehicleStatus(scooterId);
                
                // Verify vehicle availability
                if (vehicleData.availability != 1) {
                    throw new Error('Vehicle is not available for reservation');
                }
                
                if (vehicleData.battery_level < 15) {
                    throw new Error('Vehicle battery is too low for reservation');
                }
            } catch (statusError) {
                this.hideLoadingIndicator(loadingIndicator);
                this.showErrorNotification('Vehicle Unavailable', statusError.message);
                return false;
            }
            
            // 3. Prepare request data
            const isAdvancedBooking = startTime && endTime;
            const requestData = { scooter_id: scooterId };
            
            // If advanced booking, add time information
            if (isAdvancedBooking) {
                requestData.start_time = startTime;
                requestData.end_time = endTime;
            }
            
            // Send reservation request
            console.log('Sending reservation request:', requestData);
            
            // 4. Send reservation request
            const response = await fetch('api.php?action=reserveScooter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            });
            
            this.hideLoadingIndicator(loadingIndicator);
            
            // 5. Process response
            const data = await UtilService.api.handleResponse(
                response, 
                { success: false, message: 'Server returned an error' },
                'Reservation request failed'
            );
            
            if (!data.success) {
                throw new Error(data.message || 'Reservation failed');
            }
            
            // 6. Update state
            // Create reservation object
            const expiryTime = new Date(data.expiry_time || data.end_time).getTime();
            const reservation = {
                active: true,
                scooterId: scooterId,
                expiryTime: expiryTime,
                startTime: data.start_time,
                endTime: data.end_time,
                bookingId: data.booking_id,
                timerInterval: null,
                scooterData: vehicleData,
                isAdvancedBooking: isAdvancedBooking,
                status: 'reserved'
            };
            
            // Update state
            if (window.state) window.state.reservation = reservation;
            this.saveReservationToLocalStorage(reservation);
            
            // 7. Show success message
            const successMessage = isAdvancedBooking
                ? `Vehicle ${scooterId} has been successfully reserved.<br>Reservation time: ${UtilService.format.reservationTime(reservation.startTime, reservation.endTime)}`
                : `Vehicle ${scooterId} has been successfully reserved.<br>Please pick up within 15 minutes.`;
            
            this.showSuccessNotification('Reservation Success!', successMessage);
            
            // 8. Update UI or navigate
            if (typeof this.startTimer === 'function') {
                this.startTimer();
                this.updateReservationUI();
                this.updateMarkerStyle(scooterId);
            } else {
                setTimeout(() => {
                    // Refresh page to ensure UI update
                    window.location.reload();
                }, 2000);
            }
            
            return true;
        } catch (error) {
            console.error('Reservation failed:', error);
            this.showErrorNotification('Reservation Failed', error.message || 'System error occurred');
            return false;
        }
    },
    
    // Verify vehicle status - Simplified to handle only API call and error handling
    async verifyVehicleStatus(scooterId) {
        try {
            const response = await fetch(`api.php?action=verifyVehicleStatus&id=${scooterId}`);
            const data = await UtilService.api.handleResponse(
                response,
                { success: false, message: 'Vehicle status check failed' },
                'Unable to verify vehicle status'
            );
            
            if (!data.success) {
                throw new Error(data.message || 'Unable to verify vehicle status');
            }
            
            return data.vehicle;
        } catch (error) {
            console.error('Failed to verify vehicle status:', error);
            throw error;
        }
    },

    // Save reservation to local storage - Using utility class
    saveReservationToLocalStorage(reservation) {
        UtilService.storage.save(UtilService.storage.keys.RESERVATION, {
            active: reservation.active,
            scooterId: reservation.scooterId,
            expiryTime: reservation.expiryTime,
            startTime: reservation.startTime,
            endTime: reservation.endTime,
            bookingId: reservation.bookingId,
            scooterData: reservation.scooterData,
            status: reservation.status || 'reserved',
            isAdvancedBooking: reservation.isAdvancedBooking
        });
    },

    // Check existing reservation - Using utility class
    async checkExistingReservation() {
        const storedReservation = UtilService.storage.get(UtilService.storage.keys.RESERVATION);
        if (!storedReservation) return false;
        
        try {
            // Check if reservation has expired (only for reserved status)
            if (storedReservation.status === 'reserved' && storedReservation.expiryTime <= Date.now()) {
                UtilService.storage.remove(UtilService.storage.keys.RESERVATION);
                return false;
            }
            
            // Verify reservation status
            const isValid = await this.verifyReservationWithServer(storedReservation.scooterId, storedReservation.bookingId);
            if (!isValid) {
                UtilService.storage.remove(UtilService.storage.keys.RESERVATION);
                this.showErrorNotification('Reservation Expired', 'Your reservation has expired or been cancelled');
                return false;
            }
            
            // Restore reservation state
            if (window.state) {
                window.state.reservation = { ...storedReservation, active: true, timerInterval: null };
                
                // Update UI
                this.updateReservationUI();
                
                // Delay marker style update
                setTimeout(() => this.updateMarkerStyle(storedReservation.scooterId), 1000);
            }
            return true;
        } catch (e) {
            console.error('Error parsing reservation data:', e);
            UtilService.storage.remove(UtilService.storage.keys.RESERVATION);
            return false;
        }
    },

    // Verify reservation status
    async verifyReservationWithServer(scooterId, bookingId) {
        try {
            let url = `api.php?action=verifyReservation&id=${scooterId}`;
            if (bookingId) url += `&booking_id=${bookingId}`;
            
            // Get reservation status
            const response = await fetch(url);
            const data = await UtilService.api.handleResponse(
                response, 
                { is_valid: true }, 
                'Unable to verify reservation status'
            );
            return data.is_valid === true;
        } catch (error) {
            console.error('Failed to verify reservation status:', error);
            // If unable to verify, assume reservation is still valid by default
            return true;
        }
    },

    // Get user reservation history
    async getUserReservations() {
        try {
            const response = await fetch('api.php?action=getUserReservations');
            const data = await UtilService.api.handleResponse(
                response,
                { success: false, reservations: [] },
                'Failed to get reservation history'
            );
            
            // Process reservation data to ensure compatibility
            const reservations = (data.reservations || []).map(reservation => {
                return {
                    ...reservation,
                    // Ensure expiry_time has value, even if NULL in database
                    expiry_time: reservation.expiry_time || reservation.end_date,
                    // Add formatted status
                    status_formatted: UtilService.format.bookingStatus(reservation.status),
                    // Format reservation time
                    formatted_time: UtilService.format.reservationTime(
                        reservation.start_date || reservation.start_time,
                        reservation.end_date || reservation.end_time
                    ),
                    // Add battery level display format
                    battery_display: typeof reservation.battery_level !== 'undefined' ? 
                        `${reservation.battery_level}%` : 'N/A'
                };
            });
            
            return reservations;
        } catch (error) {
            console.error('Failed to get reservation history:', error);
            return [];
        }
    },

    // Get available time slots
    async getAvailableTimeSlots(scooterId, date) {
        if (!scooterId || !date) {
            console.error('Missing required parameters:', { scooterId, date });
            throw new Error('Scooter ID and date are required');
        }

        // Validate date format
        try {
            const dateObj = new Date(date);
            if (isNaN(dateObj.getTime())) {
                throw new Error('Invalid date format');
            }
            date = dateObj.toISOString().split('T')[0];
        } catch (e) {
            console.error('Date validation error:', e);
            throw new Error('Invalid date format. Please use YYYY-MM-DD');
        }

        // Generate default time slots
        const timeSlots = [];
        const startHour = 9; // Start at 9 AM
        const endHour = 21;  // End at 9 PM
        const now = new Date();
        const selectedDate = new Date(date);
        const isToday = selectedDate.toDateString() === now.toDateString();

        for (let hour = startHour; hour < endHour; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                // If today, skip past time slots
                if (isToday) {
                    const slotTime = new Date(selectedDate);
                    slotTime.setHours(hour, minute, 0);
                    if (slotTime <= now) continue;
                }

                const startTime = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                const endHourMin = minute === 30 ? [(hour + 1), 0] : [hour, 30];
                const endTime = `${endHourMin[0].toString().padStart(2, '0')}:${endHourMin[1].toString().padStart(2, '0')}`;

                timeSlots.push({
                    start: startTime,
                    end: endTime,
                    available: true, // Default available
                    start_value: `${date} ${startTime}:00`,
                    end_value: `${date} ${endTime}:00`
                });
            }
        }

        return timeSlots;
    },

    // Update reservation UI - Improved, display different content based on status
    updateReservationUI() {
        if (!window.state || !window.state.reservation.active) return;
        
        const statusPanel = document.getElementById('reservationStatus');
        if (!statusPanel) return;
        
        const isAdvancedBooking = window.state.reservation.isAdvancedBooking;
        const isInProgress = window.state.reservation.status === 'in_progress';
        
        // Update UI elements
        const elements = {
            title: statusPanel.querySelector('h4'),
            timerContainer: statusPanel.querySelector('.timer-container'),
            reservedScooterInfo: document.getElementById('reservedScooterInfo'),
            vehicleId: document.getElementById('reservedScooterId'),
            reservedTime: document.getElementById('reservedTime'),
            expiryTime: document.getElementById('reservationExpiryTime'),
            expiryTimeLabel: document.getElementById('expiryTimeLabel'),
            startButton: document.getElementById('startVehicle'),
            completeButton: document.getElementById('completeTrip'),
            cancelButton: document.getElementById('cancelReservation'),
            navigateButton: document.getElementById('navigateToReserved')
        };
        
        // Update UI display based on status
        if (elements.title) {
            elements.title.textContent = isInProgress ? "Trip In Progress" : (isAdvancedBooking ? "Reservation Successful" : "Vehicle Reserved");
        }
        
        // Set different panel styles for different statuses
        statusPanel.classList.toggle('in-progress', isInProgress);
        
        // Control element display based on status
        if (isInProgress) {
            // In progress - Hide unnecessary info, show only "Complete Trip" button
            if (elements.timerContainer) elements.timerContainer.style.display = 'none';
            if (elements.reservedScooterInfo) elements.reservedScooterInfo.style.display = 'none';
            if (elements.startButton) elements.startButton.style.display = 'none';
            if (elements.completeButton) elements.completeButton.style.display = 'block';
            if (elements.cancelButton) elements.cancelButton.style.display = 'none';
            if (elements.navigateButton) elements.navigateButton.style.display = 'none';
        } else {
            // Reservation status - Show full info
            if (elements.timerContainer) {
                elements.timerContainer.style.display = isAdvancedBooking ? 'none' : 'block';
            }
            if (elements.reservedScooterInfo) elements.reservedScooterInfo.style.display = 'block';
            if (elements.vehicleId) elements.vehicleId.textContent = window.state.reservation.scooterId;
            
            if (elements.reservedTime) {
                elements.reservedTime.textContent = new Date().toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit'
                });
            }
            
            if (elements.expiryTime && elements.expiryTimeLabel) {
                const showExpiry = !isAdvancedBooking;
                elements.expiryTimeLabel.style.display = showExpiry ? 'inline' : 'none';
                elements.expiryTime.style.display = showExpiry ? 'inline' : 'none';
                
                if (showExpiry) {
                    elements.expiryTime.textContent = new Date(window.state.reservation.expiryTime)
                        .toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                }
            }
            
            if (elements.startButton) elements.startButton.style.display = 'block';
            if (elements.completeButton) elements.completeButton.style.display = 'none';
            if (elements.cancelButton) elements.cancelButton.style.display = 'block';
            if (elements.navigateButton) elements.navigateButton.style.display = 'block';
        }
        
        // Show panel
        statusPanel.style.display = 'block';
    },

    // Start timer - Using utility class
    startTimer() {
        if (!window.state || !window.state.reservation) return;
        
        // Clear old timer
        UtilService.timers.clear('reservationTimer');
        
        // Show countdown panel
        const statusPanel = document.getElementById('reservationStatus');
        if (statusPanel) {
            statusPanel.style.display = 'block';
        }
        
        // Set new timer
        window.state.reservation.timerInterval = UtilService.timers.create(
            'reservationTimer',
            () => {
                const remaining = window.state.reservation.expiryTime - Date.now();
                
                if (remaining <= 0) {
                    // Reservation expired
                    this.resetReservation();
                    this.showErrorNotification('Reservation Expired', 'Your reservation has ended');
                    return;
                }
                
                // Update time display
                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                const timerEl = document.getElementById('reservationTimer');
                if (timerEl) {
                    timerEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    
                    // Change color based on urgency
                    if (minutes < 2) {
                        timerEl.style.color = '#f44336'; // Red
                        if (!timerEl.classList.contains('pulse')) {
                            timerEl.classList.add('pulse');
                        }
                    } else if (minutes < 5) {
                        timerEl.style.color = '#ff9800'; // Orange
                    } else {
                        timerEl.style.color = 'white';
                    }
                }
            },
            1000
        );
    },

    // Cancel reservation - Simplified process
    async cancelReservation() {
        if (!window.state || !window.state.reservation.active) return false;
        
        try {
            const loadingIndicator = this.showLoadingIndicator('Cancelling reservation...');
            const response = await fetch('api.php?action=cancelReservation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scooter_id: window.state.reservation.scooterId })
            });
            
            this.hideLoadingIndicator(loadingIndicator);
            
            const data = await UtilService.api.handleResponse(
                response,
                { success: false, message: 'Failed to cancel reservation' },
                'Cancellation request failed'
            );
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to cancel reservation');
            }
            
            // Reset state
            this.resetReservation();
            this.showSuccessNotification('Reservation Cancelled', 'Your reservation has been successfully cancelled');
            
            // Update map
            if (window.MapService && typeof window.MapService.refreshMapData === 'function') {
                setTimeout(() => window.MapService.refreshMapData(), 1000);
            }
            
            return true;
        } catch (error) {
            console.error('Cancellation failed:', error);
            this.showErrorNotification('Cancellation Failed', error.message);
            return false;
        }
    },
    
    // Reset reservation state - Simplified process 
    resetReservation() {
        if (!window.state) return;
        
        // Clear timer
        UtilService.timers.clear('reservationTimer');
        
        // Reset state
        window.state.reservation = {
            active: false, scooterId: null, expiryTime: null, 
            timerInterval: null, scooterData: null
        };
        
        // Clear storage and UI
        UtilService.storage.remove(UtilService.storage.keys.RESERVATION);
        const statusPanel = document.getElementById('reservationStatus');
        if (statusPanel) statusPanel.style.display = 'none';
        
        // Clear notifications
        this.clearAllNotifications();
    },

    // Update marker style
    updateMarkerStyle(scooterId) {
        // If on map page, update vehicle marker style
        if (window.MapService && typeof window.MapService.updateMarkerStyle === 'function') {
            window.MapService.updateMarkerStyle(scooterId);    
        }
    },

    // Verify user authentication
    checkUserAuthentication() {
        // Check if global state is available
        if (!window.state || !window.state.userInfo) {
            throw new Error('User state not available');
        }
        
        // Check if user is logged in
        if (!window.state.userInfo.isLoggedIn) {
            throw new Error('Please login first to reserve a vehicle');
        }
        
        return true;
    },

    // Start trip
    async startTrip(bookingId, scooterId) {
        try {
            const loadingIndicator = this.showLoadingIndicator('Starting trip...');
            
            const response = await fetch('api.php?action=startTrip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId, scooter_id: scooterId })
            });
            
            this.hideLoadingIndicator(loadingIndicator);
            
            const data = await UtilService.api.handleResponse(
                response,
                { success: false, message: 'Failed to start trip' },
                'Trip start request failed'
            );
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to start trip');
            }
            
            // Update reservation status
            if (window.state && window.state.reservation) {
                // Stop timer
                UtilService.timers.clear('reservationTimer');
                
                window.state.reservation.status = 'in_progress';
                this.saveReservationToLocalStorage(window.state.reservation);
            }
            
            // Update UI
            this.updateReservationUI(); // Call update UI method to display UI suitable for "in progress" status
            
            this.showSuccessNotification('Trip Started', 'Your trip has started. Enjoy your ride!');
            
            return true;
        } catch (error) {
            console.error('Start trip failed:', error);
            this.showErrorNotification('Failed to Start Trip', error.message);
            return false;
        }
    },
    
    // End trip
    async endTrip(bookingId, scooterId) {
        try {
            const loadingIndicator = this.showLoadingIndicator('Completing trip...');
            
            const response = await fetch('api.php?action=endTrip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId, scooter_id: scooterId })
            });
            
            this.hideLoadingIndicator(loadingIndicator);
            
            const data = await UtilService.api.handleResponse(
                response,
                { success: false, message: 'Failed to complete trip' },
                'Trip end request failed'
            );
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to complete trip');
            }
            
            // Reset reservation state
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
    }
};

// Export module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReservationService;
}
