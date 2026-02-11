<?php
/**
 * Authentication API Endpoints
 * FIXED VERSION - Properly handles POST requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = new Database();

// Debug - log the request
error_log("Auth.php called with method: " . $method);
error_log("GET params: " . print_r($_GET, true));
error_log("POST data: " . file_get_contents('php://input'));

switch($method) {
    case 'POST':
        // Check if action is in GET or POST
        $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
        
        error_log("Auth action: " . $action);
        
        switch($action) {
            case 'login':
                loginUser($db);
                break;
            case 'register':
                registerUser($db);
                break;
            case 'logout':
                logoutUser();
                break;
            case 'check':
                checkSession();
                break;
            default:
                // If no action specified, try to determine from request
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['username']) && isset($input['password'])) {
                    loginUser($db);
                } else {
                    FoodDeliverySystem::jsonResponse(false, 'Invalid action specified');
                }
        }
        break;
        
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'check':
                checkSession();
                break;
            case 'get_user':
                getUserData($db);
                break;
            default:
                FoodDeliverySystem::jsonResponse(false, 'Invalid action');
        }
        break;
        
    default:
        FoodDeliverySystem::jsonResponse(false, 'Method not allowed. Use POST for login/register, GET for session check');
}

function loginUser($db) {
    error_log("loginUser function called");
    
    // Try to get JSON input first
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If JSON failed, try POST form data
    if (!$input) {
        $input = $_POST;
    }
    
    error_log("Login input: " . print_r($input, true));
    
    $username = isset($input['username']) ? FoodDeliverySystem::sanitizeInput($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    
    if (empty($username) || empty($password)) {
        FoodDeliverySystem::jsonResponse(false, 'Username and password are required');
    }
    
    // Get user data - check both username and email
    $sql = "SELECT user_id, username, email, password, full_name, role_id, is_active 
            FROM users 
            WHERE username = ? OR email = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("User not found: " . $username);
        FoodDeliverySystem::jsonResponse(false, 'Invalid username or password');
    }
    
    $user = $result->fetch_assoc();
    error_log("User found: " . print_r($user, true));
    
    // Check if account is active
    if (!$user['is_active']) {
        FoodDeliverySystem::jsonResponse(false, 'Account is deactivated. Contact administrator.');
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Password verification failed");
        FoodDeliverySystem::jsonResponse(false, 'Invalid username or password');
    }
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    error_log("Login successful for user: " . $user['username']);
    
    // Log activity
    if (function_exists('FoodDeliverySystem::logActivity')) {
        FoodDeliverySystem::logActivity("User logged in", $user['user_id']);
    }
    
    FoodDeliverySystem::jsonResponse(true, 'Login successful', [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role_id' => $user['role_id']
    ]);
}

function registerUser($db) {
    error_log("registerUser function called");
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    error_log("Register input: " . print_r($input, true));
    
    // Sanitize inputs
    $username = isset($input['username']) ? FoodDeliverySystem::sanitizeInput($input['username']) : '';
    $email = isset($input['email']) ? FoodDeliverySystem::sanitizeInput($input['email']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $full_name = isset($input['full_name']) ? FoodDeliverySystem::sanitizeInput($input['full_name']) : '';
    $phone = isset($input['phone']) ? FoodDeliverySystem::sanitizeInput($input['phone']) : '';
    $address = isset($input['address']) ? FoodDeliverySystem::sanitizeInput($input['address']) : '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        FoodDeliverySystem::jsonResponse(false, 'All fields are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        FoodDeliverySystem::jsonResponse(false, 'Invalid email format');
    }
    
    if (strlen($password) < 6) {
        FoodDeliverySystem::jsonResponse(false, 'Password must be at least 6 characters');
    }
    
    // Check if user already exists
    $sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        // Check which one exists
        $check_username = "SELECT user_id FROM users WHERE username = ?";
        $stmt = $db->prepare($check_username);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            FoodDeliverySystem::jsonResponse(false, 'Username already exists');
        } else {
            FoodDeliverySystem::jsonResponse(false, 'Email already registered');
        }
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user (default role_id = 2 for customer)
    $role_id = 2;
    $sql = "INSERT INTO users (username, email, password, full_name, phone, address, role_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssssssi", $username, $email, $hashed_password, $full_name, $phone, $address, $role_id);
    
    if ($stmt->execute()) {
        $user_id = $db->getLastInsertId();
        error_log("User registered successfully: " . $username . " with ID: " . $user_id);
        FoodDeliverySystem::jsonResponse(true, 'Registration successful! Please login.');
    } else {
        error_log("Registration failed: " . $stmt->error);
        FoodDeliverySystem::jsonResponse(false, 'Registration failed: ' . $stmt->error);
    }
}

function logoutUser() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log activity if user was logged in
    if (isset($_SESSION['user_id']) && function_exists('FoodDeliverySystem::logActivity')) {
        FoodDeliverySystem::logActivity("User logged out", $_SESSION['user_id']);
    }
    
    // Destroy session
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    FoodDeliverySystem::jsonResponse(true, 'Logout successful');
}

function checkSession() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        FoodDeliverySystem::jsonResponse(true, 'Session active', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
            'logged_in' => true
        ]);
    } else {
        FoodDeliverySystem::jsonResponse(false, 'No active session', null);
    }
}

function getUserData($db) {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        FoodDeliverySystem::jsonResponse(false, 'Not authenticated');
    }
    
    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT user_id, username, email, full_name, phone, address, created_at 
            FROM users WHERE user_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        FoodDeliverySystem::jsonResponse(false, 'User not found');
    }
    
    $user = $result->fetch_assoc();
    FoodDeliverySystem::jsonResponse(true, 'User data retrieved', $user);
}
?>