<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Guest';
$user_id = $_SESSION['user_id'];

// Database connection
require_once '../../db/Market/market_db.php';

// Get the latest application ID for this user
$application_id = null;
$has_application = false;

try {
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? ORDER BY application_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($application) {
        $application_id = $application['id'];
        $has_application = true;
    }
} catch (PDOException $e) {
    // Handle error silently or log it
    error_log("Database error: " . $e->getMessage());
}

// Set correct paths for navbar
$asset_path = '../';
$logout_path = '../logout.php';
$login_path = '../index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Portal - Municipal Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
</head>
<body class="bg-gray-50">

    <?php 
    // Include navbar with correct paths
    include '../navbar.php'; 
    ?>

    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Market Stall Portal</h1>
            <p class="text-xl text-gray-600">Welcome, <?= htmlspecialchars($full_name) ?>! Manage your market stall activities.</p>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            
            <!-- Apply for Stall -->
            <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">📋</span>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Apply for Stall</h3>
                    <p class="text-gray-600 mb-6">Submit new application for market stall rental with complete requirements</p>
                    <button onclick="location.href='../../market_portal/market_portal.php'" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                        Apply Now
                    </button>
                </div>
            </div>

            <!-- View Documents -->
            <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">📄</span>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">View Documents</h3>
                    <p class="text-gray-600 mb-6">Access your application documents, permits, and rental agreements</p>
                    
                    <?php if ($has_application): ?>
                        <button onclick="location.href='view_documents/view_documents.php?application_id=<?= $application_id ?>'" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                            View Documents
                        </button>
                    <?php else: ?>
                        <button onclick="alert('No application found. Please apply for a stall first.')" 
                                class="w-full bg-gray-400 cursor-not-allowed text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                            No Application Found
                        </button>
                        <p class="text-sm text-gray-500 mt-2">Apply for a stall first to view documents</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pay Rent -->
            <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">💰</span>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Pay Rent</h3>
                    <p class="text-gray-600 mb-6">Pay your monthly stall rental fees and view payment history</p>
                    
                    <?php if ($has_application): ?>
                        <button onclick="location.href='pay_rent.php?application_id=<?= $application_id ?>'" 
                                class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                            Pay Rent Now
                        </button>
                    <?php else: ?>
                        <button onclick="alert('No application found. Please apply for a stall first.')" 
                                class="w-full bg-gray-400 cursor-not-allowed text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                            No Application Found
                        </button>
                        <p class="text-sm text-gray-500 mt-2">Apply for a stall first to pay rent</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Application Info (if exists) -->
        <?php if ($has_application): ?>
        <div class="max-w-2xl mx-auto mt-12 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-2">Current Application</h3>
            <p class="text-blue-700">You have an active application (ID: <?= $application_id ?>)</p>
            <p class="text-sm text-blue-600 mt-1">Click "View Documents" to see your application details and uploaded files.</p>
        </div>
        <?php endif; ?>
    </div>

</body>
</html>