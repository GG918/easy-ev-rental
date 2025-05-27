/**
 * 位置服务 - 处理地图和车辆位置相关功能
 */
const LocationsService = {
    map: null,
    markers: [],
    vehicleData: [],
    selectedVehicle: null,
    userLocation: null,
    
    /**
     * 初始化地图
     */
    initMap: function() {
        // 初始默认位置 (上海市中心)
        const defaultLocation = [31.230416, 121.473701];
        
        // 创建地图
        this.map = L.map('map').setView(defaultLocation, 14);
        
        // 添加地图瓦片层
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);
        
        // 尝试获取用户位置
        this.getUserLocation();
        
        // 加载车辆数据
        this.loadVehicleData();
        
        // 设置地图事件监听器
        this.setupMapEventListeners();
    },
    
    /**
     * 获取用户位置
     */
    getUserLocation: function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                // 成功回调
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    this.userLocation = [lat, lng];
                    
                    // 移动地图到用户位置
                    this.map.setView(this.userLocation, 15);
                    
                    // 添加用户位置标记
                    L.marker(this.userLocation, {
                        icon: L.divIcon({
                            className: 'user-marker',
                            html: '<div class="user-marker-icon"></div>',
                            iconSize: [20, 20]
                        })
                    }).addTo(this.map)
                      .bindPopup('<b>您的位置</b>');
                },
                // 错误回调
                (error) => {
                    console.error('获取位置失败:', error.message);
                },
                // 选项
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
        } else {
            console.error('浏览器不支持地理位置');
        }
    },
    
    /**
     * 加载车辆数据
     */
    loadVehicleData: function() {
        // 发送请求获取车辆数据
        fetch('/api/locations')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data) {
                    this.vehicleData = data.data;
                    this.displayVehicles(this.vehicleData);
                }
            })
            .catch(error => {
                console.error('加载车辆数据失败:', error);
            });
    },
    
    /**
     * 在地图上显示车辆
     * @param {Array} vehicles - 车辆数据
     */
    displayVehicles: function(vehicles) {
        // 清除现有标记
        this.clearMarkers();
        
        // 添加新标记
        vehicles.forEach(vehicle => {
            if (vehicle.latitude && vehicle.longitude) {
                const marker = this.createVehicleMarker(vehicle);
                this.markers.push(marker);
            }
        });
    },
    
    /**
     * 创建车辆标记
     * @param {Object} vehicle - 车辆数据
     * @returns {Object} - Leaflet标记对象
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
     * 创建车辆弹出窗口内容
     * @param {Object} vehicle - 车辆数据
     * @returns {String} - HTML内容
     */
    createVehiclePopup: function(vehicle) {
        const batteryClass = this.getBatteryClass(vehicle.battery_level);
        const statusText = this.getStatusText(vehicle.status);
        
        return `
            <div class="vehicle-popup">
                <h3>车辆 #${vehicle.id}</h3>
                <div class="vehicle-details">
                    <p><strong>状态:</strong> <span class="status-${vehicle.status}">${statusText}</span></p>
                    <p><strong>电池:</strong> <span class="battery ${batteryClass}">${vehicle.battery_level || 0}%</span></p>
                </div>
                <button class="book-btn" onclick="LocationsService.openBookingPanel(${vehicle.id})">预订车辆</button>
            </div>
        `;
    },
    
    /**
     * 根据电池电量获取CSS类
     * @param {Number} level - 电池电量
     * @returns {String} - CSS类名
     */
    getBatteryClass: function(level) {
        if (level >= 70) return 'high';
        if (level >= 30) return 'medium';
        return 'low';
    },
    
    /**
     * 获取状态文本
     * @param {String} status - 状态代码
     * @returns {String} - 状态文本
     */
    getStatusText: function(status) {
        const statusMap = {
            'available': '可用',
            'reserved': '已预订',
            'in_use': '使用中',
            'maintenance': '维护中',
            'offline': '离线'
        };
        
        return statusMap[status] || status;
    },
    
    /**
     * 清除所有标记
     */
    clearMarkers: function() {
        this.markers.forEach(marker => {
            this.map.removeLayer(marker);
        });
        this.markers = [];
    },
    
    /**
     * 车辆选择处理
     * @param {Object} vehicle - 车辆数据
     * @param {Object} marker - 标记对象
     */
    onVehicleSelect: function(vehicle, marker) {
        this.selectedVehicle = vehicle;
    },
    
    /**
     * 打开预订面板
     * @param {Number} vehicleId - 车辆ID
     */
    openBookingPanel: function(vehicleId) {
        // 查找车辆数据
        const vehicle = this.vehicleData.find(v => v.id == vehicleId);
        if (!vehicle) return;
        
        // 更新预订面板数据
        document.getElementById('vehicle-id').textContent = vehicle.id;
        document.getElementById('vehicle-type-info').textContent = vehicle.type || '电动滑板车';
        document.getElementById('vehicle-status-info').textContent = this.getStatusText(vehicle.status);
        document.getElementById('vehicle-battery').textContent = vehicle.battery_level || 0;
        
        // 设置预订表单的车辆ID
        document.getElementById('booking-vehicle-id').value = vehicle.id;
        
        // 设置预订日期默认为今天
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('booking-date').value = today;
        document.getElementById('booking-date').min = today;
        
        // 生成时间选项
        this.generateTimeOptions();
        
        // 显示预订面板
        document.getElementById('booking-panel').classList.remove('hidden');
    },
    
    /**
     * 生成时间选项
     */
    generateTimeOptions: function() {
        const timeSelect = document.getElementById('booking-time');
        timeSelect.innerHTML = '';
        
        // 获取当前时间并向上取整到最近的半小时
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        let startHour = currentHour;
        let startMinute = currentMinute >= 30 ? 30 : 0;
        
        if (currentMinute >= 30) {
            startHour += 1;
            startMinute = 0;
        }
        
        // 生成时间选项，从当前时间到当天结束
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
     * 设置地图事件监听器
     */
    setupMapEventListeners: function() {
        // 预订表单提交
        const bookingForm = document.getElementById('booking-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submitBooking();
            });
        }
        
        // 关闭预订面板
        const closeButton = document.getElementById('close-booking-panel');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                document.getElementById('booking-panel').classList.add('hidden');
            });
        }
        
        // 应用筛选器
        const filterButton = document.getElementById('apply-filters');
        if (filterButton) {
            filterButton.addEventListener('click', () => {
                this.applyFilters();
            });
        }
    },
    
    /**
     * 提交预订
     */
    submitBooking: function() {
        // 检查用户是否已登录
        if (typeof AuthService !== 'undefined' && !AuthService.isAuthenticated()) {
            // 显示登录提示
            alert('请先登录后再预订车辆');
            // 重定向到登录页面
            window.location.href = '/view/index?show_login=1&return_url=/view/locations';
            return;
        }
        
        // 获取表单数据
        const vehicleId = document.getElementById('booking-vehicle-id').value;
        const bookingDate = document.getElementById('booking-date').value;
        const bookingTime = document.getElementById('booking-time').value;
        const bookingDuration = document.getElementById('booking-duration').value;
        
        // 计算开始和结束时间
        const startTime = `${bookingDate}T${bookingTime}:00`;
        const endDateTime = new Date(startTime);
        endDateTime.setHours(endDateTime.getHours() + parseInt(bookingDuration));
        const endTime = endDateTime.toISOString().slice(0, 16).replace('T', ' ');
        
        // 创建请求数据
        const bookingData = {
            vehicle_id: vehicleId,
            start_time: startTime.replace('T', ' '),
            end_time: endTime
        };
        
        // 发送预订请求
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
                alert('预订成功');
                // 隐藏预订面板
                document.getElementById('booking-panel').classList.add('hidden');
                // 重新加载车辆数据
                this.loadVehicleData();
                // 跳转到预订页面
                window.location.href = '/view/my_reservations';
            } else {
                alert('预订失败: ' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            console.error('预订请求失败:', error);
            alert('预订失败，请稍后重试');
        });
    },
    
    /**
     * 应用筛选器
     */
    applyFilters: function() {
        const vehicleType = document.getElementById('vehicle-type').value;
        const vehicleStatus = document.getElementById('vehicle-status').value;
        
        // 过滤车辆数据
        const filteredVehicles = this.vehicleData.filter(vehicle => {
            // 类型过滤
            if (vehicleType !== 'all' && vehicle.type !== vehicleType) {
                return false;
            }
            
            // 状态过滤
            if (vehicleStatus !== 'all' && vehicle.status !== vehicleStatus) {
                return false;
            }
            
            return true;
        });
        
        // 显示过滤后的车辆
        this.displayVehicles(filteredVehicles);
    }
};

// 页面加载完成后初始化地图
document.addEventListener('DOMContentLoaded', function() {
    LocationsService.initMap();
}); 