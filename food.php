<?php
/**
 * Food Items API Endpoint - FIXED VERSION
 * Place this file in: backend/api/food.php
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

// Handle GET request - No authentication required for viewing menu
if ($method === 'GET') {
    $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $sql = "SELECT f.*, c.category_name 
            FROM food_items f 
            LEFT JOIN categories c ON f.category_id = c.category_id 
            WHERE f.is_available = 1";
    
    $params = [];
    $types = "";
    
    if ($category_id > 0) {
        $sql .= " AND f.category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    
    if (!empty($search)) {
        $sql .= " AND (f.food_name LIKE ? OR f.description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    
    $sql .= " ORDER BY f.food_name ASC";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->getConnection()->query($sql);
    }
    
    $food_items = [];
    while ($row = $result->fetch_assoc()) {
        // Set default image if none
        if (empty($row['image_url'])) {
            $row['image_url'] = '../assets/images/default-food.jpg';
        }
        $food_items[] = $row;
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Food items retrieved', $food_items);
    
} else {
    FoodDeliverySystem::jsonResponse(false, 'Method not allowed');
}
?>