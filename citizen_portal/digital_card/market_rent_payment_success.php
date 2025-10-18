<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if success data exists
if (!isset($_SESSION['rent_payment_success'])) {
    header('Location: ../market-dashboard.php');
    exit;
}

$success_data = $_SESSION['rent_payment_success'];
$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    header('Location: ../market-dashboard.php');
    exit;
}

// Database connection
require_once '../../db/Market/market_db.php';

// Get payment details from database
$payment_details = null;
try {
    $stmt = $pdo->prepare("
        SELECT mp.*, r.business_name, r.market_name, r.stall_number, r.first_name, r.last_name
        FROM monthly_payments mp
        LEFT JOIN renters r ON mp.renter_id = r.renter_id
        WHERE r.application_id = ? 
        AND mp.reference_number = ?
        ORDER BY mp.paid_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$application_id, $success_data['reference_number']]);
    $payment_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Clear success data after displaying
unset($_SESSION['rent_payment_success']);
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
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }
        .receipt {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px dashed #0ea5e9;
        }
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background-color: #f00;
            animation: confetti-fall 5s linear forwards;
        }
        @keyframes confetti-fall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include '../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Success Header -->
            <div class="text-center mb-12">
                <div class="success-animation w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-gray-800 mb-4">Payment Successful!</h1>
                <p class="text-xl text-gray-600">Your monthly rent payment has been processed successfully</p>
            </div>

            <!-- Receipt Card -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-8">
                <div class="bg-green-600 text-white p-6 text-center">
                    <h2 class="text-2xl font-bold">Payment Confirmed</h2>
                    <p class="text-green-100">Thank you for your payment</p>
                </div>
                
                <div class="p-6">
                    <!-- Payment Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-3">Payment Information</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Reference Number:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($success_data['reference_number']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Amount Paid:</span>
                                    <span class="font-semibold text-green-600">₱<?= number_format($success_data['amount'], 2) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-semibold"><?= strtoupper($success_data['payment_method']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Date:</span>
                                    <span class="font-semibold"><?= date('F j, Y g:i A') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-3">Renter Information</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Business Name:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($success_data['business_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Stall Location:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($success_data['market_name']) ?> - <?= htmlspecialchars($success_data['stall_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Period:</span>
                                    <span class="font-semibold">
                                        <?= $success_data['payment_for'] === 'all_months' ? 'All Unpaid Months' : 'Single Month (' . date('F Y', strtotime($success_data['month_year'] . '-01')) . ')' ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Renter ID:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($success_data['renter_id']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paid Months (if applicable) -->
                    <?php if ($payment_details && $success_data['payment_for'] === 'all_months'): ?>
                    <div class="mb-6">
                        <h3 class="font-semibold text-gray-800 mb-3">Paid Months</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <?php 
                                // Get all paid months for this payment
                                $months_stmt = $pdo->prepare("
                                    SELECT month_year, amount, late_fee 
                                    FROM monthly_payments 
                                    WHERE renter_id = ? AND reference_number = ?
                                ");
                                $months_stmt->execute([$success_data['renter_id'], $success_data['reference_number']]);
                                $paid_months = $months_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($paid_months as $month): 
                                ?>
                                <div class="flex justify-between py-1 border-b border-gray-200">
                                    <span><?= date('F Y', strtotime($month['month_year'] . '-01')) ?></span>
                                    <span class="font-semibold">₱<?= number_format($month['amount'] + $month['late_fee'], 2) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <div class="text-center p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-green-800 font-semibold">Payment Status: COMPLETED</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h3 class="font-semibold text-blue-800 mb-3">What's Next?</h3>
                <ul class="text-sm text-blue-700 space-y-2 list-disc list-inside">
                    <li>Your payment receipt has been sent to your email</li>
                    <li>Keep this reference number for your records: <strong><?= htmlspecialchars($success_data['reference_number']) ?></strong></li>
                    <li>Your stall rental is now up to date</li>
                    <li>Next payment due date will be shown in your dashboard</li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="../market_card/payment_rent.php?application_id=<?= $application_id ?>" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 text-center">
                    📊 View Payment History
                </a>
                <a href="../../../market_card/pay_rent/pay_rent.php" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 text-center">
                    🏠 Back to Dashboard
                </a>
                <button onclick="window.print()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                    🖨️ Print Receipt
                </button>
            </div>
        </div>
    </div>

    <script>
        // Create confetti effect
        function createConfetti() {
            const colors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'];
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.width = Math.random() * 10 + 5 + 'px';
                    confetti.style.height = Math.random() * 10 + 5 + 'px';
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => {
                        confetti.remove();
                    }, 5000);
                }, i * 100);
            }
        }

        // Start confetti when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
        });
    </script>

</body>
</html>