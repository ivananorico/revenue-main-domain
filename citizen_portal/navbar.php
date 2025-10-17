<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate base path for assets
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path === '/revenue/citizen_portal') {
    $asset_path = '';
} else {
    // Calculate how many levels deep we are
    $levels_deep = substr_count($base_path, '/') - 2; // -2 for /revenue/citizen_portal
    $asset_path = str_repeat('../', $levels_deep);
}

// Handle logout via ?logout=true
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    // Unset all session variables
    $_SESSION = array();
    
    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
    
    // Clear any existing output buffers
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Use JavaScript redirect as fallback
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Logging out...</title>
        <script>
            window.location.href = "' . $asset_path . 'index.php";
        </script>
    </head>
    <body>
        <p>Logging out... <a href="' . $asset_path . 'revenue/citizen_portal/index.php">Click here if not redirected</a></p>
    </body>
    </html>';
    exit();
}

// Get logged-in user name
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Guest';
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
                <a href="?logout=true" class="logout-btn" title="Logout" onclick="return confirmLogout()">
                    <i class="fas fa-sign-out-alt logout-icon"></i>
                    <span class="logout-text">Logout</span>
                </a>
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
        <a href="?logout=true" class="mobile-logout-btn" onclick="return confirmLogout()">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <script>
        // Logout confirmation function
        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

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