<?php
session_start();
require_once '../../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    $_SESSION['error'] = "No application specified.";
    header('Location: ../market-dashboard.php');
    exit;
}

// Fetch application and payment details
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            s.name AS stall_name,
            s.price AS monthly_rent,
            m.name AS market_name,
            sec.name AS section_name,
            r.renter_id,
            r.business_name,
            r.first_name,
            r.last_name,
            r.contact_number,
            r.email
        FROM applications a
        LEFT JOIN stalls s ON a.stall_id = s.id
        LEFT JOIN maps m ON s.map_id = m.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN renters r ON a.id = r.application_id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['error'] = "Application not found or access denied.";
        header('Location: ../market-dashboard.php');
        exit;
    }

    // Get all unpaid monthly payments
    $payments_stmt = $pdo->prepare("
        SELECT * FROM monthly_payments 
        WHERE renter_id = ? AND status IN ('pending', 'overdue')
        ORDER BY month_year ASC
    ");
    $payments_stmt->execute([$application['renter_id']]);
    $unpaid_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment history (paid payments)
    $history_stmt = $pdo->prepare("
        SELECT * FROM monthly_payments 
        WHERE renter_id = ? AND status = 'paid'
        ORDER BY month_year DESC 
        LIMIT 6
    ");
    $history_stmt->execute([$application['renter_id']]);
    $payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: ../market-dashboard.php');
    exit;
}

// Calculate total due amount
$total_due = 0;
foreach ($unpaid_payments as $payment) {
    $total_due += $payment['amount'] + ($payment['late_fee'] ?? 0);
}

$monthly_rent = $application['monthly_rent'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Monthly Rent - Market Stall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .payment-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        .rent-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }
        .rent-item:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }
        .btn-pay-single {
            background: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-pay-single:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-overdue {
            background: #fee2e2;
            color: #dc2626;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        .amount-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #059669;
        }
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #d97706;
            text-align: center;
            margin: 1rem 0;
        }
    </style>
</head>
<body class="bg-gray-50">
   <?php include '../../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Pay Monthly Rent</h1>
            <p class="text-gray-600">Stall: <?= htmlspecialchars($application['stall_name']) ?> • <?= htmlspecialchars($application['market_name']) ?></p>
        </div>

        <!-- Stall Information -->
        <div class="payment-card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">🏪 Stall Information</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Stall Number</p>
                    <p class="font-semibold"><?= htmlspecialchars($application['stall_name']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Market</p>
                    <p class="font-semibold"><?= htmlspecialchars($application['market_name']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Section</p>
                    <p class="font-semibold"><?= htmlspecialchars($application['section_name']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Monthly Rent</p>
                    <p class="font-semibold text-green-600">₱<?= number_format($monthly_rent, 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Unpaid Rent Payments -->
        <div class="payment-card">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">📅 Unpaid Rent</h2>
                <?php if (!empty($unpaid_payments)): ?>
                    <div class="text-right">
                        <p class="text-gray-600">Total Due</p>
                        <p class="total-amount">₱<?= number_format($total_due, 2) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($unpaid_payments)): ?>
                <div class="space-y-4">
                    <?php foreach ($unpaid_payments as $payment): ?>
                        <?php
                        $payment_amount = $payment['amount'] + ($payment['late_fee'] ?? 0);
                        $is_overdue = $payment['status'] === 'overdue';
                        ?>
                        <div class="rent-item">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-4 mb-2">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?= date('F Y', strtotime($payment['month_year'] . '-01')) ?>
                                        </h3>
                                        <span class="status-badge <?= $is_overdue ? 'status-overdue' : 'status-pending' ?>">
                                            <?= $is_overdue ? '⚠️ Overdue' : '⏰ Pending' ?>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600">Due Date:</span>
                                            <span class="font-medium"><?= date('M j, Y', strtotime($payment['due_date'])) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Monthly Rent:</span>
                                            <span class="font-medium">₱<?= number_format($payment['amount'], 2) ?></span>
                                        </div>
                                        <?php if ($payment['late_fee'] > 0): ?>
                                        <div>
                                            <span class="text-gray-600">Late Fee:</span>
                                            <span class="font-medium text-red-600">₱<?= number_format($payment['late_fee'], 2) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="amount-display mb-2">
                                        ₱<?= number_format($payment_amount, 2) ?>
                                    </div>
                                    <!-- Direct link to payment details page for single payment -->
                                    <a href="../../digital_card/market_rent_payment_details.php?application_id=<?= $application_id ?>&month_year=<?= $payment['month_year'] ?>&amount=<?= $payment_amount ?>&payment_for=single_month&payment_type=monthly_rent" 
                                       class="btn-pay-single inline-block">
                                        Pay Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- No Unpaid Payments -->
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl text-green-600">✅</span>
                    </div>
                    <h3 class="text-xl font-semibold text-green-800 mb-2">All Caught Up!</h3>
                    <p class="text-gray-600">You have no unpaid rent payments.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="payment-card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 Payment History</h2>
            
            <?php if (!empty($payment_history)): ?>
                <div class="space-y-3">
                    <?php foreach ($payment_history as $payment): ?>
                    <div class="rent-item">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-semibold text-gray-800">
                                    <?= date('F Y', strtotime($payment['month_year'] . '-01')) ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Paid on <?= date('M j, Y', strtotime($payment['paid_date'])) ?>
                                    <?php if ($payment['late_fee'] > 0): ?>
                                        • <span class="text-red-600">Late fee: ₱<?= number_format($payment['late_fee'], 2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-800">
                                    ₱<?= number_format($payment['amount'] + $payment['late_fee'], 2) ?>
                                </div>
                                <span class="status-badge status-paid">Paid</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No payment history found.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex justify-center">
            <button onclick="location.href='../market-dashboard.php'" 
                    class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200">
                ← Back to Dashboard
            </button>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const rentItems = document.querySelectorAll('.rent-item');
            rentItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>