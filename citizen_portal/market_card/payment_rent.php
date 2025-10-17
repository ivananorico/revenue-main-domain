<?php
session_start();
require_once '../../db/Market/market_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../citizen_portal/index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;

// Validate application_id
if (!$application_id) {
    header("Location: ../../citizen_portal/index.php");
    exit();
}

// Fetch renter information with lease contract
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            a.first_name,
            a.middle_name,
            a.last_name,
            CONCAT(a.first_name, ' ', IFNULL(CONCAT(a.middle_name, ' '), ''), a.last_name) as full_name,
            a.business_name,
            a.market_name,
            a.stall_number,
            s.name AS stall_name,
            sec.name AS section_name,
            lc.start_date AS lease_start_date,
            lc.end_date AS lease_end_date
        FROM renters r
        JOIN applications a ON r.application_id = a.id
        LEFT JOIN stalls s ON r.stall_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN lease_contracts lc ON r.application_id = lc.application_id
        WHERE r.application_id = :application_id AND r.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        ':application_id' => $application_id,
        ':user_id' => $user_id
    ]);
    $renter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$renter) {
        header("Location: apply_stall.php?error=renter_not_found");
        exit();
    }

    // Fetch ALL monthly payments for this renter
    $stmt = $pdo->prepare("
        SELECT * FROM monthly_payments 
        WHERE renter_id = :renter_id 
        ORDER BY due_date ASC
    ");
    $stmt->execute([':renter_id' => $renter['renter_id']]);
    $all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: apply_stall.php?error=database_error");
    exit();
}

// Separate pending and paid payments
$pending_payments = array_filter($all_payments, function($payment) {
    return $payment['status'] === 'pending';
});
$paid_payments = array_filter($all_payments, function($payment) {
    return $payment['status'] === 'paid';
});

// Calculate totals for pending payments
$total_pending = 0;
foreach ($pending_payments as $payment) {
    $total_pending += $payment['amount'] + ($payment['late_fee'] ?? 0);
}

// Get user name for navbar
$full_name = $_SESSION['full_name'] ?? 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Rent Payments - GoServePH</title>
    <link rel="stylesheet" href="../navbar.css">
    <link rel="stylesheet" href="payment_rent.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .name-details {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 8px;
            margin-top: 5px;
            border-left: 3px solid #007bff;
        }

        .name-detail {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .name-detail:last-child {
            margin-bottom: 0;
        }

        .name-detail strong {
            color: #495057;
        }

        .highlight {
            animation: highlight-fade 2s ease-in-out;
            background-color: #d4edda !important;
        }

        @keyframes highlight-fade {
            0% { background-color: #d4edda; }
            100% { background-color: transparent; }
        }
    </style>
</head>
<body>
    <?php include '../navbar.php'; ?>

    <div class="payment-container">
        <div class="payment-header">
            <h1><i class="fas fa-receipt"></i> Monthly Rent Payments</h1>
            <p class="invoice-number">Renter ID: <?= htmlspecialchars($renter['renter_id']) ?></p>
        </div>

        <div class="billing-section">
            <div class="billing-details">
                <div class="section-header">
                    <i class="fas fa-user-circle"></i>
                    <h2>Renter Information</h2>
                </div>
                <div class="billing-grid">
                    <div class="billing-item">
                        <label><i class="fas fa-id-card"></i> Renter ID:</label>
                        <span><?= htmlspecialchars($renter['renter_id']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label><i class="fas fa-user"></i> Full Name:</label>
                        <span><?= htmlspecialchars($renter['full_name']) ?></span>
                        <div class="name-details">
                            <div class="name-detail">
                                <strong>First Name:</strong> <?= htmlspecialchars($renter['first_name']) ?>
                            </div>
                            <?php if (!empty($renter['middle_name'])): ?>
                            <div class="name-detail">
                                <strong>Middle Name:</strong> <?= htmlspecialchars($renter['middle_name']) ?>
                            </div>
                            <?php endif; ?>
                            <div class="name-detail">
                                <strong>Last Name:</strong> <?= htmlspecialchars($renter['last_name']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="billing-item">
                        <label><i class="fas fa-store"></i> Business Name:</label>
                        <span><?= htmlspecialchars($renter['business_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label><i class="fas fa-map-marker-alt"></i> Market Location:</label>
                        <span><?= htmlspecialchars($renter['market_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label><i class="fas fa-door-open"></i> Stall Number:</label>
                        <span><?= htmlspecialchars($renter['stall_number']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label><i class="fas fa-th-large"></i> Section:</label>
                        <span><?= htmlspecialchars($renter['section_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="billing-item">
                        <label><i class="fas fa-money-bill-wave"></i> Monthly Rent:</label>
                        <span class="amount">₱<?= number_format($renter['monthly_rent'], 2) ?></span>
                    </div>
                    <?php if ($renter['lease_start_date'] && $renter['lease_end_date']): ?>
                    <div class="billing-item">
                        <label><i class="fas fa-calendar-alt"></i> Lease Period:</label>
                        <span>
                            <?= date('M j, Y', strtotime($renter['lease_start_date'])) ?> - 
                            <?= date('M j, Y', strtotime($renter['lease_end_date'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="payment-summary">
                <div class="section-header">
                    <i class="fas fa-chart-pie"></i>
                    <h2>Payment Summary</h2>
                </div>
                <div class="summary-card">
                    <div class="fee-breakdown">
                        <div class="fee-item">
                            <span>Monthly Rent:</span>
                            <span class="amount">₱<?= number_format($renter['monthly_rent'], 2) ?></span>
                        </div>
                        <div class="fee-item">
                            <span>Pending Payments:</span>
                            <span class="badge badge-warning"><?= count($pending_payments) ?> month(s)</span>
                        </div>
                        <div class="fee-item">
                            <span>Paid Payments:</span>
                            <span class="badge badge-success"><?= count($paid_payments) ?> month(s)</span>
                        </div>
                        <?php if ($total_pending > 0): ?>
                        <div class="fee-total">
                            <span><strong>Total Amount Due:</strong></span>
                            <span class="total-amount amount-pending">₱<?= number_format($total_pending, 2) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($pending_payments) > 0): ?>
        <div class="payment-section">
            <div class="section-header">
                <i class="fas fa-clock"></i>
                <h2>Pending Monthly Payments</h2>
            </div>
            
            <?php foreach ($pending_payments as $payment): 
                $is_overdue = strtotime($payment['due_date']) < time();
                $total_amount = $payment['amount'] + ($payment['late_fee'] ?? 0);
            ?>
            <div class="payment-item <?= $is_overdue ? 'overdue' : '' ?>">
                <div class="payment-details">
                    <div class="payment-month">
                        <?= date('F Y', strtotime($payment['month_year'] . '-01')) ?>
                        <?php if ($is_overdue): ?>
                            <span class="status-badge status-overdue">
                                <i class="fas fa-exclamation-triangle"></i> OVERDUE
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">
                                <i class="fas fa-clock"></i> PENDING
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="payment-due">
                        <i class="far fa-calendar-alt"></i>
                        Due Date: <?= date('M j, Y', strtotime($payment['due_date'])) ?>
                    </div>
                    <?php if (($payment['late_fee'] ?? 0) > 0): ?>
                    <div class="late-fee">
                        <i class="fas fa-exclamation-circle"></i>
                        Late Fee: ₱<?= number_format($payment['late_fee'], 2) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="payment-amount amount-pending">
                    ₱<?= number_format($total_amount, 2) ?>
                </div>
                <div class="payment-actions">
                    <button class="btn btn-primary pay-individual-btn" 
                            data-payment-id="<?= $payment['id'] ?>" 
                            data-amount="<?= $total_amount ?>">
                        <i class="fas fa-credit-card"></i> Pay This Month
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="pay-all-section">
                <div class="pay-all-header">
                    <i class="fas fa-bolt"></i>
                    <h3>Quick Pay All</h3>
                </div>
                <div class="pay-all-content">
                    <p>Total Amount: <strong class="total-amount">₱<?= number_format($total_pending, 2) ?></strong></p>
                    <p class="pay-all-desc">Pay all <?= count($pending_payments) ?> pending month(s) at once</p>
                    <button class="btn btn-success pay-all-btn" id="pay-all-btn">
                        <i class="fas fa-bolt"></i> Pay All (<?= count($pending_payments) ?> Months)
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($paid_payments) > 0): ?>
        <div class="payment-section">
            <div class="section-header">
                <i class="fas fa-history"></i>
                <h2>Payment History</h2>
            </div>
            
            <?php foreach ($paid_payments as $payment): ?>
            <div class="payment-item paid">
                <div class="payment-details">
                    <div class="payment-month">
                        <?= date('F Y', strtotime($payment['month_year'] . '-01')) ?>
                        <span class="status-badge status-paid">
                            <i class="fas fa-check-circle"></i> PAID
                        </span>
                    </div>
                    <div class="payment-due">
                        <i class="far fa-calendar-check"></i>
                        Paid on: <?= date('M j, Y g:i A', strtotime($payment['paid_date'])) ?>
                    </div>
                    <?php if ($payment['reference_number']): ?>
                    <div class="reference-number">
                        <i class="fas fa-hashtag"></i>
                        Reference: <?= htmlspecialchars($payment['reference_number']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="payment-amount amount-paid">
                    ₱<?= number_format($payment['amount'] + ($payment['late_fee'] ?? 0), 2) ?>
                </div>
                <div class="payment-actions">
                    <a href="download_receipt.php?payment_id=<?= $payment['id'] ?>" 
                       class="btn btn-outline download-receipt-btn" 
                       target="_blank">
                        <i class="fas fa-download"></i> Receipt
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (count($pending_payments) === 0 && count($paid_payments) === 0): ?>
        <div class="no-payments">
            <div class="no-payments-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <h3>No Payments Found</h3>
            <p>No monthly rent payments have been generated yet. Please check back later.</p>
        </div>
        <?php endif; ?>

        <div class="rent-payment-actions">
            <a href="apply_stall.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Applications
            </a>
            <?php if (count($pending_payments) > 0): ?>
            <div class="quick-actions">
                <button class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Statement
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Individual payment button handler
        document.querySelectorAll('.pay-individual-btn').forEach(button => {
            button.addEventListener('click', function() {
                const paymentId = this.getAttribute('data-payment-id');
                const amount = this.getAttribute('data-amount');
                
                if (confirm(`Proceed to pay ₱${amount} for this month?`)) {
                    window.location.href = `pay_application_fee.php?application_id=<?= $application_id ?>&payment_type=rent&payment_id=${paymentId}&amount=${amount}`;
                }
            });
        });

        // Pay all button handler
        document.getElementById('pay-all-btn')?.addEventListener('click', function() {
            const paymentIds = Array.from(document.querySelectorAll('.pay-individual-btn'))
                .map(btn => btn.getAttribute('data-payment-id'));
            const totalAmount = <?= $total_pending ?>;
            
            if (confirm(`Proceed to pay all pending payments totaling ₱${totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}?`)) {
                window.location.href = `pay_application_fee.php?application_id=<?= $application_id ?>&payment_type=rent_all&payment_ids=${paymentIds.join(',')}&amount=${totalAmount}`;
            }
        });

        // Add highlight animation for newly paid payments
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('payment_success') === 'true') {
            document.querySelectorAll('.payment-item.paid').forEach(item => {
                item.classList.add('highlight');
            });
            
            // Remove highlight after animation
            setTimeout(() => {
                document.querySelectorAll('.payment-item.paid').forEach(item => {
                    item.classList.remove('highlight');
                });
            }, 2000);
        }

        // Add loading states to buttons
        document.querySelectorAll('.pay-individual-btn, .pay-all-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                this.disabled = true;
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                window.history.back();
            }
        });
    </script>
</body>
</html>