<?php
/**
 * Admin - Manage Users
 */

require_once '../backend/includes/session.php';
SessionManager::requireAdmin();

// Include database connection
require_once '../backend/config/database.php';
$db = new Database();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$sql = "SELECT user_id, username, email, full_name, phone, address, 
               created_at, is_active 
        FROM users 
        WHERE role_id = 2";
        
$where_clauses = [];
$params = [];
$types = "";

if ($search) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if ($status === 'active') {
    $where_clauses[] = "is_active = 1";
} elseif ($status === 'inactive') {
    $where_clauses[] = "is_active = 0";
}

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users WHERE role_id = 2";
if (!empty($where_clauses)) {
    $count_sql .= " AND " . implode(" AND ", $where_clauses);
}

$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_count / $limit);

// Get users with pagination
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    // Get user order stats
    $order_sql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_spent
                  FROM orders 
                  WHERE user_id = ?";
    
    $order_stmt = $db->prepare($order_sql);
    $order_stmt->bind_param("i", $row['user_id']);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_stats = $order_result->fetch_assoc();
    
    $row['order_stats'] = $order_stats;
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - FoodExpress Admin</title>
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
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="manage-food.php">
                    <i class="fas fa-utensils"></i> Manage Food
                </a>
                <a href="manage-orders.php">
                    <i class="fas fa-shopping-cart"></i> Manage Orders
                </a>
                <a href="manage-users.php" class="active">
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
                    <h1>Manage Users</h1>
                    <p>View and manage all registered customers</p>
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
                    <div class="stat-icon" style="background-color: #3498db;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_count; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="active-users">0</h3>
                        <p>Active Today</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="new-users">0</h3>
                        <p>New This Week</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #9b59b6;">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="top-spender">$0</h3>
                        <p>Top Spender</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter-container">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search users by name, email, or username..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="status-filter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                </select>
                
                <button id="add-user-btn" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
                
                <button id="refresh-btn" class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <!-- Users Table -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Users List</h2>
                    <div class="pagination-info">
                        Showing <?php echo min($limit, count($users)); ?> of <?php echo $total_count; ?> users
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table" id="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Info</th>
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Joined Date</th>
                                <th>Orders</th>
                                <th>Spent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No users found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>#<?php echo str_pad($user['user_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                 <div class="user-info">
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo $user['order_stats']['total_orders'] ?? 0; ?></td>
                                    <td>$<?php echo number_format($user['order_stats']['total_spent'] ?? 0, 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['user_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination" id="pagination">
                    <button id="prev-page" class="btn btn-outline" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <span id="page-info" class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    <button id="next-page" class="btn btn-outline" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
            <div class="modal" id="add-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-user-form" class="admin-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add-username">Username *</label>
                            <input type="text" id="add-username" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="add-email">Email *</label>
                            <input type="email" id="add-email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add-password">Password *</label>
                            <input type="password" id="add-password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="add-confirm-password">Confirm Password *</label>
                            <input type="password" id="add-confirm-password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add-full_name">Full Name *</label>
                            <input type="text" id="add-full_name" name="full_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="add-phone">Phone</label>
                            <input type="tel" id="add-phone" name="phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="add-address">Address</label>
                        <textarea id="add-address" name="address" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="add-is_active">Status</label>
                        <select id="add-is_active" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Add User</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('add-user-modal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="edit-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="edit-user-modal-body">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal" id="view-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="view-user-modal-body">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize admin functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Load user statistics
            loadUserStats();
            
            // Setup event listeners
            document.getElementById('search-input').addEventListener('input', debounce(function() {
                const search = this.value;
                const status = document.getElementById('status-filter').value;
                updateURL({ search, status, page: 1 });
            }, 300));
            
            document.getElementById('status-filter').addEventListener('change', function() {
                const search = document.getElementById('search-input').value;
                const status = this.value;
                updateURL({ search, status, page: 1 });
            });
            
            document.getElementById('add-user-btn').addEventListener('click', function() {
                showModal('add-user-modal');
            });
            
            document.getElementById('refresh-btn').addEventListener('click', function() {
                window.location.reload();
            });
            
            // Pagination
            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    const urlParams = new URLSearchParams(window.location.search);
                    let page = parseInt(urlParams.get('page')) || 1;
                    if (page > 1) {
                        urlParams.set('page', page - 1);
                        window.location.search = urlParams.toString();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const urlParams = new URLSearchParams(window.location.search);
                    let page = parseInt(urlParams.get('page')) || 1;
                    let totalPages = <?php echo $total_pages; ?>;
                    if (page < totalPages) {
                        urlParams.set('page', page + 1);
                        window.location.search = urlParams.toString();
                    }
                });
            }
            
            // Add user form
            const addUserForm = document.getElementById('add-user-form');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    addUser(this);
                });
            }
        });
        function loadUserStats() {
            fetch('../backend/api/admin.php?action=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Get additional user stats
                        fetch('../backend/api/admin.php?action=users&limit=100')
                            .then(response => response.json())
                            .then(usersData => {
                                if (usersData.success) {
                                    const users = usersData.data.users || [];
                                    
                                    // Active today (users who logged in today)
                                    const today = new Date().toISOString().split('T')[0];
                                    const activeToday = users.filter(user => {
                                        const userDate = new Date(user.created_at).toISOString().split('T')[0];
                                        return userDate === today;
                                    }).length;
                                    
                                    // New this week
                                    const weekAgo = new Date();
                                    weekAgo.setDate(weekAgo.getDate() - 7);
                                    const newThisWeek = users.filter(user => {
                                        const userDate = new Date(user.created_at);
                                        return userDate >= weekAgo;
                                    }).length;
                                    
                                    // Top spender
                                    let topSpender = 0;
                                    users.forEach(user => {
                                        const spent = user.order_stats?.total_spent || 0;
                                        if (spent > topSpender) {
                                            topSpender = spent;
                                        }
                                    });
                                    
                                    document.getElementById('active-users').textContent = activeToday;
                                    document.getElementById('new-users').textContent = newThisWeek;
                                    document.getElementById('top-spender').textContent = '$' + topSpender.toFixed(2);
                                }
                            })
                            .catch(error => console.error('Error loading user stats:', error));
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }
        
        function viewUser(userId) {
            fetch(../backend/api/admin.php?action=user&user_id=${userId})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showUserDetails(data.data);
                    } else {
                        showNotification(data.message || 'Failed to load user details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading user:', error);
                    showNotification('Failed to load user details', 'error');
                });
        }
        
        function showUserDetails(user) {
            const modalBody = document.getElementById('view-user-modal-body');
            if (!modalBody) return;
            
            modalBody.innerHTML = `
                <div class="user-details">
                    <div class="user-header">
                        <div class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-info">
                            <h4>${user.full_name}</h4>
                            <p>${user.username} • ${user.email}</p>
                            <span class="status-badge ${user.is_active ? 'status-active' : 'status-inactive'}">
                                ${user.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="user-stats">
                        <div class="stat-item">
                            <i class="fas fa-calendar"></i>
                            <div>
                                <strong>Joined</strong>
                                <p>${new Date(user.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong>Phone</strong>
                                <p>${user.phone || 'Not provided'}</p>
                            </div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong>Address</strong>
                                <p>${user.address || 'Not provided'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-orders">
                        <h5>Recent Orders</h5>
                        ${user.recent_orders && user.recent_orders.length > 0 ? `
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${user.recent_orders.map(order => `
                                    <tr>
                                        <td>#${order.order_id.toString().padStart(6, '0')}</td>
                                        <td>${new Date(order.order_date).toLocaleDateString()}</td>
                                        <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                                        <td>
                                            <span class="status-badge status-${order.status}">
                                                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        ` : '<p class="no-orders">No orders yet</p>'}
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="editUser(${user.user_id})">
                            <i class="fas fa-edit"></i> Edit User
                        </button>
                        <button class="btn btn-outline" onclick="closeModal('view-user-modal')">
                            Close
                        </button>
                    </div>
                </div>
            `;
            
            showModal('view-user-modal');
            
            // Add styles for user details
            const style = document.createElement('style');
            style.textContent = `
            .user-details {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }
                
                .user-header {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #f8f9fa;
                }
                
                .user-avatar {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 2.5rem;
                }
                
                .user-info h4 {
                    margin: 0 0 5px 0;
                    color: #2c3e50;
                    font-size: 1.3rem;
                }
                
                .user-info p {
                    margin: 0 0 10px 0;
                    color: #7f8c8d;
                }
                
                .user-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                
                .stat-item {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                
                .stat-item i {
                    font-size: 1.5rem;
                    color: #3498db;
                }
                
                .stat-item strong {
                    display: block;
                    color: #2c3e50;
                    font-size: 0.9rem;
                }
                
                .stat-item p {
                    margin: 5px 0 0 0;
                    color: #7f8c8d;
                    font-size: 0.9rem;
                }
                
                .user-orders {
                    margin-top: 10px;
                }
                
                .user-orders h5 {
                    margin: 0 0 15px 0;
                    color: #2c3e50;
                    font-size: 1.1rem;
                }
                
                .order-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .order-table th {
                    padding: 10px;
                    text-align: left;
                    background: #f8f9fa;
                    color: #2c3e50;
                    font-weight: 600;
                    border-bottom: 2px solid #eef1f5;
                }
                
                .order-table td {
                    padding: 10px;
                    border-bottom: 1px solid #eef1f5;
                }
                
                .no-orders {
                    text-align: center;
                    padding: 20px;
                    color: #7f8c8d;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
            `;
            document.head.appendChild(style);
        }
        
        function editUser(userId) {
            closeModal('view-user-modal');
            
            fetch(../backend/api/admin.php?action=user&user_id=${userId})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditUserModal(data.data);
                    } else {
                        showNotification(data.message || 'Failed to load user data', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading user:', error);
                    showNotification('Failed to load user data', 'error');
                });
        }
        
        function showEditUserModal(user) {
            const modalBody = document.getElementById('edit-user-modal-body');
            if (!modalBody) return;
            
            modalBody.innerHTML = `
                <form id="edit-user-form" class="admin-form">
                    <input type="hidden" name="user_id" value="${user.user_id}">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-username">Username *</label>
                            <input type="text" id="edit-username" name="username" class="form-control" 
                                   value="${user.username}" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-email">Email *</label>
                            <input type="email" id="edit-email" name="email" class="form-control" 
                                   value="${user.email}" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-full_name">Full Name *</label>
                            <input type="text" id="edit-full_name" name="full_name" class="form-control" 
                                   value="${user.full_name}" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-phone">Phone</label>
                            <input type="tel" id="edit-phone" name="phone" class="form-control" 
                                   value="${user.phone || ''}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-address">Address</label>
                        <textarea id="edit-address" name="address" class="form-control" rows="3">${user.address || ''}</textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-password">New Password (leave blank to keep current)</label>
                            <input type="password" id="edit-password" name="password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-is_active">Status</label>
                            <select id="edit-is_active" name="is_active" class="form-control">
                                <option value="1" ${user.is_active ? 'selected' : ''}>Active</option>
                                <option value="0" ${!user.is_active ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Update User</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('edit-user-modal')">Cancel</button>
                    </div>
                    </form>
            `;
            
            const form = document.getElementById('edit-user-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    updateUser(this);
                });
            }
            
            showModal('edit-user-modal');
        }
        
        function updateUser(form) {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Remove empty password field
            if (!data.password) {
                delete data.password;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
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
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to update user', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error updating user:', error);
                showNotification('Failed to update user', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            
            fetch(../backend/api/admin.php?action=delete_user&user_id=${userId}, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('User deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to delete user', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting user:', error);
                showNotification('Failed to delete user', 'error');
            });
        }
        
        function addUser(form) {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Validate password
            if (data.password !== data.confirm_password) {
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            delete data.confirm_password;
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            // Use auth endpoint for registration (since admin.php doesn't have add_user)
            fetch('../backend/api/auth.php?action=register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('User added successfully', 'success');
                    form.reset();
                    closeModal('add-user-modal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to add user', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error adding user:', error);
                showNotification('Failed to add user', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function updateURL(params) {
            const urlParams = new URLSearchParams(window.location.search);
            
            Object.keys(params).forEach(key => {
                if (params[key]) {
                    urlParams.set(key, params[key]);
                } else {
                    urlParams.delete(key);
                }
            });
            
            window.location.search = urlParams.toString();
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
        
        // Make functions available globally
        window.viewUser = viewUser;
        window.editUser = editUser;
        window.deleteUser = deleteUser;
        window.closeModal = closeModal;
        window.showModal = function(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        };
    </script>
</body>
</html>
