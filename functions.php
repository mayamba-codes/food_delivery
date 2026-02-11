<?php
/**
 * Common Functions and Utilities
 */

require_once 'session.php';

class FoodDeliverySystem {
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('self::sanitizeInput', $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validatePhone($phone) {
        return preg_match('/^[0-9]{10,15}$/', $phone);
    }
    
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    public static function generateOrderId($order_id) {
        return 'ORD' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
    }
    
    public static function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'confirmed' => '<span class="badge badge-info">Confirmed</span>',
            'preparing' => '<span class="badge badge-primary">Preparing</span>',
            'out_for_delivery' => '<span class="badge badge-success">Out for Delivery</span>',
            'delivered' => '<span class="badge badge-success">Delivered</span>',
            'cancelled' => '<span class="badge badge-danger">Cancelled</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
    }
    
    public static function jsonResponse($success, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
    
    public static function logActivity($activity, $user_id = null) {
        global $db;
        
        $user_id = $user_id ?? SessionManager::getUserId();
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $sql = "INSERT INTO activity_logs (user_id, activity, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("isss", $user_id, $activity, $ip_address, $user_agent);
        $stmt->execute();
    }
}
?>