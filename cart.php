<?php
/**
 * Shopping Cart API Endpoint - FIXED VERSION
 * Place this file in: backend/api/cart.php
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    FoodDeliverySystem::jsonResponse(false, 'Please login first');
}

$user_id = $_SESSION['user_id'];

switch($method) {
    case 'GET':
        getCartItems($db, $user_id);
        break;
        
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch($action) {
            case 'add':
                addToCart($db, $user_id);
                break;
            case 'update':
                updateCartItem($db, $user_id);
                break;
            case 'clear':
                clearCart($db, $user_id);
                break;
            default:
                FoodDeliverySystem::jsonResponse(false, 'Invalid action');
        }
        break;
        
    case 'DELETE':
        removeFromCart($db, $user_id);
        break;
        
    default:
        FoodDeliverySystem::jsonResponse(false, 'Method not allowed');
}

function getCartItems($db, $user_id) {
    $sql = "SELECT c.cart_id, c.quantity, f.food_id, f.food_name, 
                   f.description, f.price, f.image_url,
                   (c.quantity * f.price) as subtotal
            FROM cart c
            JOIN food_items f ON c.food_id = f.food_id
            WHERE c.user_id = ? AND f.is_available = 1
            ORDER BY c.added_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart_items = [];
    $total = 0;
    $count = 0;
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['image_url'])) {
            $row['image_url'] = '../assets/images/default-food.jpg';
        }
        $cart_items[] = $row;
        $total += $row['subtotal'];
        $count++;
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Cart items retrieved', [
        'items' => $cart_items,
        'total' => $total,
        'count' => $count
    ]);
}

function addToCart($db, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $food_id = isset($data['food_id']) ? intval($data['food_id']) : 0;
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    
    if ($food_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid food item');
    }
    
    // Check if food exists
    $check_sql = "SELECT food_id, price FROM food_items WHERE food_id = ? AND is_available = 1";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bind_param("i", $food_id);
    $check_stmt->execute();
    $food_result = $check_stmt->get_result();
    
    if ($food_result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'Food item not available');
    }
    
    // Check if already in cart
    $cart_sql = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND food_id = ?";
    $cart_stmt = $db->prepare($cart_sql);
    $cart_stmt->bind_param("ii", $user_id, $food_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        // Update quantity
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
        
        if ($update_stmt->execute()) {
            FoodDeliverySystem::jsonResponse(true, 'Cart updated successfully');
        } else {
            FoodDeliverySystem::jsonResponse(false, 'Failed to update cart');
        }
    } else {
        // Add new item
        $insert_sql = "INSERT INTO cart (user_id, food_id, quantity) VALUES (?, ?, ?)";
        $insert_stmt = $db->prepare($insert_sql);
        $insert_stmt->bind_param("iii", $user_id, $food_id, $quantity);
        
        if ($insert_stmt->execute()) {
            FoodDeliverySystem::jsonResponse(true, 'Item added to cart');
        } else {
            FoodDeliverySystem::jsonResponse(false, 'Failed to add to cart');
        }
    }
}

function updateCartItem($db, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $cart_id = isset($data['cart_id']) ? intval($data['cart_id']) : 0;
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    
    if ($cart_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid cart item');
    }
    
    if ($quantity <= 0) {
        // Remove item if quantity is 0
        $delete_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
        $delete_stmt = $db->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        FoodDeliverySystem::jsonResponse(true, 'Item removed from cart');
    } else {
        $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        
        if ($update_stmt->execute()) {
            FoodDeliverySystem::jsonResponse(true, 'Cart updated successfully');
        } else {
            FoodDeliverySystem::jsonResponse(false, 'Failed to update cart');
        }
    }
}

function removeFromCart($db, $user_id) {
    $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
    
    if ($cart_id <= 0) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid cart item');
    }
    
    $sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $cart_id, $user_id);
    
    if ($stmt->execute()) {
        FoodDeliverySystem::jsonResponse(true, 'Item removed from cart');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to remove item');
    }
}

function clearCart($db, $user_id) {
    $sql = "DELETE FROM cart WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        FoodDeliverySystem::jsonResponse(true, 'Cart cleared successfully');
    } else {
        FoodDeliverySystem::jsonResponse(false, 'Failed to clear cart');
    }
}
?>