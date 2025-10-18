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

// Get application_id from URL parameter
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : null;

if (!$application_id) {
    $_SESSION['error'] = "No application specified.";
    header('Location: market-dashboard.php');
    exit;
}

// Database connection
require_once '../../db/Market/market_db.php';

$application = null;
$payment_details = null;

try {
    // Get application details
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            s.name as stall_name, 
            s.price as stall_price,
            m.name as market_name,
            sr.class_name
        FROM applications a 
        LEFT JOIN stalls s ON a.stall_id = s.id 
        LEFT JOIN maps m ON s.map_id = m.id 
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['error'] = "Application not found or access denied.";
        header('Location: market-dashboard.php');
        exit;
    }

    // Get payment details - include email and phone number from application_fee table
    $payment_stmt = $pdo->prepare("
        SELECT af.*, a.email as user_email, a.contact_number as user_phone 
        FROM application_fee af
        LEFT JOIN applications a ON af.application_id = a.id
        WHERE af.application_id = ? AND af.status = 'paid'
        ORDER BY af.id DESC LIMIT 1
    ");
    $payment_stmt->execute([$application_id]);
    $payment_details = $payment_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: market-dashboard.php');
    exit;
}

// Get email and phone - try multiple sources
$email = $payment_details['email'] ?? $payment_details['user_email'] ?? $_SESSION['email'] ?? $application['email'] ?? 'Not provided';
$phone = $payment_details['phone_number'] ?? $payment_details['user_phone'] ?? $_SESSION['phone_number'] ?? $application['contact_number'] ?? 'Not provided';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Market Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .success-animation {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .receipt-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            color: white;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .receipt-card {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-md">
        <!-- Success Header -->
        <div class="text-center mb-8">
            <div class="success-animation mb-4">
                <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto">
                    <span class="text-3xl text-white">✓</span>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Payment Successful!</h1>
            <p class="text-gray-600">Your application fee has been processed</p>
        </div>

        <!-- E-wallet Style Receipt -->
        <div class="receipt-card p-6 mb-6 shadow-xl">
            <div class="text-center mb-6">
                <h2 class="text-xl font-bold mb-2">E-WALLET RECEIPT</h2>
                <p class="text-blue-100">Market Stall Application</p>
            </div>

            <!-- Reference Number -->
            <div class="bg-white bg-opacity-20 rounded-lg p-4 mb-4 text-center">
                <p class="text-sm text-blue-100 mb-1">Reference Number</p>
                <p class="text-2xl font-bold font-mono tracking-wider">
                    <?= $payment_details['reference_number'] ?? 'N/A' ?>
                </p>
            </div>

            <!-- Payment Details -->
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-blue-100">Date & Time</span>
                    <span class="font-semibold"><?= date('M j, Y g:i A', strtotime($payment_details['payment_date'] ?? 'now')) ?></span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-blue-100">Application ID</span>
                    <span class="font-semibold">#<?= $application['id'] ?></span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-blue-100">Purpose</span>
                    <span class="font-semibold text-right">Stall Application Fee</span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-blue-100">Applicant</span>
                    <span class="font-semibold"><?= htmlspecialchars($full_name) ?></span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-blue-100">Email</span>
                    <span class="font-semibold text-sm"><?= htmlspecialchars($email) ?></span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-blue-100">Phone</span>
                    <span class="font-semibold"><?= htmlspecialchars($phone) ?></span>
                </div>
            </div>

            <!-- Amount -->
            <div class="border-t border-blue-300 mt-4 pt-4 text-center">
                <p class="text-blue-100 text-sm">Amount Paid</p>
                <p class="text-3xl font-bold">₱<?= number_format($payment_details['total_amount'] ?? 0, 2) ?></p>
            </div>

            <!-- Status -->
            <div class="text-center mt-4">
                <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                    COMPLETED
                </span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3 no-print">
            <button onclick="window.print()" 
                   class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center">
                <span class="mr-2">🖨️</span>
                Print Receipt
            </button>
            
            <a href="../market_card/market-dashboard.php" 
               class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center no-print">
                <span class="mr-2">📊</span>
                Back to Dashboard
            </a>
        </div>

        <!-- Support Info -->
        <div class="text-center mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg no-print">
            <p class="text-yellow-700 text-sm">
                💡 <strong>Save your reference number:</strong> <?= $payment_details['reference_number'] ?? 'N/A' ?>
            </p>
            <p class="text-yellow-600 text-xs mt-1">
                For inquiries: market.admin@localhost
            </p>
        </div>
    </div>

    <script>
        // Simple success effect
        document.addEventListener('DOMContentLoaded', function() {
            // Add subtle animation to receipt card
            const receipt = document.querySelector('.receipt-card');
            receipt.style.transform = 'scale(0.95)';
            receipt.style.opacity = '0';
            
            setTimeout(() => {
                receipt.style.transition = 'all 0.5s ease';
                receipt.style.transform = 'scale(1)';
                receipt.style.opacity = '1';
            }, 100);
        });
    </script>

</body>
</html>