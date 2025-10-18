<?php
// dashboard.php - Add session validation
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Municipal Services</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="navbar.css">
</head>
<body>

    <!-- Include Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2 class="dashboard-title">Welcome to Your Dashboard</h2>
            <p class="dashboard-subtitle">Choose a service to get started</p>
        </div>
        
        <div class="cards-grid">
            <!-- Market Stall Rental Card -->
            <div class="card" onclick="location.href='market_card/market-dashboard.php'">
                <div class="card-icon">🏪</div>
                <h3>Market Stall Rental</h3>
                <p>Apply for market stall rentals and manage your existing stalls</p>
                <div class="card-badge">Available</div>
            </div>

            <!-- Real Property Register and Tax Card -->
            <div class="card" onclick="location.href='rpt_card/rpt_dashboard.php'">
                <div class="card-icon">🏠</div>
                <h3>Real Property Register & Tax</h3>
                <p>Register properties, calculate taxes, and view property records</p>
                <div class="card-badge">Available</div>
            </div>

            <!-- Business Tax Card -->
            <div class="card" onclick="location.href='business-tax/calculate.php'">
                <div class="card-icon">💼</div>
                <h3>Business Tax</h3>
                <p>Calculate business taxes, file returns, and make payments</p>
                <div class="card-badge">Available</div>
            </div>
        </div>
    </div>

</body>
</html>