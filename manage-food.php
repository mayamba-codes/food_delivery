<?php
/**
 * Admin - Manage Food Items
 */

require_once '../backend/includes/session.php';
SessionManager::requireAdmin();

// Include database connection
require_once '../backend/config/database.php';
$db = new Database();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Get categories for filter
$categories_sql = "SELECT category_id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name";
$categories_stmt = $db->prepare($categories_sql);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Build food items query
$sql = "SELECT f.*, c.category_name 
        FROM food_items f 
        LEFT JOIN categories c ON f.category_id = c.category_id 
        WHERE 1=1";
        
$where_clauses = [];
$params = [];
$types = "";

if ($search) {
    $where_clauses[] = "(f.food_name LIKE ? OR f.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if ($category && $category !== '') {
    $where_clauses[] = "f.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if ($status === 'available') {
    $where_clauses[] = "f.is_available = 1";
} elseif ($status === 'unavailable') {
    $where_clauses[] = "f.is_available = 0";
}

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM food_items f WHERE 1=1";
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

// Get food items with pagination
$sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$food_items = [];
while ($row = $result->fetch_assoc()) {
    $food_items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Food Items - FoodExpress Admin</title>
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
                <a href="manage-food.php" class="active">
                    <i class="fas fa-utensils"></i> Manage Food
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
                    <h1>Manage Food Items</h1>
                    <p>Add, edit, and manage food menu items</p>
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
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_count; ?></h3>
                        <p>Total Items</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="available-items">0</h3>
                        <p>Available</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="top-category">-</h3>
                        <p>Top Category</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #9b59b6;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="avg-price">$0</h3>
                        <p>Avg Price</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter-container">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search food items..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="category-filter" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>" 
                            <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="status-filter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available Only</option>
                    <option value="unavailable" <?php echo $status === 'unavailable' ? 'selected' : ''; ?>>Unavailable Only</option>
                </select>
                <button id="add-food-btn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Food Item
                </button>
                
                <button id="refresh-btn" class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <!-- Food Items Table -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Food Items</h2>
                    <div class="pagination-info">
                        Showing <?php echo min($limit, count($food_items)); ?> of <?php echo $total_count; ?> items
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table" id="food-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="food-tbody">
                            <?php if (empty($food_items)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-utensils"></i>
                                        <p>No food items found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($food_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="food-item">
                                            <img src="<?php echo htmlspecialchars($item['image_url'] ?: '../assets/images/default-food.jpg'); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['food_name']); ?>"
                                                 onerror="this.src='../assets/images/default-food.jpg'">
                                            <div class="food-info">
                                                <strong><?php echo htmlspecialchars($item['food_name']); ?></strong>
                                                <small><?php echo htmlspecialchars(substr($item['description'] ?? 'No description', 0, 50)) . (strlen($item['description'] ?? '') > 50 ? '...' : ''); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $item['is_available'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline" onclick="viewFoodItem(<?php echo $item['food_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editFoodItem(<?php echo $item['food_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteFoodItem(<?php echo $item['food_id']; ?>)">
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

    <!-- Add Food Item Modal -->
    <div class="modal" id="add-food-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Food Item</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-food-form" class="admin-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="add-food_name">Food Name *</label>
                        <input type="text" id="add-food_name" name="food_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="add-description">Description</label>
                        <textarea id="add-description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add-price">Price *</label>
                            <input type="number" id="add-price" name="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="add-category_id">Category *</label>
                            <select id="add-category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
               <div class="form-row">
                        <div class="form-group">
                            <label for="add-image_url">Image URL</label>
                            <input type="text" id="add-image_url" name="image_url" class="form-control" 
                                   placeholder="https://example.com/image.jpg">
                        </div>
                        <div class="form-group">
                            <label for="add-is_available">Status</label>
                            <select id="add-is_available" name="is_available" class="form-control">
                                <option value="1">Available</option>
                                <option value="0">Unavailable</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="add-image_file">Or Upload Image</label>
                        <input type="file" id="add-image_file" name="image_file" class="form-control" 
                               accept="image/*">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Add Food Item</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('add-food-modal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Food Item Modal -->
    <div class="modal" id="edit-food-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Food Item</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="edit-food-modal-body">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- View Food Item Modal -->
    <div class="modal" id="view-food-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Food Item Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="view-food-modal-body">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize admin functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Load food statistics
            loadFoodStats();
            
            // Setup event listeners
            document.getElementById('search-input').addEventListener('input', debounce(function() {
                const search = this.value;
                const category = document.getElementById('category-filter').value;
                const status = document.getElementById('status-filter').value;
                updateURL({ search, category, status, page: 1 });
            }, 300));
            
            document.getElementById('category-filter').addEventListener('change', function() {
                const search = document.getElementById('search-input').value;
                const category = this.value;
                const status = document.getElementById('status-filter').value;
                updateURL({ search, category, status, page: 1 });
            });
                 document.getElementById('add-food-btn').addEventListener('click', function() {
                showModal('add-food-modal');
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
            
            // Add food form
            const addFoodForm = document.getElementById('add-food-form');
            if (addFoodForm) {
                addFoodForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    addFoodItem(this);
                });
            }
        });
        
        function loadFoodStats() {
            // Calculate stats from current data
            const foodItems = <?php echo json_encode($food_items); ?>;
            
            let availableCount = 0;
            let totalPrice = 0;
            let categoryCounts = {};
            
            foodItems.forEach(item => {
                if (item.is_available == 1) availableCount++;
                totalPrice += parseFloat(item.price);
                
                const category = item.category_name || 'Uncategorized';
                categoryCounts[category] = (categoryCounts[category] || 0) + 1;
            });
            
            // Find top category
            let topCategory = '-';
            let maxCount = 0;
            for (const [category, count] of Object.entries(categoryCounts)) {
                if (count > maxCount) {
                    maxCount = count;
                    topCategory = category;
                }
            }
            
            document.getElementById('available-items').textContent = availableCount;
            document.getElementById('top-category').textContent = topCategory;
            document.getElementById('avg-price').textContent = '$' + (foodItems.length > 0 ? (totalPrice / foodItems.length).toFixed(2) : '0.00');
        }
        
        function viewFoodItem(foodId) {
            fetch(../backend/api/food.php?food_id=${foodId})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showFoodDetails(data.data);
                    } else {
                        showNotification(data.message || 'Failed to load food item details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading food item:', error);
                    showNotification('Failed to load food item details', 'error');
                });
        }
        
        function showFoodDetails(foodItem) {
            const modalBody = document.getElementById('view-food-modal-body');
            if (!modalBody) return;
            
            modalBody.innerHTML = `
                <div class="food-details">
                <div class="food-header">
                        <div class="food-image">
                            <img src="${foodItem.image_url || '../assets/images/default-food.jpg'}" 
                                 alt="${foodItem.food_name}"
                                 onerror="this.src='../assets/images/default-food.jpg'">
                        </div>
                        <div class="food-info">
                            <h4>${foodItem.food_name}</h4>
                            <p class="food-price">$${parseFloat(foodItem.price).toFixed(2)}</p>
                            <span class="status-badge ${foodItem.is_available ? 'status-active' : 'status-inactive'}">
                                ${foodItem.is_available ? 'Available' : 'Unavailable'}
                            </span>
                            <p class="food-category">${foodItem.category_name || 'Uncategorized'}</p>
                        </div>
                    </div>
                    
                    <div class="food-description">
                        <h5>Description</h5>
                        <p>${foodItem.description || 'No description available.'}</p>
                    </div>
                    
                    <div class="food-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <div>
                                <strong>Created</strong>
                                <p>${new Date(foodItem.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-tags"></i>
                            <div>
                                <strong>Category ID</strong>
                                <p>${foodItem.category_id}</p>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-hashtag"></i>
                            <div>
                                <strong>Item ID</strong>
                                <p>#${foodItem.food_id}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="editFoodItem(${foodItem.food_id})">
                            <i class="fas fa-edit"></i> Edit Item
                        </button>
                        <button class="btn btn-outline" onclick="closeModal('view-food-modal')">
                            Close
                        </button>
                    </div>
                </div>
            `;
            
            showModal('view-food-modal');
            
            // Add styles for food details
            const style = document.createElement('style');
            style.textContent = `
                .food-details {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }
                
                .food-header {
                    display: flex;
                    gap: 20px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #f8f9fa;
                }
                    .food-image {
                    width: 150px;
                    height: 150px;
                    border-radius: 10px;
                    overflow: hidden;
                    flex-shrink: 0;
                }
                
                .food-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                
                .food-info h4 {
                    margin: 0 0 10px 0;
                    color: #2c3e50;
                    font-size: 1.5rem;
                }
                
                .food-price {
                    font-size: 1.3rem;
                    color: #e74c3c;
                    font-weight: 600;
                    margin: 0 0 10px 0;
                }
                
                .food-category {
                    margin: 10px 0 0 0;
                    color: #7f8c8d;
                    font-size: 0.9rem;
                }
                
                .food-description h5 {
                    margin: 0 0 10px 0;
                    color: #2c3e50;
                    font-size: 1.1rem;
                }
                
                .food-description p {
                    margin: 0;
                    color: #5d6d7e;
                    line-height: 1.6;
                }
                
                .food-meta {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                
                .meta-item {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                
                .meta-item i {
                    font-size: 1.2rem;
                    color: #3498db;
                }
                
                .meta-item strong {
                    display: block;
                    color: #2c3e50;
                    font-size: 0.9rem;
                }
                
                .meta-item p {
                    margin: 5px 0 0 0;
                    color: #7f8c8d;
                    font-size: 0.9rem;
                }
            `;
            document.head.appendChild(style);
        }
        
        function editFoodItem(foodId) {
            closeModal('view-food-modal');
            
            fetch(../backend/api/food.php?food_id=${foodId})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditFoodModal(data.data);
                    } else {
                        showNotification(data.message || 'Failed to load food item data', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading food item:', error);
                    showNotification('Failed to load food item data', 'error');
                });
        }
        
        function showEditFoodModal(foodItem) {
            const modalBody = document.getElementById('edit-food-modal-body');
            if (!modalBody) return;
            
            modalBody.innerHTML = `
                <form id="edit-food-form" class="admin-form">
                    <input type="hidden" name="food_id" value="${foodItem.food_id}">
                    
                    <div class="form-group">
                        <label for="edit-food_name">Food Name *</label>
                        <input type="text" id="edit-food_name" name="food_name" class="form-control" 
                               value="${foodItem.food_name}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-description">Description</label>
                        <textarea id="edit-description" name="description" 
                        class="form-control" rows="3">${foodItem.description || ''}</textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-price">Price *</label>
                            <input type="number" id="edit-price" name="price" class="form-control" 
                                   step="0.01" min="0" value="${parseFloat(foodItem.price).toFixed(2)}" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-category_id">Category *</label>
                            <select id="edit-category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" 
                                        ${foodItem.category_id == <?php echo $cat['category_id']; ?> ? 'selected' : ''}>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-image_url">Image URL</label>
                            <input type="text" id="edit-image_url" name="image_url" class="form-control" 
                                   value="${foodItem.image_url || ''}" placeholder="https://example.com/image.jpg">
                        </div>
                        <div class="form-group">
                            <label for="edit-is_available">Status</label>
                            <select id="edit-is_available" name="is_available" class="form-control">
                                <option value="1" ${foodItem.is_available ? 'selected' : ''}>Available</option>
                                <option value="0" ${!foodItem.is_available ? 'selected' : ''}>Unavailable</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="current-image" style="margin-bottom: 20px;">
                        <label>Current Image</label>
                        <div style="margin-top: 10px;">
                            <img src="${foodItem.image_url || '../assets/images/default-food.jpg'}" 
                                 alt="Current image"
                                 style="max-width: 150px; max-height: 150px; border-radius: 5px;"
                                 onerror="this.src='../assets/images/default-food.jpg'">
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Update Food Item</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('edit-food-modal')">Cancel</button>
                    </div>
                </form>
            `;
            
            const form = document.getElementById('edit-food-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    updateFoodItem(this);
                });
            }
            
            showModal('edit-food-modal');
        }
        
        function updateFoodItem(form) {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch('../backend/api/admin.php?action=update_food', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Food item updated successfully', 'success');
                    closeModal('edit-food-modal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to update food item', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error updating food item:', error);
                showNotification('Failed to update food item', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function deleteFoodItem(foodId) {
            if (!confirm('Are you sure you want to delete this food item? This action cannot be undone.')) {
                return;
            }
            
            fetch(../backend/api/admin.php?action=delete_food&food_id=${foodId}, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Food item deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to delete food item', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting food item:', error);
                showNotification('Failed to delete food item', 'error');
            });
        }
        
        function addFoodItem(form) {
            const formData = new FormData(form);
            
            // Convert to JSON for API (handle file upload separately if needed)
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'image_file') {
                    data[key] = value;
                }
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            fetch('../backend/api/admin.php?action=add_food', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Food item added successfully', 'success');
                    form.reset();
                    closeModal('add-food-modal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to add food item', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error adding food item:', error);
                showNotification('Failed to add food item', 'error');
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
        window.viewFoodItem = viewFoodItem;
        window.editFoodItem = editFoodItem;
        window.deleteFoodItem = deleteFoodItem;
        window.closeModal = closeModal;
        window.showModal = function(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        };
    </script>
</body>
</html>