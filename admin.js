/**
 * Admin JavaScript Functions
 */

// Global variables
let currentPage = 1;
let totalPages = 1;
let currentFilter = {};
let currentTab = 'dashboard';

// Initialize admin dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeAdminDashboard();
    
    // Check if user is admin, redirect if not
    checkAdminAccess();
    
    // Initialize mobile menu toggle
    initializeMobileMenu();
    
    // Load dashboard data
    loadDashboardData();
    
    // Set up event listeners
    setupEventListeners();
});

function checkAdminAccess() {
    fetch('../backend/api/auth.php?action=check')
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.data || data.data.role_id !== 1) {
                // Not an admin, redirect to home
                window.location.href = '../index.php';
            }
        })
        .catch(error => {
            console.error('Error checking admin access:', error);
            window.location.href = '../index.php';
        });
}

function initializeAdminDashboard() {
    // Set active sidebar item based on current page
    const currentPage = window.location.pathname.split('/').pop();
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });
}

function initializeMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
}

function setupEventListeners() {
    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutAdmin();
        });
    }
    
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            currentFilter.search = this.value;
            currentPage = 1;
            loadData();
        }, 300));
    }
    
    // Filter select
    const filterSelect = document.getElementById('filter-select');
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            currentFilter.status = this.value;
            currentPage = 1;
            loadData();
        });
    }
    
    // Pagination buttons
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadData();
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                loadData();
            }
        });
    }
    
    // Refresh button
    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            loadData();
        });
    }
    
    // Modal close buttons
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Form submissions
    const forms = document.querySelectorAll('.admin-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit(this);
        });
    });
    
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(tab => {
        tab.addEventListener('click', function() {
            switchTab(this.dataset.tab);
        });
    });
}

function loadDashboardData() {
    // Load stats
    loadStats();
    
    // Load recent orders
    loadRecentOrders();
    
    // Load recent users
    loadRecentUsers();
    
    // Load charts if on reports page
    if (window.location.pathname.includes('reports.php')) {
        loadSalesChart();
        loadTopItemsChart();
    }
}

function loadStats() {
    fetch('../backend/api/admin.php?action=stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatsDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
            showNotification('Failed to load statistics', 'error');
        });
}

function updateStatsDisplay(stats) {
    // Update total orders
    const totalOrders = document.getElementById('total-orders');
    if (totalOrders) totalOrders.textContent = stats.total_orders || 0;
    
    // Update total users
    const totalUsers = document.getElementById('total-users');
    if (totalUsers) totalUsers.textContent = stats.total_users || 0;
    
    // Update total food items
    const totalFood = document.getElementById('total-food');
    if (totalFood) totalFood.textContent = stats.total_food_items || 0;
    
    // Update total revenue
    const totalRevenue = document.getElementById('total-revenue');
    if (totalRevenue) totalRevenue.textContent = '$' + (parseFloat(stats.total_revenue) || 0).toFixed(2);
    
    // Update today's revenue
    const todayRevenue = document.getElementById('today-revenue');
    if (todayRevenue) todayRevenue.textContent = '$' + (parseFloat(stats.today_revenue) || 0).toFixed(2);
    
    // Update today's orders
    const todayOrders = document.getElementById('today-orders');
    if (todayOrders) todayOrders.textContent = stats.today_orders || 0;
    
    // Update last 7 days stats
    const weekOrders = document.getElementById('week-orders');
    if (weekOrders) weekOrders.textContent = stats.orders_last_7_days || 0;
    
    const weekRevenue = document.getElementById('week-revenue');
    if (weekRevenue) weekRevenue.textContent = '$' + (parseFloat(stats.revenue_last_7_days) || 0).toFixed(2);
}

function loadRecentOrders() {
    fetch('../backend/api/admin.php?action=recent_orders&limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRecentOrdersTable(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading recent orders:', error);
        });
}

function updateRecentOrdersTable(orders) {
    const tbody = document.getElementById('recent-orders-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = orders.map(order => `
        <tr>
            <td>#${order.order_id.toString().padStart(6, '0')}</td>
            <td>${order.full_name || order.username}</td>
            <td>${new Date(order.order_date).toLocaleDateString()}</td>
            <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
            <td>
                <span class="status-badge status-${order.status}">
                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="viewOrderDetails(${order.order_id})">
                <i class="fas fa-eye"></i> View
                </button>
            </td>
        </tr>
    `).join('');
}

function loadRecentUsers() {
    fetch('../backend/api/admin.php?action=recent_users&limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRecentUsersTable(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading recent users:', error);
        });
}

function updateRecentUsersTable(users) {
    const tbody = document.getElementById('recent-users-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>#${user.user_id.toString().padStart(4, '0')}</td>
            <td>${user.username}</td>
            <td>${user.email}</td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>
                <span class="status-badge ${user.is_active ? 'status-active' : 'status-inactive'}">
                    ${user.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
        </tr>
    `).join('');
}

function loadData() {
    const page = window.location.pathname.split('/').pop();
    
    switch(page) {
        case 'manage-users.php':
            loadUsers();
            break;
        case 'manage-orders.php':
            loadOrders();
            break;
        case 'manage-food.php':
            loadFoodItems();
            break;
        case 'reports.php':
            loadReports();
            break;
        default:
            loadDashboardData();
    }
}

function loadUsers() {
    showLoading('#users-table tbody');
    
    let url= `../backend/api/admin.php?action=users&page=${currentPage}&limit=10`;
    if (currentFilter.search) url += `&search=${encodeURIComponent(currentFilter.search)}`;
    if (currentFilter.status) url += `&status=${currentFilter.status}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUsersTable(data.data);
                updatePagination(data.data.pagination);
            } else {
                showEmptyState('#users-table tbody', 'No users found');
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
            showEmptyState('#users-table tbody', 'Failed to load users');
        });
}

function updateUsersTable(data) {
    const tbody = document.querySelector('#users-table tbody');
    if (!tbody) return;
    
    const users = data.users || [];
    
    if (users.length === 0) {
        showEmptyState('#users-table tbody', 'No users found');
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>#${user.user_id.toString().padStart(4, '0')}</td>
            <td>
                <div class="user-info">
                    <strong>${user.username}</strong>
                    <small>${user.email}</small>
                </div>
            </td>
            <td>${user.full_name}</td>
            <td>${user.phone || 'N/A'}</td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>
                <span class="status-badge ${user.is_active ? 'status-active' : 'status-inactive'}">
                    ${user.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="editUser(${user.user_id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.user_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function loadOrders() {
    showLoading('#orders-table tbody');
    
    let url = `../backend/api/orders.php?page=${currentPage}&limit=10`;
    if (currentFilter.search) url += `&search=${encodeURIComponent(currentFilter.search)}`;
    if (currentFilter.status) url += `&status=${currentFilter.status}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateOrdersTable(data.data);
                updatePagination(data.data.pagination);
            } else {
                showEmptyState('#orders-table tbody', 'No orders found');
            }
        })
        .catch(error => {
            console.error('Error loading orders:', error);
            showEmptyState('#orders-table tbody', 'Failed to load orders');
        });
}

function updateOrdersTable(data) {
    const tbody = document.querySelector('#orders-table tbody');
    if (!tbody) return;
    
    const orders = data.orders || [];
    
    if (orders.length === 0) {
        showEmptyState('#orders-table tbody', 'No orders found');
        return;
    }
    
    tbody.innerHTML = orders.map(order => `
        <tr>
            <td>#${order.order_id.toString().padStart(6, '0')}</td>
            <td>
                <div class="user-info">
                    <strong>${order.full_name || order.username}</strong>
                    <small>${order.email}</small>
                </div>
            </td>
            <td>${new Date(order.order_date).toLocaleDateString()}</td>
            <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
            <td>
                <span class="status-badge status-${order.status}">
                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="viewOrderDetails(${order.order_id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="updateOrderStatus(${order.order_id})">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function loadFoodItems() {
    showLoading('#food-table tbody');
    
    let url = `../backend/api/food.php?page=${currentPage}&limit=10`;
    if (currentFilter.search) url += `&search=${encodeURIComponent(currentFilter.search)}`;
    if (currentFilter.category) url += `&category=${currentFilter.category}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFoodTable(data.data);
                updatePagination(data.data.pagination);
            } else {
                showEmptyState('#food-table tbody', 'No food items found');
            }
        })
        .catch(error => {
            console.error('Error loading food items:', error);
            showEmptyState('#food-table tbody', 'Failed to load food items');
        });
}
function updateFoodTable(data) {
    const tbody = document.querySelector('#food-table tbody');
    if (!tbody) return;
    
    const items = data.food_items || data.items || data;
    
    if (items.length === 0) {
        showEmptyState('#food-table tbody', 'No food items found');
        return;
    }
    
    tbody.innerHTML = items.map(item => `
        <tr>
            <td>
                <div class="food-item">
                    <img src="${item.image_url || '../assets/images/default-food.jpg'}" 
                         alt="${item.food_name}"
                         onerror="this.src='../assets/images/default-food.jpg'">
                    <div class="food-info">
                        <strong>${item.food_name}</strong>
                        <small>${item.description || 'No description'}</small>
                    </div>
                </div>
            </td>
            <td>${item.category_name || 'N/A'}</td>
            <td>$${parseFloat(item.price).toFixed(2)}</td>
            <td>
                <span class="status-badge ${item.is_available ? 'status-active' : 'status-inactive'}">
                    ${item.is_available ? 'Available' : 'Unavailable'}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="editFoodItem(${item.food_id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteFoodItem(${item.food_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function loadReports() {
    // Load sales report
    fetch('../backend/api/admin.php?action=sales_report&period=month')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSalesReport(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading sales report:', error);
        });
    
    // Load categories
    loadCategoriesForFilter();
}

function updateSalesReport(report) {
    // Update summary cards
    const summary = report.summary || {};
    
    const totalOrders = document.getElementById('report-total-orders');
    if (totalOrders) totalOrders.textContent = summary.total_orders || 0;
    
    const totalRevenue = document.getElementById('report-total-revenue');
    if (totalRevenue) totalRevenue.textContent = '$' + (parseFloat(summary.total_revenue) || 0).toFixed(2);
    
    const avgOrderValue = document.getElementById('report-avg-order');
    if (avgOrderValue) avgOrderValue.textContent = '$' + (parseFloat(summary.avg_order_value) || 0).toFixed(2);
    
    const uniqueCustomers = document.getElementById('report-unique-customers');
    if (uniqueCustomers) uniqueCustomers.textContent = summary.unique_customers || 0;
    
    // Update top items table
    const topItemsTbody = document.getElementById('top-items-tbody');
    if (topItemsTbody) {
        const topItems = report.top_items || [];
        topItemsTbody.innerHTML = topItems.map((item, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${item.food_name}</td>
                <td>${item.total_sold}</td>
                <td>$${parseFloat(item.total_revenue).toFixed(2)}</td>
            </tr>
        `).join('');
    }
    
    // Update chart if chart.js is available
    if (typeof Chart !== 'undefined') {
        updateSalesChart(report.daily_sales || []);
    }
}

function loadSalesChart() {
    // This function initializes the sales chart
    // Requires Chart.js library to be included
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded');
        return;
    }
    
    const ctx = document.getElementById('sales-chart');
    if (!ctx) return;
    
    window.salesChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Daily Revenue',
                data: [],
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            }
        }
    });
}

function updateSalesChart(dailySales) {
    if (!window.salesChart) return;
    
    const labels = dailySales.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const data = dailySales.map(item => parseFloat(item.daily_revenue) || 0);
    
    window.salesChart.data.labels = labels;
    window.salesChart.data.datasets[0].data = data;
    window.salesChart.update();
}

function loadTopItemsChart() {
    // This function initializes the top items chart
    if (typeof Chart === 'undefined') return;
    
    const ctx = document.getElementById('top-items-chart');
    if (!ctx) return;
    
    window.topItemsChart = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Units Sold',
                data: [],
                backgroundColor: '#27ae60',
                borderColor: '#229954',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateTopItemsChart(topItems) {
    if (!window.topItemsChart) return;
    
    const labels = topItems.slice(0, 10).map(item => item.food_name);
    const data = topItems.slice(0, 10).map(item => item.total_sold || 0);
    
    window.topItemsChart.data.labels = labels;
    window.topItemsChart.data.datasets[0].data = data;
    window.topItemsChart.update();
}

function loadCategoriesForFilter() {
    fetch('../backend/api/admin.php?action=categories')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCategoryFilter(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
        });
}

function updateCategoryFilter(categories) {
    const filterSelect = document.getElementById('category-filter');
    if (!filterSelect) return;
    
    filterSelect.innerHTML = `
        <option value="">All Categories</option>
        ${categories.map(category => `
            <option value="${category.category_id}">${category.category_name}</option>
        `).join('')}
    `;
    
    filterSelect.addEventListener('change', function() {
        currentFilter.category = this.value;
        currentPage = 1;
        loadData();
    });
}
// Utility Functions
function showLoading(selector) {
    const element = document.querySelector(selector);
    if (element) {
        element.innerHTML = `
            <tr>
                <td colspan="100" class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </td>
            </tr>
        `;
    }
}

function showEmptyState(selector, message) {
    const element = document.querySelector(selector);
    if (element) {
        element.innerHTML = `
            <tr>
                <td colspan="100" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>${message}</p>
                </td>
            </tr>
        `;
    }
    
    // Hide pagination
    const pagination = document.getElementById('pagination');
    if (pagination) {
        pagination.style.display = 'none';
    }
}

function updatePagination(pagination) {
    const paginationDiv = document.getElementById('pagination');
    if (!paginationDiv) return;
    
    currentPage = pagination.current_page || 1;
    totalPages = pagination.total_pages || 1;
    
    const pageInfo = document.getElementById('page-info');
    if (pageInfo) {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }
    
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    
    if (prevBtn) prevBtn.disabled = currentPage <= 1;
    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
    
    paginationDiv.style.display = totalPages > 1 ? 'flex' : 'none';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Modal Functions
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}
// Notification System
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="close-notification">&times;</button>
    `;
    
    // Add styles if not already added
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                display: flex;
                justify-content: space-between;
                align-items: center;
                min-width: 300px;
                max-width: 500px;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            .notification-info { 
                background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            }
            .notification-success { 
                background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            }
            .notification-warning { 
                background: linear-gradient(135deg, #f39c12 0%, #d68910 100%);
            }
            .notification-error { 
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            }
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .close-notification {
                background: none;
                border: none;
                color: white;
                font-size: 1.2rem;
                cursor: pointer;
                margin-left: 15px;
                padding: 0;
                line-height: 1;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(styles);
    }
    document.body.appendChild(notification);
    
    // Close notification button
    notification.querySelector('.close-notification').addEventListener('click', function() {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    });
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        'info': 'info-circle',
        'success': 'check-circle',
        'warning': 'exclamation-triangle',
        'error': 'exclamation-circle'
    };
    return icons[type] || 'info-circle';
}

// CRUD Operations
function editUser(userId) {
    fetch(`../backend/api/admin.php?action=user&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showUserEditModal(data.data);
            } else {
                showNotification(data.message || 'Failed to load user data', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading user:', error);
            showNotification('Failed to load user data', 'error');
        });
}

function showUserEditModal(user) {
    const modalBody = document.getElementById('edit-user-modal-body');
    if (!modalBody) return;
    
    modalBody.innerHTML = `
        <form id="edit-user-form" class="admin-form">
            <input type="hidden" name="user_id" value="${user.user_id}">
            
            <div class="form-group">
                <label for="edit-username">Username</label>
                <input type="text" id="edit-username" name="username" class="form-control" 
                       value="${user.username}" required>
            </div>
            
            <div class="form-group">
                <label for="edit-email">Email</label>
                <input type="email" id="edit-email" name="email" class="form-control" 
                       value="${user.email}" required>
            </div>
            
            <div class="form-group">
                <label for="edit-full_name">Full Name</label>
                <input type="text" id="edit-full_name" name="full_name" class="form-control" 
                       value="${user.full_name}" required>
            </div>
            
            <div class="form-group">
                <label for="edit-phone">Phone</label>
                <input type="tel" id="edit-phone" name="phone" class="form-control" 
                       value="${user.phone || ''}">
            </div>
            
            <div class="form-group">
                <label for="edit-address">Address</label>
                <textarea id="edit-address" name="address" class="form-control" rows="3">${user.address || ''}</textarea>
            </div>
            
            <div class="form-group">
                <label for="edit-is_active">Status</label>
                <select id="edit-is_active" name="is_active" class="form-control">
                    <option value="1" ${user.is_active ? 'selected' : ''}>Active</option>
                    <option value="0" ${!user.is_active ? 'selected' : ''}>Inactive</option>
                </select>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Update User</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('edit-user-modal')">Cancel</button>
            </div>
        </form>
    `;
    
    // Add form submit handler
    const form = document.getElementById('edit-user-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            updateUserData(this);
        });
    }
    
    showModal('edit-user-modal');
}

function updateUserData(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    fetch('../backend/api/admin.php?action=update_user', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('User updated successfully', 'success');
            closeModal('edit-user-modal');
            loadData();
        } else {
            showNotification(data.message || 'Failed to update user', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating user:', error);
        showNotification('Failed to update user', 'error');
    });
}

function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    fetch(`../backend/api/admin.php?action=delete_user&user_id=${userId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('User deleted successfully', 'success');
            loadData();
        } else {
            showNotification(data.message || 'Failed to delete user', 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
        showNotification('Failed to delete user', 'error');
    });
}

function editFoodItem(foodId) {
    // Similar to editUser, but for food items
    showNotification('Edit food item functionality would be implemented here', 'info');
}

function deleteFoodItem(foodId) {
    if (!confirm('Are you sure you want to delete this food item?')) {
        return;
    }
    
    fetch(`../backend/api/admin.php?action=delete_food&food_id=${foodId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Food item deleted successfully', 'success');
            loadData();
        } else {
            showNotification(data.message || 'Failed to delete food item', 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting food item:', error);
        showNotification('Failed to delete food item', 'error');
    });
}
function viewOrderDetails(orderId) {
    fetch(`../backend/api/orders.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showOrderDetailsModal(data.data);
            } else {
                showNotification(data.message || 'Failed to load order details', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading order details:', error);
            showNotification('Failed to load order details', 'error');
        });
}

function showOrderDetailsModal(order) {
    const modalBody = document.getElementById('order-details-modal-body');
    if (!modalBody) return;
    
    const statusClass = `status-${order.status}`;
    const statusText = order.status.charAt(0).toUpperCase() + order.status.slice(1);
    
    modalBody.innerHTML = `
        <div class="order-details">
            <div class="order-header">
                <h4>Order #${order.order_id.toString().padStart(6, '0')}</h4>
                <span class="status-badge ${statusClass}">${statusText}</span>
            </div>
            
            <div class="order-info">
                <p><strong>Customer:</strong> ${order.full_name || order.username}</p>
                <p><strong>Order Date:</strong> ${new Date(order.order_date).toLocaleString()}</p>
                <p><strong>Delivery Address:</strong> ${order.delivery_address}</p>
                <p><strong>Contact:</strong> ${order.contact_number}</p>
                <p><strong>Payment Method:</strong> ${order.payment_method === 'cash_on_delivery' ? 'Cash on Delivery' : 'Online Payment'}</p>
                <p><strong>Payment Status:</strong> ${order.payment_status === 'paid' ? 'Paid' : 'Pending'}</p>
                <p><strong>Special Instructions:</strong> ${order.special_instructions || 'None'}</p>
            </div>
            
            <div class="order-items">
                <h5>Order Items</h5>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(order.items || []).map(item => `
                            <tr>
                                <td>${item.food_name}</td>
                                <td>${item.quantity}</td>
                                <td>$${parseFloat(item.price).toFixed(2)}</td>
                                <td>$${(item.price * item.quantity).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="order-total">
                <p><strong>Total Amount:</strong> $${parseFloat(order.total_amount).toFixed(2)}</p>
            </div>
            
            <div class="order-actions">
                <div class="form-group">
                    <label for="update-order-status">Update Status</label>
                    <select id="update-order-status" class="form-control">
                        <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                        <option value="preparing" ${order.status === 'preparing' ? 'selected' : ''}>Preparing</option>
                        <option value="out_for_delivery" ${order.status === 'out_for_delivery' ? 'selected' : ''}>Out for Delivery</option>
                        <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                        <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="saveOrderStatus(${order.order_id})">Update Status</button>
            </div>
        </div>
    `;
    
    showModal('order-details-modal');
}

function saveOrderStatus(orderId) {
    const status = document.getElementById('update-order-status').value;
    
    fetch('../backend/api/orders.php?action=update_status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ order_id: orderId, status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Order status updated successfully', 'success');
            closeModal('order-details-modal');
            loadData();
        } else {
            showNotification(data.message || 'Failed to update order status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating order status:', error);
        showNotification('Failed to update order status', 'error');
    });
}

function updateOrderStatus(orderId) {
    // Quick status update - shows a simple modal for quick status change
    const modalBody = document.getElementById('quick-update-modal-body');
    if (!modalBody) return;
    
    modalBody.innerHTML = `
        <form id="quick-status-form" class="admin-form">
            <input type="hidden" name="order_id" value="${orderId}">
            
            <div class="form-group">
                <label for="quick-status">Update Status</label>
                <select id="quick-status" name="status" class="form-control">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="preparing">Preparing</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('quick-update-modal')">Cancel</button>
            </div>
        </form>
    `;
    
    const form = document.getElementById('quick-status-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('../backend/api/orders.php?action=update_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Order status updated successfully', 'success');
                    closeModal('quick-update-modal');
                    loadData();
                } else {
                    showNotification(data.message || 'Failed to update order status', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating order status:', error);
                showNotification('Failed to update order status', 'error');
            });
        });
    }
    
    showModal('quick-update-modal');
}
function logoutAdmin() {
    fetch('../backend/api/auth.php?action=logout', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '../index.php';
        }
    })
    .catch(error => {
        console.error('Error logging out:', error);
        window.location.href = '../index.php';
    });
}

function switchTab(tabName) {
    currentTab = tabName;
    
    // Update active tab button
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active');
        }
    });
    
    // Show/hide tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = content.id === `${tabName}-tab` ? 'block' : 'none';
    });
    
    // Load data for the tab
    switch(tabName) {
        case 'overview':
            loadDashboardData();
            break;
        case 'orders':
            loadOrders();
            break;
        case 'users':
            loadUsers();
            break;
        case 'food':
            loadFoodItems();
            break;
        case 'reports':
            loadReports();
            break;
    }
}

function handleFormSubmit(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    const action = form.dataset.action;
    
    if (!action) {
        showNotification('Form action not specified', 'error');
        return;
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;
    
    fetch(`../backend/api/admin.php?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Operation successful', 'success');
            form.reset();
            
            // Reload data if needed
            if (form.dataset.reload !== 'false') {
                loadData();
            }
            
            // Close modal if form is in a modal
            const modal = form.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        } else {
            showNotification(data.message || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting form:', error);
        showNotification('An error occurred', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Export functions for use in HTML
window.editUser = editUser;
window.deleteUser = deleteUser;
window.editFoodItem = editFoodItem;
window.deleteFoodItem = deleteFoodItem;
window.viewOrderDetails = viewOrderDetails;
window.updateOrderStatus = updateOrderStatus;
window.saveOrderStatus = saveOrderStatus;
window.closeModal = closeModal;