<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use absolute paths that work from anywhere
$base_url = '/revenue/citizen_portal/';
$logout_path = $base_url . 'logout.php';
$login_path = $base_url . 'index.php';
$asset_path = $base_url;

// Simple session check
$full_name = 'Guest';
$show_logout = false;

if (isset($_SESSION['user_id']) && isset($_SESSION['full_name'])) {
    $full_name = $_SESSION['full_name'];
    $show_logout = true;
}

// Function to add parameters to URL
function buildUrlWithParams($baseUrl) {
    if (empty($_GET)) {
        return $baseUrl;
    }
    
    // Preserve ALL current URL parameters
    return $baseUrl . '?' . http_build_query($_GET);
}

// Build URLs with current parameters
$logout_url = buildUrlWithParams($logout_path);
$login_url = buildUrlWithParams($login_path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Services Portal</title>
    <link rel="stylesheet" href="<?= $asset_path ?>navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-brand">
                <div class="logo-section">
                    <div class="logo-image">
                        <img src="<?= $asset_path ?>images/GSM_logo.png" alt="GoServePH Logo" class="logo-img">
                    </div>
                    <div class="brand-text">
                        <h1 class="portal-title">GoServePH</h1>
                        <p class="portal-subtitle">Municipal Services & Administration</p>
                    </div>
                </div>
            </div>

            <!-- Right Section -->
            <div class="navbar-user-menu">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr(htmlspecialchars($full_name), 0, 1)) ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($full_name) ?></span>
                </div>
                
                <!-- Only show logout button if user is logged in -->
                <?php if ($show_logout): ?>
                <a href="<?= $logout_url ?>" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt logout-icon"></i>
                    <span class="logout-text">Logout</span>
                </a>
                <?php else: ?>
                <a href="<?= $login_url ?>" class="login-btn" title="Login">
                    <i class="fas fa-sign-in-alt login-icon"></i>
                    <span class="login-text">Login</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <button class="mobile-menu-toggle" aria-label="Toggle navigation menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu">
        <div class="mobile-user-info">
            <div class="user-avatar">
                <?= strtoupper(substr(htmlspecialchars($full_name), 0, 1)) ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($full_name) ?></span>
            </div>
        </div>
        
        <!-- Only show logout in mobile menu if user is logged in -->
        <?php if ($show_logout): ?>
        <a href="<?= $logout_url ?>" class="mobile-logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        <?php else: ?>
        <a href="<?= $login_url ?>" class="mobile-login-btn">
            <i class="fas fa-sign-in-alt"></i> Login
        </a>
        <?php endif; ?>
    </div>

    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('.mobile-menu-toggle');
            const menu = document.querySelector('.mobile-menu');
            const body = document.body;

            if (toggle && menu) {
                toggle.addEventListener('click', () => {
                    menu.classList.toggle('active');
                    body.style.overflow = menu.classList.contains('active') ? 'hidden' : '';
                });

                document.addEventListener('click', (e) => {
                    if (!menu.contains(e.target) && !toggle.contains(e.target)) {
                        menu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            }

            // Add keyboard accessibility for mobile menu
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && menu.classList.contains('active')) {
                    menu.classList.remove('active');
                    body.style.overflow = '';
                }
            });
        });
    </script>
</body>
</html>