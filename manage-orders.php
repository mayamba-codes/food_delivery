<?php
/**
 * Admin - Manage Orders
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
$sql = "SELECT o.*, u.username, u.full_name, u.email, 
               COUNT(oi.order_item_id) as item_count 
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id";
        
$where_clauses = [];
$params = [];
$types = "";

if ($search) {
    $where_clauses[] = "(o.order_id LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

if ($status && $status !== '') {
    $where_clauses[] = "o.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.user_id";
if (!empty($where_clauses)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_count / $limit);

// Get orders with pagination
$sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Get order status counts for filter
$status_counts_sql = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$status_counts_stmt = $db->prepare($status_counts_sql);
$status_counts_stmt->execute();
$status_counts_result = $status_counts_stmt->get_result();

$status_counts = [];
while ($row = $status_counts_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - FoodExpress Admin</title>
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
                <a href="manage-orders.php" class="active">
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
                    <h1>Manage Orders</h1>
                    <p>View and manage all customer orders</p>
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
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_count; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $status_counts['delivered'] ?? 0; ?></h3>
                        <p>Delivered</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $status_counts['pending'] ?? 0; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #9b59b6;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="today-revenue">$0</h3>
                        <p>Today's Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Order Status Tabs -->
            <div class="dashboard-tabs">
                <button class="tab-btn <?php echo !$status ? 'active' : ''; ?>" data-status="">
                    All Orders (<?php echo $total_count; ?>)
                </button>
                <button class="tab-btn <?php echo $status === 'pending' ? 'active' : ''; ?>" data-status="pending">
                    Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                </button>
                <button class="tab-btn <?php echo $status === 'confirmed' ? 'active' : ''; ?>" data-status="confirmed">
                    Confirmed (<?php echo $status_counts['confirmed'] ?? 0; ?>)
                </button>
                <button class="tab-btn <?php echo $status === 'preparing' ? 'active' : ''; ?>" data-status="preparing">
                    Preparing (<?php echo $status_counts['preparing'] ?? 0; ?>)
                </button>
                <button class="tab-btn <?php echo $status === 'out_for_delivery' ? 'active' : ''; ?>" data-status="out_for_delivery">
                    Out for Delivery (<?php echo $status_counts['out_for_delivery'] ?? 0; ?>)
                </button>
                <button class="tab-btn <?php echo $status === 'delivered' ? 'active' : ''; ?>" data-status="delivered">
                    Delivered (<?php echo $status_counts['delivered'] ?? 0; ?>)
                </button>
                <button class="tab-btn <?php echo $status === 'cancelled' ? 'active' : ''; ?>" data-status="cancelled">
                    Cancelled (<?php echo $status_counts['cancelled'] ?? 0; ?>)
                </button>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter-container">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search orders by ID, name, or email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="date-filter" class="filter-select">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
                
                <button id="export-btn" class="btn btn-success">
                    <i class="fas fa-download"></i> Export
                </button>
                
                <button id="refresh-btn" class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <!-- Orders Table -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Orders List</h2>
                    <div class="pagination-info">
                        Showing <?php echo min($limit, count($orders)); ?> of <?php echo $total_count; ?> orders
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table" id="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orders-tbody">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fas fa-shopping-cart"></i>
                                        <p>No orders found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="user-info">
                                            <strong><?php echo htmlspecialchars($order['full_name'] ?: $order['username']); ?></strong>
                                            <small><?php echo htmlspecialchars($order['email']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo $order['item_count']; ?> items</td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $order['payment_status'] === 'paid' ? 'status-active' : 'status-pending'; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <?php if ($order['status'] === 'pending' || $order['status'] === 'confirmed'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
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

    <!-- Order Details Modal -->
    <div class="modal" id="order-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="order-details-modal-body">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal" id="update-status-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Order Status</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="update-status-modal-body">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize admin functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Load today's revenue
            loadTodayRevenue();
            
            // Setup event listeners
            document.getElementById('search-input').addEventListener('input', debounce(function() {
                const search = this.value;
                const status = getCurrentStatus();
                updateURL({ search, status, page: 1 });
            }, 300));
            
            document.getElementById('date-filter').addEventListener('change', function() {
                const search = document.getElementById('search-input').value;
                const status = getCurrentStatus();
                const date = this.value;
                updateURL({ search, status, date, page: 1 });
            });
            
            // Tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const status = this.dataset.status;
                    const search = document.getElementById('search-input').value;
                    updateURL({ search, status, page: 1 });
                });
            });
            
            document.getElementById('export-btn').addEventListener('click', function() {
                exportOrders();
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
        });
        
        function loadTodayRevenue() {
            fetch('../backend/api/admin.php?action=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('today-revenue').textContent = 
                            '$' + (parseFloat(data.data.today_revenue) || 0).toFixed(2);
                    }
                })
                .catch(error => console.error('Error loading revenue:', error));
        }
        
        function getCurrentStatus() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('status') || '';
        }
        
        function viewOrderDetails(orderId) {
            fetch(../backend/api/orders.php?order_id=${orderId})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showOrderDetails(data.data);
                    } else {
                        showNotification(data.message || 'Failed to load order details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading order details:', error);
                    showNotification('Failed to load order details', 'error');
                });
        }
        
        function showOrderDetails(order) {
            const modalBody = document.getElementById('order-details-modal-body');
            if (!modalBody) return;
            
            const statusClass = status-${order.status};
            const statusText = order.status.charAt(0).toUpperCase() + order.status.slice(1).replace('_', ' ');
            
            modalBody.innerHTML = `
                <div class="order-details">
                    <div class="order-header">
                        <h4>Order #${order.order_id.toString().padStart(6, '0')}</h4>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    
                    <div class="order-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <strong>Customer:</strong>
                                <p>${order.full_name || order.username} (${order.email})</p>
                            </div>
                            <div class="info-item">
                                <strong>Order Date:</strong>
                                <p>${new Date(order.order_date).toLocaleString()}</p>
                            </div>
                            <div class="info-item">
                                <strong>Delivery Address:</strong>
                                <p>${order.delivery_address}</p>
                            </div>
                            <div class="info-item">
                                <strong>Contact:</strong>
                                <p>${order.contact_number}</p>
                            </div>
                            <div class="info-item">
                                <strong>Payment Method:</strong>
                                <p>${order.payment_method === 'cash_on_delivery' ? 'Cash on Delivery' : 'Online Payment'}</p>
                            </div>
                            <div class="info-item">
                                <strong>Payment Status:</strong>
                                <p>
                                    <span class="status-badge ${order.payment_status === 'paid' ? 'status-active' : 'status-pending'}">
                                        ${order.payment_status === 'paid' ? 'Paid' : 'Pending'}
                                    </span>
                                </p>
                            </div>
                            <div class="info-item">
                                <strong>Special Instructions:</strong>
                                <p>${order.special_instructions || 'None'}</p>
                            </div>
                        </div>
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
                                        <td>
                                            <div class="item-info">
                                                <strong>${item.food_name}</strong>
                                            </div>
                                        </td>
                                        <td>${item.quantity}</td>
                                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                                        <td>$${(item.price * item.quantity).toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                                    <td><strong>$${parseFloat(order.total_amount).toFixed(2)}</strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="text-align: right;">Delivery Fee:</td>
                                    <td>$2.99</td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="text-align: right;">Tax (8%):</td>
                                    <td>$${(order.total_amount * 0.08).toFixed(2)}</td>
                                </tr>
                                <tr class="grand-total">
                                    <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                    <td><strong>$${(parseFloat(order.total_amount) + 2.99 + (order.total_amount * 0.08)).toFixed(2)}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="order-actions">
                        <div class="status-update">
                            <label for="update-status-select">Update Status</label>
                            <select id="update-status-select" class="form-control">
                                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                <option value="preparing" ${order.status === 'preparing' ? 'selected' : ''}>Preparing</option>
                                <option value="out_for_delivery" ${order.status === 'out_for_delivery' ? 'selected' : ''}>Out for Delivery</option>
                                <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="saveOrderStatus(${order.order_id})">
                                Update Status
                            </button>
                            ${order.status === 'pending' || order.status === 'confirmed' ? `
                            <button class="btn btn-danger" onclick="cancelOrder(${order.order_id}, true)">
                                Cancel Order
                            </button>
                            ` : ''}
                            <button class="btn btn-outline" onclick="printOrder(${order.order_id})">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            showModal('order-details-modal');
            
            // Add styles for order details
            const style = document.createElement('style');
            style.textContent = `
                .order-details {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }
                
                .order-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #f8f9fa;
                }
                
                .order-header h4 {
                    margin: 0;
                    color: #2c3e50;
                    font-size: 1.3rem;
                }
                
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 15px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                
                .info-item strong {
                    display: block;
                    color: #2c3e50;
                    font-size: 0.9rem;
                    margin-bottom: 5px;
                }
                
                .info-item p {
                    margin: 0;
                    color: #5d6d7e;
                    font-size: 0.95rem;
                }
                
                .order-items {
                    margin-top: 10px;
                }
                
                .order-items h5 {
                    margin: 0 0 15px 0;
                    color: #2c3e50;
                    font-size: 1.1rem;
                }
                
                .order-items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                
                .order-items-table th {
                    padding: 12px;
                    text-align: left;
                    background: #f8f9fa;
                    color: #2c3e50;
                    font-weight: 600;
                    border-bottom: 2px solid #eef1f5;
                }
                
                .order-items-table td {
                    padding: 12px;
                    border-bottom: 1px solid #eef1f5;
                }
                
                .order-items-table tfoot tr:last-child td {
                    border-bottom: none;
                }
                
                .grand-total {
                    font-weight: 600;
                    color: #2c3e50;
                }
                
                .item-info {
                    display: flex;
                    flex-direction: column;
                }
                
                .item-info strong {
                    color: #2c3e50;
                }
                
                .order-actions {
                    padding-top: 20px;
                    border-top: 2px solid #f8f9fa;
                }
                
                .status-update {
                    margin-bottom: 20px;
                }
                
                .status-update label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                    color: #2c3e50;
                }
            `;
            document.head.appendChild(style);
        }
        
        function updateOrderStatus(orderId) {
            const modalBody = document.getElementById('update-status-modal-body');
            if (!modalBody) return;
            
            modalBody.innerHTML = `
                <form id="update-status-form" class="admin-form">
                    <input type="hidden" name="order_id" value="${orderId}">
                    
                    <div class="form-group">
                        <label for="status-select">Select New Status</label>
                        <select id="status-select" name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status-notes">Notes (Optional)</label>
                        <textarea id="status-notes" name="notes" class="form-control" rows="3" 
                                  placeholder="Add any notes about the status change..."></textarea>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Update Status</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('update-status-modal')">Cancel</button>
                    </div>
                </form>
            `;
            
            const form = document.getElementById('update-status-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData);
                    
                    saveOrderStatus(data.order_id, data.status);
                });
            }
            
            showModal('update-status-modal');
        }
        
        function saveOrderStatus(orderId, newStatus = null) {
            if (!newStatus) {
                newStatus = document.getElementById('update-status-select')?.value;
                if (!newStatus) {
                    showNotification('Please select a status', 'error');
                    return;
                }
            }
            
            fetch('../backend/api/orders.php?action=update_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_id: orderId, status: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Order status updated successfully', 'success');
                    closeModal('order-details-modal');
                    closeModal('update-status-modal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to update order status', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating order status:', error);
                showNotification('Failed to update order status', 'error');
            });
        }
        
        function cancelOrder(orderId, fromModal = false) {
            if (!confirm('Are you sure you want to cancel this order?')) {
                return;
            }
            
            fetch(../backend/api/orders.php?action=cancel&order_id=${orderId}, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Order cancelled successfully', 'success');
                    if (fromModal) {
                        closeModal('order-details-modal');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to cancel order', 'error');
                }
            })
            .catch(error => {
                console.error('Error cancelling order:', error);
                showNotification('Failed to cancel order', 'error');
            });
        }
        
        function printOrder(orderId) {
            // Open print window with order details
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Order #${orderId} - FoodExpress</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .order-info { margin-bottom: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #f5f5f5; }
                        .total { font-weight: bold; text-align: right; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>FoodExpress</h1>
                        <p>Order Receipt</p>
                    </div>
                    <p class="no-print">
                        <button onclick="window.print()">Print Receipt</button>
                        <button onclick="window.close()">Close</button>
                    </p>
                    <p>Loading order details...</p>
                </body>
                </html>
            `);
            
            // Load order details
            fetch(../backend/api/orders.php?order_id=${orderId})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.data;
                        const orderDate = new Date(order.order_date).toLocaleString();
                        
                        let itemsHtml = '';
                        let subtotal = 0;
                        
                        if (order.items && order.items.length > 0) {
                            itemsHtml = order.items.map(item => {
                                const itemSubtotal = item.price * item.quantity;
                                subtotal += itemSubtotal;
                                return `
                                    <tr>
                                        <td>${item.food_name}</td>
                                        <td>${item.quantity}</td>
                                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                                        <td>$${itemSubtotal.toFixed(2)}</td>
                                    </tr>
                                `;
                            }).join('');
                        }
                        
                        const deliveryFee = 2.99;
                        const tax = subtotal * 0.08;
                        const grandTotal = subtotal + deliveryFee + tax;
                        
                        printWindow.document.body.innerHTML = `
                            <div class="header">
                                <h1>FoodExpress</h1>
                                <p>Order Receipt</p>
                            </div>
                            <p class="no-print">
                                <button onclick="window.print()">Print Receipt</button>
                                <button onclick="window.close()">Close</button>
                            </p>
                            <div class="order-info">
                                <p><strong>Order #</strong>: ${order.order_id.toString().padStart(6, '0')}</p>
                                <p><strong>Date</strong>: ${orderDate}</p>
                                <p><strong>Customer</strong>: ${order.full_name || order.username}</p>
                                <p><strong>Status</strong>: ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</p>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="total">Subtotal:</td>
                                        <td>$${subtotal.toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="total">Delivery Fee:</td>
                                        <td>$${deliveryFee.toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="total">Tax (8%):</td>
                                        <td>$${tax.toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="total">Grand Total:</td>
                                        <td>$${grandTotal.toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <div class="footer">
                                <p>Thank you for ordering with FoodExpress!</p>
                                <p>For questions, contact: support@foodexpress.com</p>
                            </div>
                        `;
                    } else {
                        printWindow.document.body.innerHTML = `
                            <p>Failed to load order details.</p>
                            <button onclick="window.close()">Close</button>
                        `;
                    }
                })
                .catch(error => {
                    printWindow.document.body.innerHTML = `
                        <p>Error loading order details.</p>
                        <button onclick="window.close()">Close</button>
                    `;
                });
        }
        
        function exportOrders() {
            showNotification('Export functionality would generate a CSV/Excel file', 'info');
            // In a real implementation, this would generate and download a CSV file
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
        window.viewOrderDetails = viewOrderDetails;
        window.updateOrderStatus = updateOrderStatus;
        window.saveOrderStatus = saveOrderStatus;
        window.cancelOrder = cancelOrder;
        window.printOrder = printOrder;
        window.closeModal = closeModal;
        window.showModal = function(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        };
    </script>
</body>
</html>