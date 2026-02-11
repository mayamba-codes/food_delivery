<?php
/**
 * Logout Script - FoodExpress Food Delivery System
 * 
 * This file handles user logout by destroying the session
 * and redirecting to the homepage. It ensures secure session
 * termination following best practices.
 */

// Start session to access session data
session_start();

// Include session manager for additional security functions
require_once 'backend/includes/session.php';

// Log logout activity if user was logged in
if (isset($_SESSION['user_id'])) {
    // Include functions for logging
    require_once 'backend/includes/functions.php';
    
    // Log the logout activity
    FoodDeliverySystem::logActivity("User logged out", $_SESSION['user_id']);
}

// Destroy the session securely using the SessionManager class
SessionManager::destroySession();

// Clear any session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

// Set headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to homepage with a success message parameter
header('Location: index.php?logout=success');
exit();
?>