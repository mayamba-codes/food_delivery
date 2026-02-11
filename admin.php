<?php
/**
 * Admin API Endpoints
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication and admin privileges
//session_start();
if (!SessionManager::isLoggedIn() || !SessionManager::isAdmin()) {
    FoodDeliverySystem::jsonResponse(false, 'Unauthorized access. Admin privileges required.');
}

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

switch($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'stats':
                getAdminDashboardStats($db);
                break;
            case 'recent_orders':
                getRecentOrders($db);
                break;
            case 'recent_users':
                getRecentUsers($db);
                break;
            case 'users':
                getUsers($db);
                break;
            case 'user':
                getUser($db);
                break;
            case 'categories':
                getCategories($db);
                break;
            case 'category':
                getCategory($db);
                break;
            case 'sales_report':
                getSalesReport($db);
                break;
            default:
                FoodDeliverySystem::jsonResponse(false, 'Invalid action');
        }
        break;
        
    case 'POST':
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'add_food':
                addFoodItem($db);
                break;
            case 'update_food':
                updateFoodItem($db);
                break;
            case 'add_category':
                addCategory($db);
                break;
            case 'update_category':
                updateCategory($db);
                break;
            case 'update_user':
                updateUser($db);
                break;
            case 'update_order':
                updateOrder($db);
                break;
            default:
                FoodDeliverySystem::jsonResponse(false, 'Invalid action');
        }
        break;
        
    case 'DELETE':
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'delete_food':
                deleteFoodItem($db);
                break;
            case 'delete_category':
                deleteCategory($db);
                break;
            case 'delete_user':
                deleteUser($db);
                break;
                default:
                FoodDeliverySystem::jsonResponse(false, 'Invalid action');
        }
        break;
        
    default:
        FoodDeliverySystem::jsonResponse(false, 'Method not allowed');
}

function getAdminDashboardStats($db) {
    // Total users (excluding admin)
    $users_sql = "SELECT COUNT(*) as total_users FROM users WHERE role_id = 2";
    $users_stmt = $db->prepare($users_sql);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    $users_stats = $users_result->fetch_assoc();
    
    // Total food items
    $food_sql = "SELECT COUNT(*) as total_food_items FROM food_items";
    $food_stmt = $db->prepare($food_sql);
    $food_stmt->execute();
    $food_result = $food_stmt->get_result();
    $food_stats = $food_result->fetch_assoc();
    
    // Order statistics
    $order_sql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN DATE(order_date) = CURDATE() THEN total_amount ELSE 0 END), 0) as today_revenue,
                    COUNT(CASE WHEN DATE(order_date) = CURDATE() THEN 1 END) as today_orders
                  FROM orders";
    
    $order_stmt = $db->prepare($order_sql);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_stats = $order_result->fetch_assoc();
    
    // Recent growth (last 7 days)
    $growth_sql = "SELECT 
                    COUNT(*) as orders_last_7_days,
                    COALESCE(SUM(total_amount), 0) as revenue_last_7_days
                  FROM orders 
                  WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    
    $growth_stmt = $db->prepare($growth_sql);
    $growth_stmt->execute();
    $growth_result = $growth_stmt->get_result();
    $growth_stats = $growth_result->fetch_assoc();
    
    $stats = array_merge($users_stats, $food_stats, $order_stats, $growth_stats);
    
    FoodDeliverySystem::jsonResponse(true, 'Dashboard statistics retrieved', $stats);
}

function getRecentOrders($db) {
    $limit = intval($_GET['limit'] ?? 5);
    
    $sql = "SELECT o.*, u.username, u.full_name 
            FROM orders o 
            JOIN users u ON o.user_id = u.user_id 
            ORDER BY o.order_date DESC 
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Recent orders retrieved', $orders);
}

function getRecentUsers($db) {
    $limit = intval($_GET['limit'] ?? 5);
    
    $sql = "SELECT user_id, username, email, full_name, created_at, is_active 
            FROM users 
            WHERE role_id = 2 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Recent users retrieved', $users);
}

function getUsers($db) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
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
    
    // Get total count
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
    
    $pagination = [
        'current_page' => $page,
        'per_page' => $limit,
        'total_users' => $total_count,
        'total_pages' => ceil($total_count / $limit)
    ];
    
    FoodDeliverySystem::jsonResponse(true, 'Users retrieved', [
        'users' => $users,
        'pagination' => $pagination
    ]);
}

function getUser($db) {
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid user ID');
    }
    
    $sql = "SELECT user_id, username, email, full_name, phone, address, 
                   created_at, is_active 
            FROM users 
            WHERE user_id = ? AND role_id = 2";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'User not found');
    }
    
    $user = $result->fetch_assoc();
    
    // Get user orders
    $orders_sql = "SELECT order_id, order_date, total_amount, status 
                   FROM orders 
                   WHERE user_id = ? 
                   ORDER BY order_date DESC 
                   LIMIT 10";
    
    $orders_stmt = $db->prepare($orders_sql);
    $orders_stmt->bind_param("i", $user_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    $orders = [];
    while ($order = $orders_result->fetch_assoc()) {
        $orders[] = $order;
    }
    
    $user['recent_orders'] = $orders;
    
    FoodDeliverySystem::jsonResponse(true, 'User details retrieved', $user);
}

function getCategories($db) {
    $sql = "SELECT c.*, 
                   COUNT(f.food_id) as item_count,
                   SUM(CASE WHEN f.is_available = 1 THEN 1 ELSE 0 END) as available_items
            FROM categories c 
            LEFT JOIN food_items f ON c.category_id = f.category_id 
            GROUP BY c.category_id 
            ORDER BY c.category_name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
        $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Categories retrieved', $categories);
}

function getCategory($db) {
    $category_id = intval($_GET['category_id'] ?? 0);
    
    if ($category_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid category ID');
    }
    
    $sql = "SELECT * FROM categories WHERE category_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Category not found');
    }
    
    $category = $result->fetch_assoc();
    
    // Get category items
    $items_sql = "SELECT food_id, food_name, price, is_available 
                  FROM food_items 
                  WHERE category_id = ? 
                  ORDER BY food_name";
    
    $items_stmt = $db->prepare($items_sql);
    $items_stmt->bind_param("i", $category_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    $category['items'] = $items;
    
    FoodDeliverySystem::jsonResponse(true, 'Category details retrieved', $category);
}

function addFoodItem($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['food_name', 'description', 'price', 'category_id'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            FoodDeliverySystem::jsonResponse(false, "$field is required");
        }
    }
    
    $food_name = FoodDeliverySystem::sanitizeInput($data['food_name']);
    $description = FoodDeliverySystem::sanitizeInput($data['description']);
    $price = floatval($data['price']);
    $category_id = intval($data['category_id']);
    $image_url = FoodDeliverySystem::sanitizeInput($data['image_url'] ?? '');
    $is_available = isset($data['is_available']) ? intval($data['is_available']) : 1;
    
    $sql = "INSERT INTO food_items (food_name, description, price, category_id, image_url, is_available) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssdiss", $food_name, $description, $price, $category_id, $image_url, $is_available);
    
    if ($stmt->execute()) {
        $food_id = $db->getLastInsertId();
        FoodDeliverySystem::logActivity("Added food item: $food_name (ID: $food_id)");
        
        FoodDeliverySystem::jsonResponse(true, 'Food item added successfully', [
            'food_id' => $food_id
        ]);
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to add food item: ' . $stmt->error);
    }
}

function updateFoodItem($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $food_id = intval($data['food_id'] ?? 0);
    if ($food_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid food ID');
    }
    
    // Build update query dynamically based on provided fields
    $update_fields = [];
    $params = [];
    $types = "";
    
    $allowed_fields = ['food_name', 'description', 'price', 'category_id', 'image_url', 'is_available'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            
            if ($field === 'price') {
                $params[] = floatval($data[$field]);
                $types .= "d";
            } elseif ($field === 'category_id' || $field === 'is_available') {
                $params[] = intval($data[$field]);
                $types .= "i";
            } else {
                $params[] = FoodDeliverySystem::sanitizeInput($data[$field]);
                $types .= "s";
            }
        }
    }
    
    if (empty($update_fields)) {
        FoodDeliverySystem::jsonResponse(false, 'No fields to update');
    }
    
    $params[] = $food_id;
    $types .= "i";
    
    $sql = "UPDATE food_items SET " . implode(", ", $update_fields) . " WHERE food_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        FoodDeliverySystem::logActivity("Updated food item ID: $food_id");
        FoodDeliverySystem::jsonResponse(true, 'Food item updated successfully');
        } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to update food item');
    }
}

function deleteFoodItem($db) {
    $food_id = intval($_GET['food_id'] ?? 0);
    
    if ($food_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid food ID');
    }
    
    // Check if food item exists
    $check_sql = "SELECT food_name FROM food_items WHERE food_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $food_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Food item not found');
    }
    
    $food_name = $check_result->fetch_assoc()['food_name'];
    
    // Delete food item
    $delete_sql = "DELETE FROM food_items WHERE food_id = ?";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->bind_param("i", $food_id);
    
    if ($delete_stmt->execute()) {
        FoodDeliverySystem::logActivity("Deleted food item: $food_name (ID: $food_id)");
        FoodDeliverySystem::jsonResponse(true, 'Food item deleted successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to delete food item');
    }
}

function addCategory($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['category_name'])) {
        FoodDeliverySystem::jsonResponse(false, 'Category name is required');
    }
    
    $category_name = FoodDeliverySystem::sanitizeInput($data['category_name']);
    $description = FoodDeliverySystem::sanitizeInput($data['description'] ?? '');
    $image_url = FoodDeliverySystem::sanitizeInput($data['image_url'] ?? '');
    
    $sql = "INSERT INTO categories (category_name, description, image_url) 
            VALUES (?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("sss", $category_name, $description, $image_url);
    
    if ($stmt->execute()) {
        $category_id = $db->getLastInsertId();
        FoodDeliverySystem::logActivity("Added category: $category_name (ID: $category_id)");
        
        FoodDeliverySystem::jsonResponse(true, 'Category added successfully', [
            'category_id' => $category_id
        ]);
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to add category');
    }
}

function updateCategory($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $category_id = intval($data['category_id'] ?? 0);
    if ($category_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid category ID');
    }
    
    $update_fields = [];
    $params = [];
    $types = "";
    
    $allowed_fields = ['category_name', 'description', 'image_url', 'is_active'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            
            if ($field === 'is_active') {
                $params[] = intval($data[$field]);
                $types .= "i";
            } else {
                $params[] = FoodDeliverySystem::sanitizeInput($data[$field]);
                $types .= "s";
            }
        }
    }
    
    if (empty($update_fields)) {
        FoodDeliverySystem::jsonResponse(false, 'No fields to update');
    }
    
    $params[] = $category_id;
    $types .= "i";
    
    $sql = "UPDATE categories SET " . implode(", ", $update_fields) . " WHERE category_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        FoodDeliverySystem::logActivity("Updated category ID: $category_id");
        FoodDeliverySystem::jsonResponse(true, 'Category updated successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to update category');
    }
}

function deleteCategory($db) {
    $category_id = intval($_GET['category_id'] ?? 0);
    
    if ($category_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid category ID');
    }
    
    // Check if category has items
    $check_items_sql = "SELECT COUNT(*) as item_count FROM food_items WHERE category_id = ?";
    $check_items_stmt = $db->prepare($check_items_sql);
    $check_items_stmt->bind_param("i", $category_id);
    $check_items_stmt->execute();
    $check_items_result = $check_items_stmt->get_result();
    $item_count = $check_items_result->fetch_assoc()['item_count'];
    
    if ($item_count > 0) {
        FoodDeliverySystem::jsonResponse(false, 'Cannot delete category with existing food items');
    }
    
    // Delete category
    $delete_sql = "DELETE FROM categories WHERE category_id = ?";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->bind_param("i", $category_id);
    
    if ($delete_stmt->execute()) {
        FoodDeliverySystem::logActivity("Deleted category ID: $category_id");
        FoodDeliverySystem::jsonResponse(true, 'Category deleted successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to delete category');
    }
}

function updateUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = intval($data['user_id'] ?? 0);
    if ($user_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid user ID');
    }
    
    // Check if user exists and is not admin
    $check_sql = "SELECT role_id FROM users WHERE user_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'User not found');
    }
    
    $user = $check_result->fetch_assoc();
    if ($user['role_id'] == 1) {
        FoodDeliverySystem::jsonResponse(false, 'Cannot modify admin user');
    }
    
    $update_fields = [];
    $params = [];
    $types = "";
    
    $allowed_fields = ['username', 'email', 'full_name', 'phone', 'address', 'is_active'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            
            if ($field === 'is_active') {
                $params[] = intval($data[$field]);
                $types .= "i";
            } else {
                $params[] = FoodDeliverySystem::sanitizeInput($data[$field]);
                $types .= "s";
            }
        }
    }
    if (empty($update_fields)) {
        FoodDeliverySystem::jsonResponse(false, 'No fields to update');
    }
    
    $params[] = $user_id;
    $types .= "i";
    
    $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        FoodDeliverySystem::logActivity("Updated user ID: $user_id");
        FoodDeliverySystem::jsonResponse(true, 'User updated successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to update user');
    }
}

function deleteUser($db) {
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid user ID');
    }
    
    // Check if user exists and is not admin
    $check_sql = "SELECT role_id FROM users WHERE user_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'User not found');
    }
    
    $user = $check_result->fetch_assoc();
    if ($user['role_id'] == 1) {
        FoodDeliverySystem::jsonResponse(false, 'Cannot delete admin user');
    }
    
    // Check if user has orders
    $check_orders_sql = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
    $check_orders_stmt = $db->prepare($check_orders_sql);
    $check_orders_stmt->bind_param("i", $user_id);
    $check_orders_stmt->execute();
    $check_orders_result = $check_orders_stmt->get_result();
    $order_count = $check_orders_result->fetch_assoc()['order_count'];
    
    if ($order_count > 0) {
        FoodDeliverySystem::jsonResponse(false, 'Cannot delete user with existing orders. Deactivate instead.');
    }
    
    // Delete user (this will cascade delete cart items due to foreign key)
    $delete_sql = "DELETE FROM users WHERE user_id = ?";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    
    if ($delete_stmt->execute()) {
        FoodDeliverySystem::logActivity("Deleted user ID: $user_id");
        FoodDeliverySystem::jsonResponse(true, 'User deleted successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to delete user');
    }
}

function updateOrder($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $order_id = intval($data['order_id'] ?? 0);
    if ($order_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid order ID');
    }
    
    $update_fields = [];
    $params = [];
    $types = "";
    
    $allowed_fields = ['status', 'payment_status', 'delivery_address', 'contact_number', 'special_instructions'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = FoodDeliverySystem::sanitizeInput($data[$field]);
            $types .= "s";
        }
    }
    if (empty($update_fields)) {
        FoodDeliverySystem::jsonResponse(false, 'No fields to update');
    }
    
    $params[] = $order_id;
    $types .= "i";
    
    $sql = "UPDATE orders SET " . implode(", ", $update_fields) . " WHERE order_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        FoodDeliverySystem::logActivity("Updated order ID: $order_id");
        FoodDeliverySystem::jsonResponse(true, 'Order updated successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to update order');
    }
}

function getSalesReport($db) {
    $period = $_GET['period'] ?? 'month'; // day, week, month, year
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    // Determine date range based on period
    if ($start_date && $end_date) {
        $date_condition = "DATE(order_date) BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
        $types = "ss";
    } else {
        switch ($period) {
            case 'day':
                $date_condition = "DATE(order_date) = CURDATE()";
                $params = [];
                $types = "";
                break;
            case 'week':
                $date_condition = "order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                $params = [];
                $types = "";
                break;
            case 'year':
                $date_condition = "order_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                $params = [];
                $types = "";
                break;
            case 'month':
            default:
                $date_condition = "order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                $params = [];
                $types = "";
        }
    }
    
    // Get sales summary
    $summary_sql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COALESCE(AVG(total_amount), 0) as avg_order_value,
                    COUNT(DISTINCT user_id) as unique_customers,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
                  FROM orders 
                  WHERE $date_condition";
    
    $summary_stmt = $db->prepare($summary_sql);
    if (!empty($params)) {
        $summary_stmt->bind_param($types, ...$params);
    }
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary = $summary_result->fetch_assoc();
    // Get daily sales for chart
    $daily_sql = "SELECT 
                    DATE(order_date) as date,
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as daily_revenue
                  FROM orders 
                  WHERE $date_condition AND status = 'delivered'
                  GROUP BY DATE(order_date)
                  ORDER BY date";
    
    $daily_stmt = $db->prepare($daily_sql);
    if (!empty($params)) {
        $daily_stmt->bind_param($types, ...$params);
    }
    $daily_stmt->execute();
    $daily_result = $daily_stmt->get_result();
    
    $daily_sales = [];
    while ($row = $daily_result->fetch_assoc()) {
        $daily_sales[] = $row;
    }
    
    // Get top selling items
    $top_items_sql = "SELECT 
                        f.food_name,
                        SUM(oi.quantity) as total_sold,
                        SUM(oi.subtotal) as total_revenue
                      FROM order_items oi
                      JOIN food_items f ON oi.food_id = f.food_id
                      JOIN orders o ON oi.order_id = o.order_id
                      WHERE o.$date_condition AND o.status = 'delivered'
                      GROUP BY f.food_id
                      ORDER BY total_sold DESC
                      LIMIT 10";
    
    $top_items_stmt = $db->prepare($top_items_sql);
    if (!empty($params)) {
        $top_items_stmt->bind_param($types, ...$params);
    }
    $top_items_stmt->execute();
    $top_items_result = $top_items_stmt->get_result();
    
    $top_items = [];
    while ($row = $top_items_result->fetch_assoc()) {
        $top_items[] = $row;
    }
    
    $report = [
        'summary' => $summary,
        'daily_sales' => $daily_sales,
        'top_items' => $top_items,
        'period' => $period,
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    FoodDeliverySystem::jsonResponse(true, 'Sales report generated', $report);
}
?>