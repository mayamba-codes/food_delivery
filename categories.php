<?php
/**
 * Categories API Endpoint
 * Place this file in: backend/api/categories.php
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

if ($method === 'GET') {
    $sql = "SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name";
    $result = $db->getConnection()->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Categories retrieved', $categories);
} else {
    FoodDeliverySystem::jsonResponse(false, 'Method not allowed');
}
?>