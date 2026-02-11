<?php
/**
 * Admin Dashboard
 */

require_once '../backend/includes/session.php';
SessionManager::requireAdmin();

// Include database connection
require_once '../backend/config/database.php';
$db = new Database();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FoodExpress</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-utensils"></i> FoodExpress</h2>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="manage-food.php">
                    <i class="fas fa-utensils"></i> Manage Food Items
                </a>
                <a href="manage-orders.php">
                    <i class="fas fa-shopping-cart"></i> Manage Orders
                </a>
                <a href="manage-users.php">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="#" id="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
                </div>
                <div class="header-right">
                    <div class="admin-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4CAF50;">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-orders">0</h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2196F3;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-users">0</h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #FF9800;">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-food">0</h3>
                        <p>Food Items</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #9C27B0;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-revenue">$0</h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>
            <!-- Recent Orders -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Orders</h2>
                    <a href="manage-orders.php" class="btn btn-outline">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="recent-orders">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Orders will be loaded dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Users</h2>
                    <a href="manage-users.php" class="btn btn-outline">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="recent-users">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Joined Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Users will be loaded dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
            loadRecentOrders();
            loadRecentUsers();
            
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function(e) {
                e.preventDefault();
                fetch('../backend/api/auth.php?action=logout', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '../frontend/index.html';
                    }
                });
            });
        });
        
        function loadDashboardStats() {
            fetch('../backend/api/admin.php?action=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('total-orders').textContent = data.data.total_orders;
                        document.getElementById('total-users').textContent = data.data.total_users;
                        document.getElementById('total-food').textContent = data.data.total_food_items;
                        document.getElementById('total-revenue').textContent = '$' + parseFloat(data.data.total_revenue).toFixed(2);
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }
        
        function loadRecentOrders() {
            fetch('../backend/api/admin.php?action=recent_orders')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.querySelector('#recent-orders tbody');
                        tbody.innerHTML = data.data.map(order => `
                            <tr>
                                <td>#ORD${order.order_id.toString().padStart(6, '0')}
                                </td>
                                <td>${order.full_name}</td>
                                <td>${new Date(order.order_date).toLocaleDateString()}</td>
                                <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                                <td>
                                    <span class="status-badge status-${order.status}">
                                        ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                    </span>
                                </td>
                                <td>
                                    <a href="manage-orders.php?view=${order.order_id}" class="btn btn-sm btn-outline">
                                        View
                                    </a>
                                </td>
                            </tr>
                        `).join('');
                    }
                })
                .catch(error => console.error('Error loading recent orders:', error));
        }
        
        function loadRecentUsers() {
            fetch('../backend/api/admin.php?action=recent_users')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.querySelector('#recent-users tbody');
                        tbody.innerHTML = data.data.map(user => `
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
                })
                .catch(error => console.error('Error loading recent users:', error));
        }
    </script>
</body>
</html>