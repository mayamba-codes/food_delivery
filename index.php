<?php
/**
 * Main Entry Point - FoodExpress Food Delivery System
 * 
 * This file serves as the main landing page and handles session-based redirection.
 * It displays the public homepage for guests and redirects logged-in users
 * to their appropriate dashboard or menu page.
 */

// Start session for authentication check
//session_start(); nimewka comment isiwe active

// Include necessary files
require_once 'backend/includes/session.php';  //nime addi coz nimefuta session_start(); juu ya code ya index ili kufanya replacement
require_once 'backend/includes/functions.php';
require_once 'backend/config/database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userRole = $_SESSION['role_id'] ?? null;

// Redirect logged-in users to appropriate pages
if ($isLoggedIn) {
    if ($userRole == 1) { // Admin
        header('Location: admin/dashboard.php');
        exit();
    } else { // Customer
        header('Location: frontend/menu.html');
        exit();
    }
}

// Set page title and description for SEO
$pageTitle = "FoodExpress - Delicious Food Delivery & Ordering System";
$pageDescription = "Order delicious food from the best restaurants in town. Fast delivery, fresh food, and great prices.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-utensils"></i>
                <span>FoodExpress</span>
            </a>
            
            <div class="nav-links">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Home</a>
                <a href="frontend/menu.html"><i class="fas fa-utensils"></i> Menu</a>
                <a href="#features"><i class="fas fa-star"></i> Features</a>
                <a href="#about"><i class="fas fa-info-circle"></i> About</a>
                <a href="#contact"><i class="fas fa-envelope"></i> Contact</a>
                
                <div class="auth-buttons">
                    <a href="frontend/login.html" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="frontend/register.html" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
                </div>
            </div>
            
            <button class="menu-toggle" id="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Delicious Food Delivered to Your Doorstep</h1>
                <p>Order from the best restaurants in town. Fast delivery, fresh food, and great prices.</p>
                <div class="hero-buttons">
                    <a href="frontend/register.html" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i> Get Started
                    </a>
                    <a href="frontend/menu.html" class="btn btn-outline btn-large">
                        <i class="fas fa-utensils"></i> Browse Menu
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <i class="fas fa-users"></i>
                        <div>
                            <h3>1,000+</h3>
                            <p>Happy Customers</p>
                        </div>
                    </div>
                    <div class="stat">
                        <i class="fas fa-utensils"></i>
                        <div>
                        <h3>200+</h3>
                            <p>Food Items</p>
                        </div>
                    </div>
                    <div class="stat">
                        <i class="fas fa-shipping-fast"></i>
                        <div>
                            <h3>30 min</h3>
                            <p>Avg Delivery</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                     alt="Delicious Food" 
                     onerror="this.src='assets/images/default-food.jpg'">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2 class="section-title">Why Choose FoodExpress?</h2>
            <p class="section-subtitle">We provide the best food delivery experience with these amazing features</p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h3>Fast Delivery</h3>
                    <p>Get your food delivered in 30 minutes or less. Our efficient delivery network ensures your food arrives hot and fresh.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Fresh Food</h3>
                    <p>All our partner restaurants use fresh ingredients and follow strict hygiene standards for food preparation.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3>Best Prices</h3>
                    <p>Enjoy great quality food at affordable prices with regular discounts and special offers for our customers.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure Payments</h3>
                    <p>Multiple secure payment options including cash on delivery and online payments with complete safety.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Our customer support team is available round the clock to assist you with any queries or issues.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Easy Ordering</h3>
                    <p>Simple and intuitive interface that makes ordering food quick and convenient from any device.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Ordering food has never been easier. Follow these simple steps:</p>
            
            <div class="steps-container">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Create Account</h3>
                    <p>Sign up for free in less than a minute. No credit card required.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Browse Menu</h3>
                    <p>Explore our wide variety of delicious food items from top restaurants.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Add to Cart</h3>
                    <p>Select your favorite items and add them to your shopping cart.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3>Receive Delivery</h3>
                    <p>Sit back and relax while we deliver your food to your doorstep.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Categories Preview -->
    <section class="categories-preview">
        <div class="container">
            <h2 class="section-title">Popular Categories</h2>
            <p class="section-subtitle">Explore our most popular food categories</p>
            
            <div class="categories-grid" id="categories-preview">
                <!-- Categories will be loaded via JavaScript -->
                <div class="loading-categories">
                    <i class="fas fa-spinner fa-spin"></i> Loading categories...
                </div>
            </div>
            
            <div class="text-center">
                <a href="frontend/menu.html" class="btn btn-primary btn-large">
                    <i class="fas fa-utensils"></i> View Full Menu
                </a>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2 class="section-title">About FoodExpress</h2>
                    <p>FoodExpress is a leading food delivery service dedicated to connecting food lovers with the best restaurants in town. Our mission is to make food ordering convenient, fast, and enjoyable for everyone.</p>
                    <p>With our user-friendly platform, you can discover new restaurants, explore diverse cuisines, and have delicious meals delivered right to your doorstep. We work with verified restaurants to ensure food quality and safety standards.</p>
                    <div class="about-features">
                        <p><i class="fas fa-check-circle"></i> 100% Satisfaction Guarantee</p>
                        <p><i class="fas fa-check-circle"></i> Verified Restaurant Partners</p>
                        <p><i class="fas fa-check-circle"></i> Real-time Order Tracking</p>
                        <p><i class="fas fa-check-circle"></i> Environmentally Friendly Packaging</p>
                    </div>
                </div>
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                         alt="About FoodExpress"
                         onerror="this.src='assets/images/about-food.jpg'">
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">What Our Customers Say</h2>
            <p class="section-subtitle">Don't just take our word for it. Here's what our customers have to say:</p>
            
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"The best food delivery service I've ever used! Fast delivery and the food is always fresh and delicious."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://randomuser.me/api/portraits/women/32.jpg" alt="Sarah Johnson">
                        <div>
                            <h4>Sarah Johnson</h4>
                            <p>Regular Customer</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"I love the variety of restaurants available. The ordering process is so simple and the delivery is always on time."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://randomuser.me/api/portraits/men/54.jpg" alt="Michael Chen">
                        <div>
                            <h4>Michael Chen</h4>
                            <p>Food Enthusiast</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"As a busy professional, FoodExpress has been a lifesaver. Great food delivered right to my office!"</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://randomuser.me/api/portraits/women/67.jpg" alt="Emma Davis">
                        <div>
                            <h4>Emma Davis</h4>
                            <p>Business Executive</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="contact-content">
                <div class="contact-info">
                    <h2 class="section-title">Contact Us</h2>
                    <p>Have questions or feedback? We'd love to hear from you!</p>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Address</h4>
                                <p>123 Food Street, Cityville, ST 12345</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Phone</h4>
                                <p>+1 (555) 123-4567</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email</h4>
                                <p>support@foodexpress.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Business Hours</h4>
                                <p>24/7 - We're always here to serve you</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form">
                    <h3>Send us a Message</h3>
                    <form id="contact-form">
                        <div class="form-group">
                            <input type="text" id="contact-name" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" id="contact-email" placeholder="Your Email" required>
                        </div>
                        <div class="form-group">
                            <textarea id="contact-message" rows="5" placeholder="Your Message" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Order Delicious Food?</h2>
                <p>Join thousands of satisfied customers who trust FoodExpress for their food delivery needs.</p>
                <div class="cta-buttons">
                    <a href="frontend/register.html" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i> Sign Up Now
                    </a>
                    <a href="frontend/menu.html" class="btn btn-outline btn-large">
                        <i class="fas fa-utensils"></i> Order Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="logo">
                        <i class="fas fa-utensils"></i>
                        <span>FoodExpress</span>
                    </div>
                    <p>Delivering happiness and delicious food since 2024. Your satisfaction is our priority.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="index.php"><i class="fas fa-chevron-right"></i> Home</a>
                    <a href="frontend/menu.html"><i class="fas fa-chevron-right"></i> Menu</a>
                    <a href="#features"><i class="fas fa-chevron-right"></i> Features</a>
                    <a href="#about"><i class="fas fa-chevron-right"></i> About Us</a>
                    <a href="#contact"><i class="fas fa-chevron-right"></i> Contact</a>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <a href="#"><i class="fas fa-chevron-right"></i> Terms of Service</a>
                    <a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a>
                    <a href="#"><i class="fas fa-chevron-right"></i> Cookie Policy</a>
                    <a href="#"><i class="fas fa-chevron-right"></i> Refund Policy</a>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Food Street, Cityville</p>
                    <p><i class="fas fa-phone"></i>  255 699222829</p>
                    <p><i class="fas fa-envelope"></i> support@foodexpress.com</p>
                    <p><i class="fas fa-clock"></i> 24/7 Support</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2026 FoodExpress. All rights reserved. | Designed with <i class="fas fa-heart"></i> for food lovers</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
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
            
            // Load categories for preview
            loadCategoriesPreview();
            
            // Handle contact form submission
            const contactForm = document.getElementById('contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const name = document.getElementById('contact-name').value;
                    const email = document.getElementById('contact-email').value;
                    const message = document.getElementById('contact-message').value;
                    
                    // In a real implementation, you would send this to a backend endpoint
                    alert('Thank you for your message, ' + name + '! We will get back to you soon.');
                    contactForm.reset();
                });
            }
            
            // Add animation on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                    }
                });
            }, observerOptions);
            
            // Observe elements for animation
            document.querySelectorAll('.feature-card, .step, .testimonial-card').forEach(el => {
                observer.observe(el);
            });
        });
        
        // Function to load categories for preview
        function loadCategoriesPreview() {
            const container = document.getElementById('categories-preview');
            if (!container) return;
            
            // Sample categories (in real implementation, fetch from API)
            const categories = [
                { id: 1, name: "Pizza", description: "Delicious pizzas", image: "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" },
                { id: 2, name: "Burgers", description: "Juicy burgers", image: "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" },
                { id: 3, name: "Pasta", description: "Italian pasta", image: "https://images.unsplash.com/photo-1563379926898-05f4575a45d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" },
                { id: 4, name: "Salads", description: "Fresh salads", image: "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" }
            ];
            
            setTimeout(() => {
                container.innerHTML = categories.map(category => `
                    <div class="category-card">
                        <div class="category-image">
                            <img src="${category.image}" alt="${category.name}"
                            onerror="this.src='assets/images/default-food.jpg'">
                        </div>
                        <div class="category-content">
                            <h3>${category.name}</h3>
                            <p>${category.description}</p>
                            <a href="frontend/menu.html?category=${category.id}" class="btn btn-outline btn-sm">
                                Explore <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                `).join('');
            }, 1000);
        }
        
        // Check if user is trying to access restricted pages
        function checkPageAccess() {
            const restrictedPages = ['admin/dashboard.php', 'admin/', 'backend/'];
            const currentPage = window.location.pathname;
            
            if (restrictedPages.some(page => currentPage.includes(page))) {
                // In a real implementation, you would check session and redirect
                console.log('Accessing restricted area');
            }
        }
        
        // Initialize page access check
        checkPageAccess();
    </script>
    
    <!-- Additional CSS for this page -->
    <style>
        .hero-buttons {
            display: flex;
            gap: 15px;
            margin: 25px 0;
            flex-wrap: wrap;
        }
        
        .hero-stats {
            display: flex;
            gap: 30px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .hero-stats .stat {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .hero-stats .stat i {
            font-size: 2rem;
            color: #ff6b6b;
        }
        
        .hero-stats .stat h3 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .hero-stats .stat p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .section-subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: #ff6b6b;
            margin-bottom: 15px;
        }
        
        .how-it-works {
            background-color: #f8f9fa;
            padding: 80px 0;
        }
        
        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .step {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: -15px;
            background: #ff6b6b;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .step-icon {
            font-size: 2.5rem;
            color: #ff6b6b;
            margin-bottom: 15px;
        }
        
        .categories-preview {
            padding: 80px 0;
        }
        
        .loading-categories {
            text-align: center;
            padding: 40px;
            color: #666;
            grid-column: 1 / -1;
        }
        
        .category-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .category-image {
            height: 200px;
            overflow: hidden;
        }
        
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .category-card:hover .category-image img {
            transform: scale(1.1);
        }
        
        .category-content {
            padding: 20px;
        }
        
        .about {
            padding: 80px 0;
            background-color: #f8f9fa;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 50px;
            align-items: center;
        }
        
        .about-image img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .about-features {
            margin-top: 20px;
        }
        
        .about-features p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .about-features i {
            color: #4CAF50;
        }
        
        .testimonials {
            padding: 80px 0;
        }
        
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .testimonial-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .testimonial-content {
            position: relative;
            margin-bottom: 20px;
        }
        
        .testimonial-content i {
            color: #ff6b6b;
            font-size: 1.5rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .testimonial-author img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .contact {
            padding: 80px 0;
            background-color: #f8f9fa;
        }
        
        .contact-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 50px;
        }
        
        .contact-details {
            margin-top: 30px;
        }
        
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .contact-item i {
            color: #ff6b6b;
            font-size: 1.2rem;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b6b;
        }
        
        .cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .cta p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            color: #333;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: #ff6b6b;
            color: white;
            transform: translateY(-3px);
        }
        
        .text-center {
            text-align: center;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        /* Animation classes */
        .animated {
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero .container {
                flex-direction: column;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</body>
</html>