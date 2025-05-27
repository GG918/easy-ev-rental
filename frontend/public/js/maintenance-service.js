/**
 * 维护服务 - 处理维护记录相关功能
 */
const MaintenanceService = {
    maintenanceData: [],
    activeFilters: {
        status: 'all',
        date: ''
    },
    selectedMaintenanceId: null,
    
    /**
     * 初始化维护服务
     */
    init: function() {
        // 获取维护记录
        this.loadMaintenanceData();
        
        // 设置事件监听器
        this.setupEventListeners();
    },
    
    /**
     * 设置事件监听器
     */
    setupEventListeners: function() {
        // 添加维护按钮
        const addButton = document.getElementById('add-maintenance-btn');
        if (addButton) {
            addButton.addEventListener('click', () => this.openModal());
        }
        
        // 维护表单提交
        const maintenanceForm = document.getElementById('maintenance-form');
        if (maintenanceForm) {
            maintenanceForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitMaintenanceForm();
            });
        }
        
        // 筛选器按钮
        const applyFiltersButton = document.getElementById('apply-filters');
        if (applyFiltersButton) {
            applyFiltersButton.addEventListener('click', () => this.applyFilters());
        }
        
        // 重置筛选器按钮
        const resetFiltersButton = document.getElementById('reset-filters');
        if (resetFiltersButton) {
            resetFiltersButton.addEventListener('click', () => this.resetFilters());
        }
        
        // 确认完成按钮
        const confirmCompleteButton = document.getElementById('confirm-complete-btn');
        if (confirmCompleteButton) {
            confirmCompleteButton.addEventListener('click', () => this.completeMaintenance());
        }
    },
    
    /**
     * 加载维护记录数据
     */
    loadMaintenanceData: function() {
        // 显示加载状态
        const tableBody = document.getElementById('maintenance-data');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="6" class="loading-message">正在加载维护记录...</td></tr>';
        }
        
        // 发送API请求获取维护记录
        fetch('/api/maintenance')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.maintenanceData = data.data || [];
                    this.renderMaintenanceData();
                } else {
                    this.showNotification('获取维护记录失败: ' + (data.message || '未知错误'), 'error');
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="6" class="error-message">加载失败</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('API请求错误:', error);
                this.showNotification('获取维护记录失败: ' + error.message, 'error');
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="error-message">加载失败</td></tr>';
                }
            });
    },
    
    /**
     * 渲染维护记录数据
     */
    renderMaintenanceData: function() {
        const tableBody = document.getElementById('maintenance-data');
        if (!tableBody) return;
        
        // 应用筛选器
        const filteredData = this.applyDataFilters(this.maintenanceData);
        
        if (filteredData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="empty-message">没有找到维护记录</td></tr>';
            return;
        }
        
        // 生成表格行
        let html = '';
        filteredData.forEach(record => {
            const isCompleted = record.completed_at !== null;
            const status = isCompleted ? '已完成' : '待处理';
            const statusClass = isCompleted ? 'completed' : 'pending';
            const formattedDate = new Date(record.maintenance_date).toLocaleDateString('zh-CN');
            
            html += `
                <tr data-id="${record.id}" class="maintenance-row ${statusClass}">
                    <td>${record.id}</td>
                    <td>${record.vehicle_id}</td>
                    <td>${formattedDate}</td>
                    <td>${record.description}</td>
                    <td class="status-${statusClass}">${status}</td>
                    <td class="actions">
                        ${isCompleted ? '' : '<button class="action-btn complete-btn" data-id="' + record.id + '">完成</button>'}
                    </td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = html;
        
        // 添加完成按钮事件监听器
        const completeButtons = document.querySelectorAll('.complete-btn');
        completeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const maintenanceId = e.target.getAttribute('data-id');
                this.openConfirmModal(maintenanceId);
            });
        });
    },
    
    /**
     * 应用数据筛选器
     * @param {Array} data - 原始数据
     * @returns {Array} - 筛选后的数据
     */
    applyDataFilters: function(data) {
        return data.filter(record => {
            // 筛选状态
            if (this.activeFilters.status !== 'all') {
                const isCompleted = record.completed_at !== null;
                if (
                    (this.activeFilters.status === 'completed' && !isCompleted) ||
                    (this.activeFilters.status === 'pending' && isCompleted)
                ) {
                    return false;
                }
            }
            
            // 筛选日期
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
     * 应用筛选器
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
     * 重置筛选器
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
     * 打开添加维护模态框
     */
    openModal: function() {
        const modal = document.getElementById('maintenance-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    },
    
    /**
     * 关闭添加维护模态框
     */
    closeModal: function() {
        const modal = document.getElementById('maintenance-modal');
        if (modal) {
            modal.style.display = 'none';
            
            // 重置表单
            const form = document.getElementById('maintenance-form');
            if (form) form.reset();
        }
    },
    
    /**
     * 打开确认完成模态框
     * @param {number} maintenanceId - 维护记录ID
     */
    openConfirmModal: function(maintenanceId) {
        this.selectedMaintenanceId = maintenanceId;
        
        const modal = document.getElementById('complete-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    },
    
    /**
     * 关闭确认完成模态框
     */
    closeConfirmModal: function() {
        this.selectedMaintenanceId = null;
        
        const modal = document.getElementById('complete-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },
    
    /**
     * 提交维护表单
     */
    submitMaintenanceForm: function() {
        const vehicleId = document.getElementById('vehicle-id')?.value;
        const maintenanceDate = document.getElementById('maintenance-date')?.value;
        const description = document.getElementById('description')?.value;
        
        if (!vehicleId || !maintenanceDate || !description) {
            this.showNotification('请填写所有必填字段', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('vehicle_id', vehicleId);
        formData.append('maintenance_date', maintenanceDate);
        formData.append('description', description);
        
        // 发送请求
        fetch('/api/maintenance', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.showNotification('维护记录已成功添加', 'success');
                    this.closeModal();
                    this.loadMaintenanceData(); // 重新加载数据
                } else {
                    this.showNotification('添加维护记录失败: ' + (data.message || '未知错误'), 'error');
                }
            })
            .catch(error => {
                console.error('API请求错误:', error);
                this.showNotification('添加维护记录失败: ' + error.message, 'error');
            });
    },
    
    /**
     * 完成维护
     */
    completeMaintenance: function() {
        if (!this.selectedMaintenanceId) return;
        
        // 发送完成维护请求
        fetch(`/api/maintenance/${this.selectedMaintenanceId}/complete`, {
            method: 'POST'
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.showNotification('维护记录已标记为已完成', 'success');
                    this.closeConfirmModal();
                    this.loadMaintenanceData(); // 重新加载数据
                } else {
                    this.showNotification('完成维护失败: ' + (data.message || '未知错误'), 'error');
                    this.closeConfirmModal();
                }
            })
            .catch(error => {
                console.error('API请求错误:', error);
                this.showNotification('完成维护失败: ' + error.message, 'error');
                this.closeConfirmModal();
            });
    },
    
    /**
     * 显示通知消息
     * @param {string} message - 消息内容
     * @param {string} type - 消息类型 (success/error/info)
     */
    showNotification: function(message, type = 'info') {
        const notification = document.getElementById('notification');
        if (!notification) return;
        
        // 设置通知内容和类型
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.remove('hidden');
        
        // 自动隐藏通知
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 5000);
    }
}; 