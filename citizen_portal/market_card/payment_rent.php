<?php
session_start();
require_once '../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    die("No application specified.");
}

// Fetch renter information with lease contract
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        a.full_name,
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
    die("Renter information not found.");
}

// Fetch ALL monthly payments for this renter (both pending and paid)
$stmt = $pdo->prepare("
    SELECT * FROM monthly_payments 
    WHERE renter_id = :renter_id 
    ORDER BY due_date ASC
");
$stmt->execute([':renter_id' => $renter['renter_id']]); // Use renter_id, not id
$all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../navbar.css">
<link rel="stylesheet" href="payment_rent.css">
<title>Monthly Rent Payments</title>
</head>
<body>
<?php include '../navbar.php'; ?>

<div class="payment-container">
    <div class="payment-header">
        <h2>Monthly Rent Payments</h2>
        <p class="invoice-number">Renter ID: <?= htmlspecialchars($renter['renter_id']) ?></p>
    </div>

    <div class="billing-section">
        <div class="billing-details">
            <h3>Renter Information</h3>
            <div class="billing-grid">
                <div class="billing-item">
                    <label>Renter ID:</label>
                    <span><?= htmlspecialchars($renter['renter_id']) ?></span>
                </div>
                <div class="billing-item">
                    <label>Full Name:</label>
                    <span><?= htmlspecialchars($renter['full_name']) ?></span>
                </div>
                <div class="billing-item">
                    <label>Business Name:</label>
                    <span><?= htmlspecialchars($renter['business_name']) ?></span>
                </div>
                <div class="billing-item">
                    <label>Market Location:</label>
                    <span><?= htmlspecialchars($renter['market_name']) ?></span>
                </div>
                <div class="billing-item">
                    <label>Stall Number:</label>
                    <span><?= htmlspecialchars($renter['stall_number']) ?></span>
                </div>
                <div class="billing-item">
                    <label>Section:</label>
                    <span><?= htmlspecialchars($renter['section_name'] ?? 'N/A') ?></span>
                </div>
                <div class="billing-item">
                    <label>Monthly Rent:</label>
                    <span>₱<?= number_format($renter['monthly_rent'], 2) ?></span>
                </div>
                <?php if ($renter['lease_start_date'] && $renter['lease_end_date']): ?>
                <div class="billing-item">
                    <label>Lease Period:</label>
                    <span>
                        <?= date('M j, Y', strtotime($renter['lease_start_date'])) ?> - 
                        <?= date('M j, Y', strtotime($renter['lease_end_date'])) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="payment-summary">
            <h3>Payment Summary</h3>
            <div class="summary-card">
                <div class="fee-breakdown">
                    <div class="fee-item">
                        <span>Monthly Rent:</span>
                        <span>₱<?= number_format($renter['monthly_rent'], 2) ?></span>
                    </div>
                    <div class="fee-item">
                        <span>Pending Payments:</span>
                        <span><?= count($pending_payments) ?> month(s)</span>
                    </div>
                    <div class="fee-item">
                        <span>Paid Payments:</span>
                        <span><?= count($paid_payments) ?> month(s)</span>
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
        <h3 class="section-title">Pending Monthly Payments</h3>
        
        <?php foreach ($pending_payments as $payment): ?>
        <div class="payment-item">
            <div class="payment-details">
                <div class="payment-month">
                    <?= date('F Y', strtotime($payment['month_year'] . '-01')) ?>
                    <?php if (strtotime($payment['due_date']) < time()): ?>
                        <span class="status-badge status-overdue">OVERDUE</span>
                    <?php endif; ?>
                </div>
                <div class="payment-due">
                    Due Date: <?= date('M j, Y', strtotime($payment['due_date'])) ?>
                </div>
                <?php if (($payment['late_fee'] ?? 0) > 0): ?>
                <div class="late-fee">
                    Late Fee: ₱<?= number_format($payment['late_fee'], 2) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="payment-amount amount-pending">
                ₱<?= number_format($payment['amount'] + ($payment['late_fee'] ?? 0), 2) ?>
            </div>
            <div class="payment-actions">
                <button class="pay-individual-btn" 
                        data-payment-id="<?= $payment['id'] ?>" 
                        data-amount="<?= $payment['amount'] + ($payment['late_fee'] ?? 0) ?>">
                    Pay This Month
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="pay-all-section">
            <h4>Pay All Pending Payments</h4>
            <p>Total: <strong>₱<?= number_format($total_pending, 2) ?></strong></p>
            <button class="pay-all-btn" id="pay-all-btn">
                Pay All (<?= count($pending_payments) ?> Months)
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($paid_payments) > 0): ?>
    <div class="payment-section">
        <h3 class="section-title">Payment History</h3>
        
        <?php foreach ($paid_payments as $payment): ?>
        <div class="payment-item">
            <div class="payment-details">
                <div class="payment-month">
                    <?= date('F Y', strtotime($payment['month_year'] . '-01')) ?>
                    <span class="status-badge status-paid">PAID</span>
                </div>
                <div class="payment-due">
                    Paid on: <?= date('M j, Y g:i A', strtotime($payment['paid_date'])) ?>
                </div>
                <?php if ($payment['reference_number']): ?>
                <div class="reference-number">
                    Reference: <?= htmlspecialchars($payment['reference_number']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="payment-amount amount-paid">
                ₱<?= number_format($payment['amount'] + ($payment['late_fee'] ?? 0), 2) ?>
            </div>
            <div class="payment-actions">
                <a href="download_receipt.php?payment_id=<?= $payment['id'] ?>" 
                   class="download-receipt-btn" 
                   target="_blank">
                    Download Receipt
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (count($pending_payments) === 0 && count($paid_payments) === 0): ?>
    <div class="no-payments">
        <h3>No Payments Found</h3>
        <p>No monthly rent payments have been generated yet. Please check back later.</p>
    </div>
    <?php endif; ?>

    <div class="rent-payment-actions">
        <a href="apply_stall.php" class="cancel-btn">Back to Application</a>
    </div>
</div>

<script>
// Individual payment button handler
document.querySelectorAll('.pay-individual-btn').forEach(button => {
    button.addEventListener('click', function() {
        const paymentId = this.getAttribute('data-payment-id');
        const amount = this.getAttribute('data-amount');
        
        // Redirect to payment page with rent parameters
        window.location.href = `pay_application_fee.php?application_id=<?= $application_id ?>&payment_type=rent&payment_id=${paymentId}&amount=${amount}`;
    });
});

// Pay all button handler
document.getElementById('pay-all-btn')?.addEventListener('click', function() {
    const paymentIds = Array.from(document.querySelectorAll('.pay-individual-btn'))
        .map(btn => btn.getAttribute('data-payment-id'));
    
    // Redirect to payment page with all payment IDs
    window.location.href = `pay_application_fee.php?application_id=<?= $application_id ?>&payment_type=rent_all&payment_ids=${paymentIds.join(',')}&amount=<?= $total_pending ?>`;
});

// Add highlight animation for newly paid payments
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('payment_success') === 'true') {
    const paidItems = document.querySelectorAll('.payment-item .status-paid').closest('.payment-item');
    paidItems.forEach(item => {
        item.classList.add('highlight');
    });
}
</script>
</body>
</html>