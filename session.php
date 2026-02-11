<?php
/**
 * Session Management and Authentication
 * FIXED VERSION - Ensures session is properly started
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class SessionManager {
    
    public static function startSession($userData) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['role_id'] = $userData['role_id'];
        $_SESSION['full_name'] = $userData['full_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return true;
    }
    
    public static function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return self::isLoggedIn() && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
    }
    
    public static function isCustomer() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return self::isLoggedIn() && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;
    }
    
    public static function getUserId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserRole() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['role_id'] ?? null;
    }
    
    public static function destroySession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ../frontend/login.html');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ../frontend/login.html');
            exit();
        }
    }
    
    public static function requireCustomer() {
        self::requireLogin();
        if (!self::isCustomer()) {
            header('Location: ../admin/dashboard.php');
            exit();
        }
    }
}
?>