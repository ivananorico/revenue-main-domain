<?php
session_start();
require_once '../db/user_db.php';

$login_message = '';
$register_message = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_email'])) {
    $email = trim($_POST['login_email']);
    $password = $_POST['login_password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        header('Location: dashboard.php');
        exit;
    } else {
        $login_message = "Invalid email or password!";
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_email'])) {
    $full_name = trim($_POST['register_full_name']);
    $email = trim($_POST['register_email']);
    $password_hash = password_hash($_POST['register_password'], PASSWORD_BCRYPT);

    // Check if email exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $register_message = "Email already exists!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
        if ($stmt->execute([$full_name, $email, $password_hash])) {
            $register_message = "Registration successful! You can now login.";
            // Auto-switch back to login form after successful registration
            echo '<script>document.addEventListener("DOMContentLoaded", function() { showLoginForm(); });</script>';
        } else {
            $register_message = "Registration failed!";
        }
    }   
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Citizen Portal</title>
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-landmark"></i>
                    <h1>Government Services Portal</h1>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="main-content">
            <div class="services-info">
                <h2>Access Government Services</h2>
                <p class="services-intro">Login to manage your government services and obligations in one convenient place.</p>
                
                <div class="service-cards">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="service-content">
                            <h4>Property Tax</h4>
                            <p>Register your property and manage your real estate tax payments online.</p>
                        </div>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="service-content">
                            <h4>Business Registration</h4>
                            <p>Register your business and manage business tax obligations efficiently.</p>
                        </div>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="service-content">
                            <h4>Market Stall Rental</h4>
                            <p>Rent a stall in government-managed markets and manage your rental agreements.</p>
                        </div>
                    </div>
                </div>
                
                <div class="benefits-section">
                    <h3>Benefits of our portal</h3>
                    <div class="benefits-grid">
                        <div class="benefit-item">
                            <i class="fas fa-check-circle"></i>
                            <span>24/7 access to services</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Secure data protection</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Real-time status updates</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Digital payment options</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="auth-section">
                <div class="auth-container">
                    <!-- Login Form -->
                    <div class="auth-form login-form active" id="login-form">
                        <div class="auth-card">
                            <h2>Login to Your Account</h2>
                            <?php if ($login_message): ?>
                                <div class="alert alert-danger"><?= $login_message ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" class="auth-form-content">
                                <input type="hidden" name="login_form" value="1">
                                <div class="form-group">
                                    <label for="login_email">Email Address</label>
                                    <input type="email" id="login_email" name="login_email" class="form-control" placeholder="Enter your email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="login_password">Password</label>
                                    <input type="password" id="login_password" name="login_password" class="form-control" placeholder="Enter your password" required>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Login</button>
                                </div>
                            </form>
                            
                            <div class="auth-links">
                                <div class="register-link">
                                    <p>Don't have an account? <a href="#" id="show-register">Register here</a></p>
                                </div>
                                
                                <div class="forgot-password">
                                    <a href="forgot-password.php">Forgot your password?</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Registration Form -->
                    <div class="auth-form register-form" id="register-form">
                        <div class="auth-card">
                            <h2>Create Account</h2>
                            <?php if ($register_message): ?>
                                <div class="alert <?= strpos($register_message, 'successful') !== false ? 'alert-success' : 'alert-danger' ?>"><?= $register_message ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" class="auth-form-content">
                                <input type="hidden" name="register_form" value="1">
                                <div class="form-group">
                                    <label for="register_full_name">Full Name</label>
                                    <input type="text" id="register_full_name" name="register_full_name" class="form-control" placeholder="Enter your full name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="register_email">Email Address</label>
                                    <input type="email" id="register_email" name="register_email" class="form-control" placeholder="Enter your email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="register_password">Password</label>
                                    <input type="password" id="register_password" name="register_password" class="form-control" placeholder="Create a password" required>
                                    <small class="form-text">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Register</button>
                                </div>
                            </form>
                            
                            <div class="auth-links">
                                <div class="login-link">
                                    <p>Already have an account? <a href="#" id="show-login">Login here</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Government Services</h4>
                    <ul>
                        <li><a href="#">Property Tax</a></li>
                        <li><a href="#">Business Registration</a></li>
                        <li><a href="#">Market Stall Rental</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Help & Support</h4>
                    <ul>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Service Status</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Accessibility</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2023 Government Services Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Form switching functionality
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        const showRegisterBtn = document.getElementById('show-register');
        const showLoginBtn = document.getElementById('show-login');
        const authContainer = document.querySelector('.auth-container');

        function showRegisterForm() {
            loginForm.classList.remove('active');
            registerForm.classList.add('active');
            authContainer.classList.add('show-register');
        }

        function showLoginForm() {
            registerForm.classList.remove('active');
            loginForm.classList.add('active');
            authContainer.classList.remove('show-register');
        }

        showRegisterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showRegisterForm();
        });

        showLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showLoginForm();
        });

        // Show register form if there's a register message (after form submission with error)
        <?php if ($register_message && strpos($register_message, 'successful') === false): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showRegisterForm();
            });
        <?php endif; ?>
    </script>
</body>
</html>