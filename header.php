<?php
/**
 * Header Component
 * Reusable header/navigation for both frontend and admin pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication status
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
$username = $_SESSION['username'] ?? '';
$fullName = $_SESSION['full_name'] ?? '';

// Determine current page for active link highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

// Function to check if a link should be active
function isActive($pageName, $currentPage) {
    return $pageName === $currentPage ? 'active' : '';
}
?>

<!-- Header/Navigation Component -->
<header class="header">
    <nav class="navbar">
        <div class="container">
            <!-- Logo -->
            <a href="<?php echo $isAdmin ? 'dashboard.php' : '../index.php'; ?>" class="logo">
                <i class="fas fa-utensils"></i>
                <span>FoodExpress</span>
            </a>
            
            <!-- Navigation Links -->
            <div class="nav-links">
                <?php if ($isAdmin): ?>
                    <!-- Admin Navigation -->
                    <a href="dashboard.php" class="<?php echo isActive('dashboard.php', $currentPage); ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="manage-food.php" class="<?php echo isActive('manage-food.php', $currentPage); ?>">
                        <i class="fas fa-utensils"></i> Manage Food
                    </a>
                    <a href="manage-orders.php" class="<?php echo isActive('manage-orders.php', $currentPage); ?>">
                        <i class="fas fa-shopping-cart"></i> Manage Orders
                    </a>
                    <a href="manage-users.php" class="<?php echo isActive('manage-users.php', $currentPage); ?>">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="reports.php" class="<?php echo isActive('reports.php', $currentPage); ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    
                <?php else: ?>
                    <!-- Customer Navigation -->
                    <a href="../index.php" class="<?php echo isActive('index.php', $currentPage); ?>">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="menu.html" class="<?php echo isActive('menu.html', $currentPage); ?>">
                        <i class="fas fa-utensils"></i> Menu
                    </a>
                    <a href="cart.html" class="<?php echo isActive('cart.html', $currentPage); ?>">
                        <i class="fas fa-shopping-cart"></i> Cart 
                        <span id="cart-count" class="cart-count">0</span>
                    </a>
                    <a href="order-history.html" class="<?php echo isActive('order-history.html', $currentPage); ?>">
                        <i class="fas fa-history"></i> Orders
                    </a>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Logged In User Menu -->
                        <div class="user-menu">
                            <div class="user-dropdown">
                                <button class="user-btn">
                                    <i class="fas fa-user-circle"></i>
                                    <span id="username-display"><?php echo htmlspecialchars($username); ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <div class="dropdown-content">
                                    <a href="profile.html">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                    <a href="order-history.html">
                                        <i class="fas fa-history"></i> Order History
                                    </a>
                                    <a href="#" id="logout-btn">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Login/Register Buttons -->
                        <div class="auth-buttons">
                            <a href="login.html" class="btn btn-outline">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            <a href="register.html" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Admin Logout (if admin is logged in) -->
                <?php if ($isAdmin): ?>
                    <div class="user-menu">
                        <div class="user-dropdown">
                            <button class="user-btn">
                                <i class="fas fa-user-shield"></i>
                                <span id="username-display"><?php echo htmlspecialchars($username); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-content">
                                <a href="../index.php" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> View Site
                                </a>
                                <a href="#" id="logout-btn">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <button class="menu-toggle" id="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
</header>

<!-- Include JavaScript for header functionality -->
<script>
    // Initialize mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menu-toggle');
        const navLinks = document.querySelector('.nav-links');
        
        if (menuToggle && navLinks) {
            menuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('active');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
                    navLinks.classList.remove('active');
                }
            });
        }
        
        // Logout functionality
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // For admin pages, use admin logout
                if (window.location.pathname.includes('/admin/')) {
                    window.location.href = '../logout.php';
                } else {
                    // For frontend pages, use AJAX logout
                    fetch('../backend/api/auth.php?action=logout', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '../index.php';
                        }
                    })
                    .catch(error => {
                        console.error('Error logging out:', error);
                        window.location.href = '../logout.php';
                    });
                }
            });
        }
        
        // Update cart count if on customer pages
        if (!window.location.pathname.includes('/admin/')) {
            updateCartCount();
        }
    });
    
    // Function to update cart count
    async function updateCartCount() {
        try {
            const response = await fetch('../backend/api/cart.php');
            const data = await response.json();
            
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                if (data.success) {
                    cartCount.textContent = data.data.count || 0;
                    cartCount.style.display = data.data.count > 0 ? 'inline-flex' : 'none';
                } else {
                    cartCount.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error updating cart count:', error);
        }
    }
</script>

<!-- Add header-specific styles -->
<style>
    /* Header/Navigation Styles */
    .header {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .navbar .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.5rem;
        font-weight: 700;
        color: #ff6b6b;
        text-decoration: none;
    }
    
    .logo i {
        font-size: 1.8rem;
    }
    
    .nav-links {
        display: flex;
        align-items: center;
        gap: 25px;
    }
    
    .nav-links a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 5px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .nav-links a:hover,
    .nav-links a.active {
        background-color: #fff5f5;
        color: #ff6b6b;
    }
    
    .cart-count {
        background-color: #ff6b6b;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        margin-left: 5px;
    }
    
    .auth-buttons {
        display: flex;
        gap: 10px;
    }
    
    .user-menu {
        display: block;
    }
    
    .user-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px 15px;
        border-radius: 5px;
        background-color: #f8f9fa;
        color: #333;
        font-weight: 500;
    }
    
    .user-dropdown {
        position: relative;
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: white;
        min-width: 200px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        border-radius: 5px;
        z-index: 1000;
    }
    
    .dropdown-content a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        text-decoration: none;
        color: #333;
        transition: background-color 0.3s;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .dropdown-content a:last-child {
        border-bottom: none;
    }
    
    .dropdown-content a:hover {
        background-color: #f8f9fa;
        color: #ff6b6b;
    }
    
    .user-dropdown:hover .dropdown-content {
        display: block;
    }
    
    .menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #333;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border-radius: 5px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        gap: 8px;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    .btn-primary {
        background-color: #ff6b6b;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #ff5252;
        transform: translateY(-2px);
    }
    
    .btn-outline {
        background-color: transparent;
        border: 2px solid #ff6b6b;
        color: #ff6b6b;
    }
    
    .btn-outline:hover {
        background-color: #ff6b6b;
        color: white;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            flex-direction: column;
            padding: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            gap: 10px;
        }
        
        .nav-links.active {
            display: flex;
        }
        
        .menu-toggle {
            display: block;
        }
        
        .auth-buttons {
            flex-direction: column;
            width: 100%;
        }
        
        .auth-buttons .btn {
            width: 100%;
        }
        
        .user-dropdown .dropdown-content {
            position: static;
            box-shadow: none;
            border: 1px solid #f0f0f0;
            margin-top: 10px;
        }
    }
    
    @media (max-width: 480px) {
        .navbar .container {
            padding: 10px 15px;
        }
        
        .logo span {
            font-size: 1.2rem;
        }
        
        .logo i {
            font-size: 1.5rem;
        }
    }
</style>