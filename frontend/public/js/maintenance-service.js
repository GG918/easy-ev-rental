/**
 * Maintenance Service - Handles maintenance record related functions
 */
const MaintenanceService = {
    maintenanceData: [],
    activeFilters: {
        status: 'all',
        date: ''
    },
    selectedMaintenanceId: null,
    
    /**
     * Initialize maintenance service
     */
    init: function() {
        // Get maintenance records
        this.loadMaintenanceData();
        
        // Set event listeners
        this.setupEventListeners();
    },
    
    /**
     * Set event listeners
     */
    setupEventListeners: function() {
        // Add maintenance button
        const addButton = document.getElementById('add-maintenance-btn');
        if (addButton) {
            addButton.addEventListener('click', () => this.openModal());
        }
        
        // Maintenance form submission
        const maintenanceForm = document.getElementById('maintenance-form');
        if (maintenanceForm) {
            maintenanceForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitMaintenanceForm();
            });
        }
        
        // Filter buttons
        const applyFiltersButton = document.getElementById('apply-filters');
        if (applyFiltersButton) {
            applyFiltersButton.addEventListener('click', () => this.applyFilters());
        }
        
        // Reset filter button
        const resetFiltersButton = document.getElementById('reset-filters');
        if (resetFiltersButton) {
            resetFiltersButton.addEventListener('click', () => this.resetFilters());
        }
        
        // Confirm complete button
        const confirmCompleteButton = document.getElementById('confirm-complete-btn');
        if (confirmCompleteButton) {
            confirmCompleteButton.addEventListener('click', () => this.completeMaintenance());
        }
    },
    
    /**
     * Load maintenance record data
     */
    loadMaintenanceData: function() {
        // Show loading state
        const tableBody = document.getElementById('maintenance-data');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="6" class="loading-message">Loading maintenance records...</td></tr>';
        }
        
        // Send API request to get maintenance records
        fetch('/api/maintenance')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.maintenanceData = data.data || [];
                    this.renderMaintenanceData();
                } else {
                    this.showNotification('Failed to get maintenance records: ' + (data.message || 'Unknown error'), 'error');
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="6" class="error-message">Loading failed</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('API request error:', error);
                this.showNotification('Failed to get maintenance records: ' + error.message, 'error');
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="error-message">Loading failed</td></tr>';
                }
            });
    },
    
    /**
     * Render maintenance record data
     */
    renderMaintenanceData: function() {
        const tableBody = document.getElementById('maintenance-data');
        if (!tableBody) return;
        
        // Apply filters
        const filteredData = this.applyDataFilters(this.maintenanceData);
        
        if (filteredData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="empty-message">No maintenance records found</td></tr>';
            return;
        }
        
        // Generate table rows
        let html = '';
        filteredData.forEach(record => {
            const isCompleted = record.completed_at !== null;
            const status = isCompleted ? 'Completed' : 'Pending';
            const statusClass = isCompleted ? 'completed' : 'pending';
            const formattedDate = new Date(record.maintenance_date).toLocaleDateString('en-US');
            
            html += `
                <tr data-id="${record.id}" class="maintenance-row ${statusClass}">
                    <td>${record.id}</td>
                    <td>${record.vehicle_id}</td>
                    <td>${formattedDate}</td>
                    <td>${record.description}</td>
                    <td class="status-${statusClass}">${status}</td>
                    <td class="actions">
                        ${isCompleted ? '' : '<button class="action-btn complete-btn" data-id="' + record.id + '">Complete</button>'}
                    </td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = html;
        
        // Add complete button event listeners
        const completeButtons = document.querySelectorAll('.complete-btn');
        completeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const maintenanceId = e.target.getAttribute('data-id');
                this.openConfirmModal(maintenanceId);
            });
        });
    },
    
    /**
     * Apply data filters
     * @param {Array} data - Original data
     * @returns {Array} - Filtered data
     */
    applyDataFilters: function(data) {
        return data.filter(record => {
            // Filter status
            if (this.activeFilters.status !== 'all') {
                const isCompleted = record.completed_at !== null;
                if (
                    (this.activeFilters.status === 'completed' && !isCompleted) ||
                    (this.activeFilters.status === 'pending' && isCompleted)
                ) {
                    return false;
                }
            }
            
            // Filter date
            if (this.activeFilters.date) {
                const filterDate = new Date(this.activeFilters.date).toDateString();
                const recordDate = new Date(record.maintenance_date).toDateString();
                if (filterDate !== recordDate) {
                    return false;
                }
            }
            
            return true;
        });
    },
    
    /**
     * Apply filters
     */
    applyFilters: function() {
        const statusFilter = document.getElementById('status-filter');
        const dateFilter = document.getElementById('date-filter');
        
        this.activeFilters = {
            status: statusFilter?.value || 'all',
            date: dateFilter?.value || ''
        };
        
        this.renderMaintenanceData();
    },
    
    /**
     * Reset filters
     */
    resetFilters: function() {
        const statusFilter = document.getElementById('status-filter');
        const dateFilter = document.getElementById('date-filter');
        
        if (statusFilter) statusFilter.value = 'all';
        if (dateFilter) dateFilter.value = '';
        
        this.activeFilters = {
            status: 'all',
            date: ''
        };
        
        this.renderMaintenanceData();
    },
    
    /**
     * Open add maintenance modal
     */
    openModal: function() {
        const modal = document.getElementById('maintenance-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    },
    
    /**
     * Close add maintenance modal
     */
    closeModal: function() {
        const modal = document.getElementById('maintenance-modal');
        if (modal) {
            modal.style.display = 'none';
            
            // Reset form
            const form = document.getElementById('maintenance-form');
            if (form) form.reset();
        }
    },
    
    /**
     * Open confirm complete modal
     * @param {number} maintenanceId - Maintenance record ID
     */
    openConfirmModal: function(maintenanceId) {
        this.selectedMaintenanceId = maintenanceId;
        
        const modal = document.getElementById('complete-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    },
    
    /**
     * Close confirm complete modal
     */
    closeConfirmModal: function() {
        this.selectedMaintenanceId = null;
        
        const modal = document.getElementById('complete-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },
    
    /**
     * Submit maintenance form
     */
    submitMaintenanceForm: function() {
        const vehicleId = document.getElementById('vehicle-id')?.value;
        const maintenanceDate = document.getElementById('maintenance-date')?.value;
        const description = document.getElementById('description')?.value;
        
        if (!vehicleId || !maintenanceDate || !description) {
            this.showNotification('Please fill in all required fields', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('vehicle_id', vehicleId);
        formData.append('maintenance_date', maintenanceDate);
        formData.append('description', description);
        
        // Send request
        fetch('/api/maintenance', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.showNotification('Maintenance record added successfully', 'success');
                    this.closeModal();
                    this.loadMaintenanceData(); // Reload data
                } else {
                    this.showNotification('Failed to add maintenance record: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('API request error:', error);
                this.showNotification('Failed to add maintenance record: ' + error.message, 'error');
            });
    },
    
    /**
     * Complete maintenance
     */
    completeMaintenance: function() {
        if (!this.selectedMaintenanceId) return;
        
        // Send complete maintenance request
        fetch(`/api/maintenance/${this.selectedMaintenanceId}/complete`, {
            method: 'POST'
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.showNotification('Maintenance record marked as completed', 'success');
                    this.closeConfirmModal();
                    this.loadMaintenanceData(); // Reload data
                } else {
                    this.showNotification('Failed to complete maintenance: ' + (data.message || 'Unknown error'), 'error');
                    this.closeConfirmModal();
                }
            })
            .catch(error => {
                console.error('API request error:', error);
                this.showNotification('Failed to complete maintenance: ' + error.message, 'error');
                this.closeConfirmModal();
            });
    },
    
    /**
     * Show notification message
     * @param {string} message - Message content
     * @param {string} type - Message type (success/error/info)
     */
    showNotification: function(message, type = 'info') {
        const notification = document.getElementById('notification');
        if (!notification) return;
        
        // Set notification content and type
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.remove('hidden');
        
        // Auto-hide notification
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 5000);
    }
}; 