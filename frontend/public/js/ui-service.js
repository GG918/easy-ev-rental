/**
 * UI Service Module - Handles user interface elements and interactions
 */
const UIService = (function() {
    // Private variables - Essential only
    let selectedSlot = null;
    let selectedDate = '';
    let timeSlots = [];
    let initialized = false;
    let previousSelectedTime = null; // Added: Remember user's previous time selection

    // Common utility functions
    const utils = {
        // Format date
        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        
        // Safely bind events
        safeAddEventListener(element, eventType, handler) {
            if (!element) return null;
            element.addEventListener(eventType, handler);
            return element;
        },
        
        // Replace element and bind events - Prevent duplicate binding
        replaceElementAndBind(elementId, eventType, handler) {
            const element = document.getElementById(elementId);
            if (!element) return null;
            
            const newElement = element.cloneNode(true);
            if (element.parentNode) element.parentNode.replaceChild(newElement, element);
            
            newElement.addEventListener(eventType, handler);
            return newElement;
        }
    };
    
    // Common event binding function
    function safeEventListener(elementId, eventType, handler) {
        const element = document.getElementById(elementId);
        if (!element) return null;
        
        // Clear any existing event listeners of the same type
        const newElement = element.cloneNode(true);
        if (element.parentNode) element.parentNode.replaceChild(newElement, element);
        
        // Bind new event
        newElement.addEventListener(eventType, handler);
        return newElement;
    }
    
    // Initialize map controls
    function initMapControls() {
        // Removed find nearby button and user location zoom button, keeping other necessary bindings
        
        // Remove the zoom to user location button binding code
        // const zoomToUserBtn = document.getElementById('zoomToUserLocation');
        // if (zoomToUserBtn) {
        //     zoomToUserBtn.addEventListener('click', () => {
        //         if (window.MapService && typeof window.MapService.zoomToUserLocation === 'function') {
        //             window.MapService.zoomToUserLocation(18); // Set zoom level to 18
        //         }
        //     });
        // }
        
        // Other button events
        safeEventListener('closeNearbyInfo', 'click', () => {
            document.getElementById('nearbyInfo').style.display = 'none';
            window.state.showingNearbyOnly = false;
            MapService.clearNearbyMarkers();
            MapService.updateVehicleMarkers(
                DataService.getVehicleData(), 
                true, 
                DataService.createVehicleTooltip
            );
        });
        
        safeEventListener('expandSearchRadius', 'click', () => {
            const radius = document.getElementById('searchRadius');
            if (radius && radius.selectedIndex < radius.options.length - 1) {
                radius.selectedIndex = radius.selectedIndex + 1;
                document.getElementById('findNearbyButton').click();
            }
        });
        
        safeEventListener('showAllScooters', 'click', () => {
            document.getElementById('nearbyInfo').style.display = 'none';
            window.state.showingNearbyOnly = false;
            MapService.clearNearbyMarkers();
            MapService.updateVehicleMarkers(
                DataService.getVehicleData(), 
                true, 
                DataService.createVehicleTooltip
            );
        });
    }
    
    // Initialize reservation controls
    function initReservationControls() {
        if (typeof ReservationService !== 'undefined') {
            ReservationService.checkExistingReservation();
            
            // Bind reservation-related buttons
            safeEventListener('cancelReservation', 'click', () => {
                ReservationService.cancelReservation();
            });
            
            safeEventListener('navigateToReserved', 'click', () => {
                if (window.state.reservation.active && window.state.reservation.scooterData) {
                    startNavigation(
                        window.state.reservation.scooterData.latitude, 
                        window.state.reservation.scooterData.longitude
                    );
                }
            });
        }
    }
    
    // Improved initialization of booking modal - Fix date selection logic
    function initBookingModal() {
        const modal = document.getElementById('bookingModal');
        const form = document.getElementById('bookingForm');
        
        if (!modal || !form) return;
        
        // Close button event
        const closeBtn = document.querySelector('.booking-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }
        
        // Generate date selection for three days starting from today
        const dateSelector = document.querySelector('.date-selector');
        if (dateSelector) {
            // Regenerate date buttons each time the modal is opened to ensure the dates are up-to-date
            updateDateButtons(dateSelector);
        }
        
        // Modify form submission logic to fix validation process
        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (!selectedDate) {
                    ReservationService.showErrorNotification('Select Date', 'Please select a date first');
                    return;
                }
                
                if (!selectedSlot) {
                    ReservationService.showErrorNotification('Select Time Slot', 'Please select a time slot after selecting a date');
                    return;
                }
                
                const vehicleId = document.getElementById('vehicle_id').value;
                if (!vehicleId) {
                    ReservationService.showErrorNotification('System Error', 'Vehicle ID is missing');
                    return;
                }
                
                // Handle time and reservation
                try {
                    // Adjust time based on selected date
                    const startTimeDate = new Date(selectedSlot.start_value);
                    const endTimeDate = new Date(selectedSlot.end_value);
                    const selectedDateObj = new Date(selectedDate);
                    
                    startTimeDate.setFullYear(selectedDateObj.getFullYear(), selectedDateObj.getMonth(), selectedDateObj.getDate());
                    endTimeDate.setFullYear(selectedDateObj.getFullYear(), selectedDateObj.getMonth(), selectedDateObj.getDate());
                    
                    const startTime = startTimeDate.toISOString().slice(0, 19).replace('T', ' ');
                    const endTime = endTimeDate.toISOString().slice(0, 19).replace('T', ' ');
                    
                    // Directly use ReservationService to handle reservation
                    const result = await ReservationService.reserveScooter(vehicleId, startTime, endTime);
                    
                    if (result) {
                        // Close modal after successful reservation
                        modal.style.display = 'none';
                    }
                } catch (error) {
                    ReservationService.showErrorNotification('System Error', 'Error during reservation: ' + error.message);
                }
            });
        }
        
        // Close modal when clicking outside of it
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    // New function: Update date buttons to show the current week's dates (Monday to Sunday) and disable past dates
    function updateDateButtons(dateSelector) {
        // Clear existing buttons
        dateSelector.innerHTML = '';
        
        // Get the date range for this week (Monday to Sunday)
        const weekDates = getWeekDates();
        
        // Current date (for comparison, to disable past dates)
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Reset time part for correct date comparison
        
        // Generate date selection for seven days (Monday to Sunday)
        weekDates.forEach((date, index) => {
            const dateStr = utils.formatDate(date);
            
            // Get day name
            const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const dayName = dayNames[index];
            
            // Format date as "Month.Day"
            const formattedDate = formatMonthDay(date);
            
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'date-btn';
            
            // Check if the date is in the past (earlier than today)
            const isPastDate = date < today;
            
            // If it's a past date, add disabled class and disable the button
            if (isPastDate) {
                button.classList.add('disabled');
                button.disabled = true;
            }
            
            button.dataset.date = dateStr;
            button.innerHTML = `<div class="date-name">${dayName}</div><div class="date-value">${formattedDate}</div>`;
            
            // Only add click event to non-disabled buttons
            if (!isPastDate) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active state from all date buttons
                    document.querySelectorAll('.date-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Add active state to the current button
                    this.classList.add('active');
                    
                    // Set selected date and load time slots
                    selectedDate = this.dataset.date;
                    const vehicleId = document.getElementById('vehicle_id').value;
                    
                    if (vehicleId) {
                        loadTimeSlots(selectedDate, vehicleId);
                    }
                });
            }
            
            dateSelector.appendChild(button);
        });
    }
    
    // Get the dates for this week (Monday to Sunday)
    function getWeekDates() {
        const today = new Date();
        const currentDay = today.getDay(); // 0 is Sunday, 1-6 is Monday to Saturday
        
        // Calculate the date for this Monday
        const monday = new Date(today);
        // In JavaScript, getDay() returns 0-6, where 0 is Sunday, 1-6 is Monday to Saturday
        // So special handling is needed: if today is Sunday (0), subtract 6 days; otherwise, subtract (currentDay-1) days
        const daysToSubtract = currentDay === 0 ? 6 : currentDay - 1;
        monday.setDate(today.getDate() - daysToSubtract);
        
        // Generate the seven days for this week (Monday to Sunday)
        const weekDates = [];
        for (let i = 0; i < 7; i++) {
            const date = new Date(monday);
            date.setDate(monday.getDate() + i);
            weekDates.push(date);
        }
        
        return weekDates;
    }
    
    // Format date as "Month.Day"
    function formatMonthDay(date) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[date.getMonth()];
        const day = date.getDate();
        
        return `${month}.${day}`;
    }
    
    // Improved load time slots function - Add loading state and error handling, and remember previous selection
    async function loadTimeSlots(date, vehicleId) {
        if (!date || !vehicleId) {
            console.error('Missing required parameters for loadTimeSlots:', { date, vehicleId });
            return;
        }
        
        const timeSlotsContainer = document.getElementById('timeSlots');
        if (!timeSlotsContainer) return;
        
        // Save the currently selected time before loading new time slots
        if (selectedSlot) {
            previousSelectedTime = {
                start: selectedSlot.start,
                end: selectedSlot.end
            };
        }
        
        // Clear existing time slots and show loading state
        timeSlotsContainer.innerHTML = '<div class="loading-time-slots">Loading available times...</div>';
        
        // Reset selected time slot state
        selectedSlot = null;
        
        // Disable submit button
        const submitButton = document.getElementById('submitBooking');
        if (submitButton) submitButton.disabled = true;
        
        // Hide summary
        const bookingSummary = document.getElementById('bookingSummary');
        if (bookingSummary) bookingSummary.style.display = 'none';
        
        try {
            console.log(`Fetching time slots for vehicle ${vehicleId} on ${date}`);
            const response = await fetch(`/web/backend/api/api.php/timeslots?vehicle_id=${vehicleId}&date=${date}`);
            
            if (!response.ok) {
                throw new Error(`Failed to get time slots: ${response.status} ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                timeSlots = data.data.time_slots;
                renderTimeSlots();
            } else {
                // Show error message returned by the API
                timeSlotsContainer.innerHTML = `<div class="time-slots-error">${data.message || 'Failed to load time slots'}</div>`;
                ReservationService.showErrorNotification('Error', data.message || 'Failed to get time slots');
            }
        } catch (error) {
            console.error('Time slot loading error:', error);
            timeSlotsContainer.innerHTML = '<div class="time-slots-error">System error loading time slots. Please try again.</div>';
            ReservationService.showErrorNotification('System Error', 'Unable to load available time slots: ' + error.message);
        }
    }
    
    // Render time slots - Optimize UI feedback and divide time slots into morning, afternoon, and evening, and filter out past time slots
    function renderTimeSlots() {
        const timeSlotsContainer = document.getElementById('timeSlots');
        if (!timeSlotsContainer) return;
        
        timeSlotsContainer.innerHTML = '';
        
        if (!timeSlots || timeSlots.length === 0) {
            timeSlotsContainer.innerHTML = '<div class="no-slots-message">No time slots available for this date.</div>';
            return;
        }
        
        // Get current time for comparison
        const now = new Date();
        const today = utils.formatDate(now);
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        
        // Is it a booking for today
        const isToday = selectedDate === today;
        
        // Divide time slots into morning (06:00-12:00), afternoon (12:00-18:00), and evening (18:00-22:00)
        const morningSlots = [];
        const afternoonSlots = [];
        const eveningSlots = [];
        
        timeSlots.forEach((slot, index) => {
            const startHour = parseInt(slot.start.split(':')[0]);
            const startMinute = parseInt(slot.start.split(':')[1]);
            
            // If it's today, filter out past time slots
            if (isToday) {
                // If the start time is earlier than the current time, mark it as unavailable
                if (startHour < currentHour || (startHour === currentHour && startMinute <= currentMinute)) {
                    slot.available = false;
                }
            }
            
            if (startHour >= 6 && startHour < 12) {
                morningSlots.push({slot, index});
            } else if (startHour >= 12 && startHour < 18) {
                afternoonSlots.push({slot, index});
            } else if (startHour >= 18) {
                eveningSlots.push({slot, index});
            }
        });
        
        // Create time slot group container
        const container = document.createElement('div');
        container.className = 'time-slots-container';
        
        // Add morning time slots - Only add if it's not today or it's still morning
        if (morningSlots.length > 0 && (!isToday || currentHour < 12)) {
            const morningGroup = createTimeSlotGroup('Morning', morningSlots);
            container.appendChild(morningGroup);
        }
        
        // Add afternoon time slots - Only add if it's not today or it's still afternoon
        if (afternoonSlots.length > 0 && (!isToday || currentHour < 18)) {
            const afternoonGroup = createTimeSlotGroup('Afternoon', afternoonSlots);
            container.appendChild(afternoonGroup);
        }
        
        // Add evening time slots - Only add if it's not today or it's still evening
        if (eveningSlots.length > 0 && (!isToday || currentHour < 22)) {
            const eveningGroup = createTimeSlotGroup('Evening', eveningSlots);
            container.appendChild(eveningGroup);
        }
        
        // If all time slots have passed, show a message
        if (container.children.length === 0) {
            timeSlotsContainer.innerHTML = '<div class="no-slots-message">No more time slots available for today. Please select another date.</div>';
            return;
        }
        
        timeSlotsContainer.appendChild(container);
        
        // If no matching time slot is found but there was a previous selection, show a notification
        if (previousSelectedTime && !selectedSlot) {
            const matchingTime = `${previousSelectedTime.start} - ${previousSelectedTime.end}`;
            console.log(`Previously selected time ${matchingTime} is not available on this date.`);
        }
    }
    
    // Create time slot group
    function createTimeSlotGroup(title, slots) {
        const group = document.createElement('div');
        group.className = 'time-slot-group';
        
        const groupTitle = document.createElement('h4');
        groupTitle.className = 'time-group-title';
        groupTitle.textContent = title;
        group.appendChild(groupTitle);
        
        const slotsContainer = document.createElement('div');
        slotsContainer.className = 'time-slots-column';
        
        // Filter out all unavailable time slots to avoid displaying empty groups
        const availableSlots = slots.filter(({slot}) => slot.available);
        
        if (availableSlots.length === 0) {
            const noSlotsMsg = document.createElement('div');
            noSlotsMsg.className = 'no-slots-in-group';
            noSlotsMsg.textContent = 'No available time slots for this period';
            slotsContainer.appendChild(noSlotsMsg);
        } else {
            slots.forEach(({slot, index}) => {
                const slotEl = document.createElement('div');
                slotEl.className = 'time-slot';
                if (!slot.available) {
                    slotEl.classList.add('unavailable');
                    slotEl.title = 'This time slot is unavailable';
                }
                slotEl.innerHTML = `${slot.start} - ${slot.end}`;
                slotEl.dataset.index = index;
                
                // Check if it matches the previous selection
                const isPreviouslySelected = previousSelectedTime && 
                                           slot.start === previousSelectedTime.start && 
                                           slot.end === previousSelectedTime.end;
                
                if (slot.available) {
                    // Add click event
                    slotEl.addEventListener('click', () => selectTimeSlot(slotEl, index));
                    
                    // If it was the previously selected time slot and is available, auto-select it
                    if (isPreviouslySelected) {
                        setTimeout(() => {
                            selectTimeSlot(slotEl, index);
                        }, 100);
                    }
                }
                
                slotsContainer.appendChild(slotEl);
            });
        }
        
        group.appendChild(slotsContainer);
        return group;
    }
    
    // Select time slot
    function selectTimeSlot(slotEl, index) {
        // Remove selection state from other slots
        document.querySelectorAll('.time-slot.selected').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Add selection state to the current slot
        slotEl.classList.add('selected');
        selectedSlot = timeSlots[index];
        
        // Update summary
        updateSummary();
        
        // Enable submit button
        const submitButton = document.getElementById('submitBooking');
        if (submitButton) submitButton.disabled = false;
    }
    
    // Update booking summary
    function updateSummary() {
        const bookingSummary = document.getElementById('bookingSummary');
        const summaryVehicleId = document.getElementById('summaryVehicleId');
        const summaryDate = document.getElementById('summaryDate');
        const summaryTime = document.getElementById('summaryTime');
        
        if (!bookingSummary || !summaryVehicleId || !summaryDate || !summaryTime) return;
        
        if (selectedSlot) {
            const vehicleId = document.getElementById('vehicle_id')?.value || '';
            const dateObj = new Date(selectedDate);
            const formattedDate = dateObj.toLocaleDateString('en-US', {
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
            
            summaryVehicleId.textContent = vehicleId;
            summaryDate.textContent = formattedDate;
            summaryTime.textContent = `${selectedSlot.start} - ${selectedSlot.end}`;
            bookingSummary.style.display = 'block';
        } else {
            bookingSummary.style.display = 'none';
        }
    }
    
    // Return public methods
    return {
        /**
         * Initialize UI service
         */
        init: function() {
            // Prevent duplicate initialization
            if (initialized) {
                console.log('UI Service already initialized, skipping');
                return;
            }
            
            console.log('Initializing UI service...');
            
            // Clear any duplicate notifications
            if (typeof ReservationService !== 'undefined' && 
                typeof ReservationService.clearAllNotifications === 'function') {
                ReservationService.clearAllNotifications();
            }
            
            // Initialize components
            initBookingModal();
            initMapControls();
            initReservationControls();
            
            // Handle reservation button click using event delegation - Fix event binding
            document.removeEventListener('click', this.handleReserveBtnClick);
            document.addEventListener('click', this.handleReserveBtnClick);
            
            // Mark as initialized
            initialized = true;
            
            console.log('UI service initialization complete, reservation functionality enabled');
        },
        
        // Reservation button click handler - Fix function name and implementation
        handleReserveBtnClick: function(event) {
            // Ensure the clicked element is a reservation button
            if (!event.target || !event.target.classList || !event.target.classList.contains('reserve-btn')) {
                return;
            }
            
            event.preventDefault();
            event.stopPropagation();
            
            console.log('Reservation button click detected:', event.target);
            
            // Get vehicle ID
            const scooterId = event.target.getAttribute('data-scooter-id');
            if (!scooterId) {
                console.error('Reservation failed: scooterId not found');
                return;
            }
            
            console.log('Starting reservation for vehicle:', scooterId);
            
            // Get battery level - Extract from parent element's tooltip content
            let batteryLevel = 100; // Default value
            try {
                const tooltipContent = event.target.closest('.leaflet-popup-content');
                if (tooltipContent) {
                    const batteryText = tooltipContent.querySelector('.battery-text');
                    if (batteryText) {
                        batteryLevel = parseInt(batteryText.textContent) || 100;
                    }
                }
            } catch (e) {
                console.warn('Unable to get battery level:', e);
            }
            
            // Call reservation service
            if (typeof ReservationService !== 'undefined') {
                try {
                    // Check if the user is logged in, if not, an exception will be thrown
                    ReservationService.checkUserAuthentication();
                
                    // User is logged in, continue with reservation process
                    if (typeof UIService.openBookingModal === 'function') {
                        UIService.openBookingModal(scooterId, batteryLevel);
                    } else {
                        // Direct reservation
                        ReservationService.reserveScooter(scooterId);
                    }
                } catch (error) {
                    console.log('User not logged in:', error.message);
                    ReservationService.showErrorNotification('Login Required', 'Please login first to reserve a vehicle');
                    
                    // Delay redirect to the home login page
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                }
            } else {
                console.error('Reservation failed: ReservationService not defined');
                alert('System error: Reservation service not available');
            }
        },

        /**
         * Open advanced booking modal - Modify to show this week's dates and auto-select today or the earliest available date
         * @param {string} scooterId Vehicle ID
         * @param {number} batteryLevel Battery level
         */
        openBookingModal: function(scooterId, batteryLevel = 100) {
            // Reset selection state and previous selection time
            selectedSlot = null;
            previousSelectedTime = null;
            
            // Get modal element
            const modal = document.getElementById('bookingModal');
            if (!modal) {
                console.error('Booking modal not found');
                return;
            }
            
            // Update vehicle information in the modal
            document.getElementById('bookingVehicleId').textContent = scooterId;
            document.getElementById('modalVehicleId').textContent = scooterId;
            document.getElementById('vehicle_id').value = scooterId;
            
            // Update battery level display
            const batteryLevelEl = document.getElementById('modalBatteryLevel');
            const batteryTextEl = document.getElementById('modalBatteryText');
            if (batteryLevelEl && batteryTextEl) {
                batteryLevelEl.style.width = batteryLevel + '%';
                batteryTextEl.textContent = batteryLevel + '%';
                
                // Set battery color
                let batteryColor = '#4CAF50'; // Green
                if (batteryLevel < 20) {
                    batteryColor = '#f44336'; // Red
                } else if (batteryLevel < 50) {
                    batteryColor = '#ff9800'; // Orange
                }
                batteryLevelEl.style.background = batteryColor;
            }
            
            // Reset selection state
            selectedSlot = null;
            
            // Disable submit button
            const submitButton = document.getElementById('submitBooking');
            if (submitButton) submitButton.disabled = true;
            
            // Clear time slots area and show instruction
            const timeSlotsContainer = document.getElementById('timeSlots');
            if (timeSlotsContainer) {
                timeSlotsContainer.innerHTML = '<div class="time-slots-instruction">Please select a date to view available time slots</div>';
            }
            
            // Hide booking summary
            const bookingSummary = document.getElementById('bookingSummary');
            if (bookingSummary) bookingSummary.style.display = 'none';
            
            // Update date buttons each time the modal is opened to ensure the dates are up-to-date
            const dateSelector = document.querySelector('.date-selector');
            if (dateSelector) {
                updateDateButtons(dateSelector);
            }
            
            // Auto-select today or the earliest available date
            const today = utils.formatDate(new Date());
            const dateButtons = document.querySelectorAll('.date-btn:not(.disabled)');
            if (dateButtons && dateButtons.length > 0) {
                dateButtons.forEach(btn => btn.classList.remove('active'));
                
                // Select the first non-disabled button (earliest available date)
                const firstAvailableBtn = dateButtons[0];
                firstAvailableBtn.classList.add('active');
                selectedDate = firstAvailableBtn.dataset.date;
                
                // Load time slots for that date
                setTimeout(() => {
                    loadTimeSlots(selectedDate, scooterId);
                }, 100);
            } else {
                // If no dates are available (rare case), show an error message
                const timeSlotsContainer = document.getElementById('timeSlots');
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML = '<div class="no-slots-message">No dates available for booking in the current week.</div>';
                }
            }
            
            // Show modal
            modal.style.display = 'flex';
        },

        /**
         * Create vehicle action buttons HTML
         * @param {Object} vehicle Vehicle object
         * @returns {string} HTML button code
         */
        createVehicleActionButtons: function(vehicle) {
            if (!vehicle) return '';
            
            const isReserved = window.state?.reservation?.active && 
                               window.state.reservation.scooterId == vehicle.id;
            
            // Show reserve button for non-logged-in users as well, login check logic will be moved to button click event handler
            if (!window.state?.reservation?.active) {
                return `
                    <p>
                        <a href="#" class="navigate-btn" onclick="event.preventDefault(); startNavigation(${vehicle.latitude}, ${vehicle.longitude});">
                            Navigate
                        </a>
                        <button class="reserve-btn" data-scooter-id="${vehicle.id}">
                            Reserve
                        </button>
                    </p>
                `;
            } 
            // If this is the reserved vehicle, show cancel reservation button
            else if (isReserved) {
                return `
                    <p>
                        <a href="#" class="navigate-btn" onclick="event.preventDefault(); startNavigation(${vehicle.latitude}, ${vehicle.longitude});">
                            Navigate
                        </a>
                        <button class="cancel-btn" onclick="event.preventDefault(); event.stopPropagation(); ReservationService.cancelReservation();">
                            Cancel reservation
                        </button>
                    </p>
                `;
            } 
            // If another vehicle is reserved, only show navigate button
            else {
                return `
                    <p>
                        <a href="#" class="navigate-btn" onclick="event.preventDefault(); startNavigation(${vehicle.latitude}, ${vehicle.longitude});">
                            Navigate
                        </a>
                    </p>
                `;
            }
        },
    };
})();

// Navigate to specified location
function startNavigation(lat, lng) {
    // Determine device type and choose the appropriate navigation app
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        // iOS device, use Apple Maps
        window.open(`maps://maps.apple.com/maps?daddr=${lat},${lng}&dirflg=d`);
    } else {
        // Other devices, use Google Maps
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`);
    }
}

// Export module if in Node environment
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UIService;
}
