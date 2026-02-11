<?php
/**
 * Orders API Endpoints
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
//session_start();
if (!SessionManager::isLoggedIn()) {
    FoodDeliverySystem::jsonResponse(false, 'Please login to access orders');
}

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();
$user_id = SessionManager::getUserId();
$isAdmin = SessionManager::isAdmin();

switch($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'stats':
                if ($isAdmin) {
                    getAdminOrderStats($db);
                } else {
                    getUserOrderStats($db, $user_id);
                }
                break;
            default:
                if ($isAdmin) {
                    getAllOrders($db);
                } else {
                    getUserOrders($db, $user_id);
                }
        }
        break;
        
    case 'POST':
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'create':
                createOrder($db, $user_id);
                break;
            case 'cancel':
                cancelOrder($db, $user_id, $isAdmin);
                break;
            case 'update_status':
                if ($isAdmin) {
                    updateOrderStatus($db);
                } else {
                    FoodDeliverySystem::jsonResponse(false, 'Unauthorized access');
                }
                break;
            case 'reorder':
                reorderItems($db, $user_id);
                break;
            default:
                FoodDeliverySystem::jsonResponse(false, 'Invalid action');
        }
        break;
        
    default:
        FoodDeliverySystem::jsonResponse(false, 'Method not allowed');
}

function getUserOrders($db, $user_id) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    $time_filter = $_GET['time'] ?? 'all';
    $offset = ($page - 1) * $limit;
    
    // Build base query
    $sql = "SELECT o.*, COUNT(oi.order_item_id) as item_count 
            FROM orders o 
            LEFT JOIN order_items oi ON o.order_id = oi.order_id 
            WHERE o.user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    // Add status filter
    if ($status && $status !== '') {
        $sql .= " AND o.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add search filter
    if ($search) {
        $sql .= " AND (o.order_id LIKE ? OR o.delivery_address LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    
    // Add time filter
    if ($time_filter !== 'all') {
        $date_condition = '';
        switch($time_filter) {
            case 'week':
                $date_condition = "DATE(o.order_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "DATE(o.order_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case '3months':
                $date_condition = "DATE(o.order_date) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $date_condition = "DATE(o.order_date) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
        }
        if ($date_condition) {
            $sql .= " AND $date_condition";
        }
    }
    
    // Group and count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.user_id = ?";
    $count_params = [$user_id];
    $count_types = "i";
    
    if ($status && $status !== '') {
        $count_sql .= " AND o.status = ?";
        $count_params[] = $status;
        $count_types .= "s";
    }
    
    if ($time_filter !== 'all') {
        if ($date_condition) {
            $count_sql .= " AND $date_condition";
        }
    }
    
    // Get total count
    $count_stmt = $db->prepare($count_sql);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'] ?? 0;
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
        // Get order items
        $items_sql = "SELECT oi.*, f.food_name, f.image_url, f.price 
                     FROM order_items oi 
                     JOIN food_items f ON oi.food_id = f.food_id 
                     WHERE oi.order_id = ?";
        $items_stmt = $db->prepare($items_sql);
        $items_stmt->bind_param("i", $row['order_id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $items = [];
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
        
        $row['items'] = $items;
        $orders[] = $row;
    }
    
    $pagination = [
        'current_page' => $page,
        'per_page' => $limit,
        'total_orders' => $total_count,
        'total_pages' => ceil($total_count / $limit)
    ];
    
    FoodDeliverySystem::jsonResponse(true, 'Orders retrieved', [
        'orders' => $orders,
        'pagination' => $pagination
    ]);
}

function getAllOrders($db) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    
    $offset = ($page - 1) * $limit;
    
    // Build base query
    $sql = "SELECT o.*, u.username, u.full_name, u.email, 
                   COUNT(oi.order_item_id) as item_count 
            FROM orders o 
            JOIN users u ON o.user_id = u.user_id 
            LEFT JOIN order_items oi ON o.order_id = oi.order_id";
    
    $where_clauses = [];
    $params = [];
    $types = "";
    
    // Add status filter
    if ($status && $status !== '') {
        $where_clauses[] = "o.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add search filter
    if ($search) {
        $where_clauses[] = "(o.order_id LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ssss";
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Get total count
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
    
    $pagination = [
        'current_page' => $page,
        'per_page' => $limit,
        'total_orders' => $total_count,
        'total_pages' => ceil($total_count / $limit)
    ];
    
    FoodDeliverySystem::jsonResponse(true, 'All orders retrieved', [
        'orders' => $orders,
        'pagination' => $pagination
    ]);
}
function createOrder($db, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['delivery_address', 'contact_number', 'full_name', 'email', 'payment_method'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            FoodDeliverySystem::jsonResponse(false, "$field is required");
        }
    }
    
    // Get cart items
    $cart_sql = "SELECT c.cart_id, c.food_id, c.quantity, f.price, f.food_name 
                 FROM cart c 
                 JOIN food_items f ON c.food_id = f.food_id 
                 WHERE c.user_id = ? AND f.is_available = 1";
    
    $cart_stmt = $db->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Your cart is empty');
    }
    
    // Calculate total amount
    $total_amount = 0;
    $cart_items = [];
    
    while ($item = $cart_result->fetch_assoc()) {
        $subtotal = $item['price'] * $item['quantity'];
        $total_amount += $subtotal;
        $cart_items[] = $item;
    }
    
    // Add delivery fee and tax
    $delivery_fee = 2.99;
    $tax = $total_amount * 0.08;
    $grand_total = $total_amount + $delivery_fee + $tax;
    
    // Start transaction
    $db->getConnection()->begin_transaction();
    
    try {
        // Create order
        $order_sql = "INSERT INTO orders (user_id, total_amount, status, delivery_address, 
                      contact_number, payment_method, special_instructions) 
                      VALUES (?, ?, 'pending', ?, ?, ?, ?)";
        
        $order_stmt = $db->prepare($order_sql);
        $order_stmt->bind_param(
            "idssss",
            $user_id,
            $grand_total,
            $data['delivery_address'],
            $data['contact_number'],
            $data['payment_method'],
            $data['special_instructions'] ?? ''
        );
        
        if (!$order_stmt->execute()) {
            throw new Exception('Failed to create order: ' . $order_stmt->error);
        }
        
        $order_id = $db->getLastInsertId();
        
        // Create order items
        $item_sql = "INSERT INTO order_items (order_id, food_id, quantity, price, subtotal) 
                     VALUES (?, ?, ?, ?, ?)";
        $item_stmt = $db->prepare($item_sql);
        
        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $item_stmt->bind_param(
                "iiidd",
                $order_id,
                $item['food_id'],
                $item['quantity'],
                $item['price'],
                $subtotal
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception('Failed to add order items');
            }
        }
        
        // Clear cart
        $clear_cart_sql = "DELETE FROM cart WHERE user_id = ?";
        $clear_cart_stmt = $db->prepare($clear_cart_sql);
        $clear_cart_stmt->bind_param("i", $user_id);
        
        if (!$clear_cart_stmt->execute()) {
            throw new Exception('Failed to clear cart');
        }
        
        // Commit transaction
        $db->getConnection()->commit();
        
        // Log activity
        FoodDeliverySystem::logActivity("Created order #$order_id", $user_id);
        
        FoodDeliverySystem::jsonResponse(true, 'Order created successfully', [
            'order_id' => $order_id,
            'total_amount' => $grand_total,
            'message' => 'Your order has been placed successfully!'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->getConnection()->rollback();
        FoodDeliverySystem::jsonResponse(false, $e->getMessage());
    }
}

function cancelOrder($db, $user_id, $isAdmin) {
    $order_id = intval($_GET['order_id'] ?? 0);
    
    if ($order_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid order ID');
    }
    
    // Check if order exists and user has permission
    $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Order not found');
    }
    
    $order = $check_result->fetch_assoc();
    
    // Check permissions
    if (!$isAdmin && $order['user_id'] != $user_id) {
        FoodDeliverySystem::jsonResponse(false, 'You do not have permission to cancel this order');
    }
    
    // Check if order can be cancelled
    if ($order['status'] == 'delivered' || $order['status'] == 'cancelled') {
        FoodDeliverySystem::jsonResponse(false, 'Cannot cancel this order. Current status: ' . $order['status']);
    }
    
    // Update order status
    $update_sql = "UPDATE orders SET status = 'cancelled' WHERE order_id = ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->bind_param("i", $order_id);
    
    if ($update_stmt->execute()) {
        FoodDeliverySystem::logActivity("Cancelled order #$order_id", $user_id);
        FoodDeliverySystem::jsonResponse(true, 'Order cancelled successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to cancel order');
    }
}

function updateOrderStatus($db) {
    $order_id = intval($_GET['order_id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    $status = $data['status'] ?? '';
    
    if ($order_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid order ID');
    }
    
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
    
    if (!in_array($status, $valid_statuses)) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid status');
    }
    
    // Check if order exists
    $check_sql = "SELECT order_id FROM orders WHERE order_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Order not found');
    }
    
    // Update order status
    $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->bind_param("si", $status, $order_id);
    
    if ($update_stmt->execute()) {
        FoodDeliverySystem::logActivity("Updated order #$order_id status to $status");
        FoodDeliverySystem::jsonResponse(true, 'Order status updated successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to update order status');
    }
}

function reorderItems($db, $user_id) {
    $order_id = intval($_GET['order_id'] ?? 0);
    
    if ($order_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid order ID');
    }
    
    // Check if order belongs to user
    $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Order not found');
    }
    
    $order = $check_result->fetch_assoc();
    
    if ($order['user_id'] != $user_id) {
        FoodDeliverySystem::jsonResponse(false, 'You do not have permission to reorder this order');
    }
    
    // Get order items
    $items_sql = "SELECT oi.food_id, oi.quantity, f.food_name, f.price, f.is_available 
                  FROM order_items oi 
                  JOIN food_items f ON oi.food_id = f.food_id 
                  WHERE oi.order_id = ?";
    
    $items_stmt = $db->prepare($items_sql);
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $added_count = 0;
    $unavailable_items = [];
    
    while ($item = $items_result->fetch_assoc()) {
        if ($item['is_available'] == 1) {
            // Check if item already in cart
            $check_cart_sql = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND food_id = ?";
            $check_cart_stmt = $db->prepare($check_cart_sql);
            $check_cart_stmt->bind_param("ii", $user_id, $item['food_id']);
            $check_cart_stmt->execute();
            $cart_result = $check_cart_stmt->get_result();
            
            if ($cart_result->num_rows > 0) {
                // Update quantity
                $cart_item = $cart_result->fetch_assoc();
                $new_quantity = $cart_item['quantity'] + $item['quantity'];
                
                $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
                $update_stmt->execute();
            } else {
                // Add to cart
                $insert_sql = "INSERT INTO cart (user_id, food_id, quantity) VALUES (?, ?, ?)";
                $insert_stmt = $db->prepare($insert_sql);
                $insert_stmt->bind_param("iii", $user_id, $item['food_id'], $item['quantity']);
                $insert_stmt->execute();
            }
            
            $added_count++;
        } else {
            $unavailable_items[] = $item['food_name'];
        }
    }
    
    FoodDeliverySystem::logActivity("Reordered items from order #$order_id", $user_id);
    
    $message = "Added $added_count item(s) to cart";
    if (!empty($unavailable_items)) {
        $message .= ". " . count($unavailable_items) . " item(s) were unavailable: " . implode(', ', $unavailable_items);
    }
    
    FoodDeliverySystem::jsonResponse(true, $message, [
        'added_count' => $added_count,
        'unavailable_count' => count($unavailable_items)
    ]);
}

function getUserOrderStats($db, $user_id) {
    $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN status IN ('pending', 'confirmed', 'preparing', 'out_for_delivery') THEN 1 ELSE 0 END) as active_orders,
                COALESCE(SUM(total_amount), 0) as total_spent
            FROM orders 
            WHERE user_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = $result->fetch_assoc();
    
    FoodDeliverySystem::jsonResponse(true, 'Order statistics retrieved', $stats);
}

function getAdminOrderStats($db) {
    $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(AVG(CASE WHEN status = 'delivered' THEN total_amount ELSE NULL END), 0) as avg_order_value
            FROM orders";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = $result->fetch_assoc();
    
    // Get today's stats
    $today_sql = "SELECT 
                    COUNT(*) as today_orders,
                    COALESCE(SUM(total_amount), 0) as today_revenue
                  FROM orders 
                  WHERE DATE(order_date) = CURDATE()";
    
    $today_stmt = $db->prepare($today_sql);
    $today_stmt->execute();
    $today_result = $today_stmt->get_result();
    $today_stats = $today_result->fetch_assoc();
    
    $stats = array_merge($stats, $today_stats);
    
    FoodDeliverySystem::jsonResponse(true, 'Admin order statistics retrieved', $stats);
}
?>