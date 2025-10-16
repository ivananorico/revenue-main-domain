<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle logout via ?logout=true
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
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
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-brand">
                <div class="logo-section">
                    <div class="logo-icon"><i class="fas fa-landmark"></i></div>
                    <div class="brand-text">
                        <h1 class="portal-title">Government Services Portal</h1>
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
                <a href="?logout=true" class="logout-btn" title="Logout">
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
        <a href="?logout=true" class="mobile-logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
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
        });
    </script>
</body>
</html>
