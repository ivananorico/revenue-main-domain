<?php
session_start();
require_once '../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;
$payment_type = $_GET['payment_type'] ?? 'application'; // 'application' or 'rent' or 'rent_all'
$payment_id = $_GET['payment_id'] ?? null;
$payment_ids = $_GET['payment_ids'] ?? null;
$amount = $_GET['amount'] ?? null;

if (!$application_id) {
    die("No application specified.");
}

if ($payment_type === 'application') {
    // Fetch application fee info with billing details - UPDATED FOR NEW NAME STRUCTURE
    $stmt = $pdo->prepare("
        SELECT 
            af.*, 
            a.first_name,
            a.middle_name,
            a.last_name,
            CONCAT(a.first_name, ' ', IFNULL(CONCAT(a.middle_name, ' '), ''), a.last_name) as full_name,
            a.business_name,
            a.market_name,
            a.stall_number,
            a.application_date,
            af.application_fee,
            af.security_bond,
            af.stall_rights_fee,
            af.total_amount
        FROM application_fee af
        JOIN applications a ON af.application_id = a.id
        WHERE af.application_id = :application_id AND a.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        ':application_id' => $application_id,
        ':user_id' => $user_id
    ]);
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment_data) {
        die("Application fee not found.");
    }

    $title = "Pay Stall Application Fee";
    $invoice_number = "APP-" . str_pad($application_id, 6, '0', STR_PAD_LEFT);
    $display_amount = $payment_data['total_amount'];
    $application_date = date('F j, Y', strtotime($payment_data['application_date']));
    
} else {
    // For rent payments, fetch renter information - UPDATED FOR NEW NAME STRUCTURE
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            a.first_name,
            a.middle_name,
            a.last_name,
            CONCAT(a.first_name, ' ', IFNULL(CONCAT(a.middle_name, ' '), ''), a.last_name) as full_name,
            a.business_name,
            a.market_name,
            a.stall_number
        FROM renters r
        JOIN applications a ON r.application_id = a.id
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

    if ($payment_type === 'rent') {
        // Single month rent payment
        $title = "Pay Monthly Rent";
        $invoice_number = "RENT-" . $payment_id;
        $display_amount = $amount;
    } else {
        // Multiple months rent payment
        $title = "Pay All Monthly Rent";
        $invoice_number = "RENT-ALL-" . time();
        $display_amount = $amount;
    }
    
    $payment_data = $renter;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../navbar.css">
<link rel="stylesheet" href="pay_application_fee.css">
<title><?= $title ?></title>
</head>
<body>
<?php include '../navbar.php'; ?>

<div class="payment-container">
    <div class="payment-header">
        <h2><?= $title ?></h2>
        <p class="invoice-number">Invoice #<?= $invoice_number ?></p>
    </div>

    <div class="billing-section">
        <div class="billing-details">
            <h3>Billing Details</h3>
            <div class="billing-grid">
                <?php if ($payment_type === 'application'): ?>
                    <div class="billing-item">
                        <label>Application ID:</label>
                        <span>APP-<?= str_pad($application_id, 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Applicant Name:</label>
                        <span><?= htmlspecialchars($payment_data['full_name']) ?></span>
                    </div>
                    <!-- Optional: Show individual name components for verification -->
                    <div class="billing-item">
                        <label>First Name:</label>
                        <span><?= htmlspecialchars($payment_data['first_name']) ?></span>
                    </div>
                    <?php if (!empty($payment_data['middle_name'])): ?>
                    <div class="billing-item">
                        <label>Middle Name:</label>
                        <span><?= htmlspecialchars($payment_data['middle_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="billing-item">
                        <label>Last Name:</label>
                        <span><?= htmlspecialchars($payment_data['last_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Business Name:</label>
                        <span><?= htmlspecialchars($payment_data['business_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Market Location:</label>
                        <span><?= htmlspecialchars($payment_data['market_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Stall Number:</label>
                        <span><?= htmlspecialchars($payment_data['stall_number']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Application Date:</label>
                        <span><?= $application_date ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Due Date:</label>
                        <span class="due-date"><?= date('F j, Y', strtotime('+7 days')) ?></span>
                    </div>
                <?php else: ?>
                    <div class="billing-item">
                        <label>Renter ID:</label>
                        <span><?= htmlspecialchars($payment_data['id']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Applicant Name:</label>
                        <span><?= htmlspecialchars($payment_data['full_name']) ?></span>
                    </div>
                    <!-- Optional: Show individual name components for verification -->
                    <div class="billing-item">
                        <label>First Name:</label>
                        <span><?= htmlspecialchars($payment_data['first_name']) ?></span>
                    </div>
                    <?php if (!empty($payment_data['middle_name'])): ?>
                    <div class="billing-item">
                        <label>Middle Name:</label>
                        <span><?= htmlspecialchars($payment_data['middle_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="billing-item">
                        <label>Last Name:</label>
                        <span><?= htmlspecialchars($payment_data['last_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Business Name:</label>
                        <span><?= htmlspecialchars($payment_data['business_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Market Location:</label>
                        <span><?= htmlspecialchars($payment_data['market_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Stall Number:</label>
                        <span><?= htmlspecialchars($payment_data['stall_number']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Payment Type:</label>
                        <span><?= $payment_type === 'rent' ? 'Single Month Rent' : 'Multiple Months Rent' ?></span>
                    </div>
                    <?php if ($payment_type === 'rent' && $payment_id): ?>
                    <div class="billing-item">
                        <label>Payment ID:</label>
                        <span><?= $payment_id ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="payment-summary">
            <h3>Payment Summary</h3>
            <div class="summary-card">
                <div class="fee-breakdown">
                    <?php if ($payment_type === 'application'): ?>
                        <div class="fee-item">
                            <span>Application Fee:</span>
                            <span>₱<?= number_format($payment_data['application_fee'], 2) ?></span>
                        </div>
                        <div class="fee-item">
                            <span>Security Bond:</span>
                            <span>₱<?= number_format($payment_data['security_bond'], 2) ?></span>
                        </div>
                        <div class="fee-item">
                            <span>Stall Rights Fee:</span>
                            <span>₱<?= number_format($payment_data['stall_rights_fee'], 2) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="fee-item">
                            <span>Monthly Rent:</span>
                            <span>₱<?= number_format($payment_data['monthly_rent'], 2) ?></span>
                        </div>
                        <?php if ($payment_type === 'rent'): ?>
                            <div class="fee-item">
                                <span>Payment For:</span>
                                <span>1 Month</span>
                            </div>
                        <?php else: ?>
                            <div class="fee-item">
                                <span>Payment For:</span>
                                <span><?= count(explode(',', $payment_ids)) ?> Months</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="fee-total">
                        <span><strong>Total Amount Due:</strong></span>
                        <span class="total-amount">₱<?= number_format($display_amount, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="payment-methods-section">
        <h3>Payment Process</h3>
        <div class="payment-instructions">
            <p>Click the button below to proceed to payment method selection where you can choose your preferred payment method and provide your contact information.</p>
        </div>

        <div class="payment-actions">
            <a href="select_payment_method.php?application_id=<?= $application_id ?>&payment_type=<?= $payment_type ?>&amount=<?= $display_amount ?><?= $payment_id ? '&payment_id=' . $payment_id : '' ?><?= $payment_ids ? '&payment_ids=' . $payment_ids : '' ?>" 
               class="pay-now-btn">
                Proceed to Payment Method
            </a>
            <a href="<?= $payment_type === 'application' ? 'apply_stall.php' : 'payment_rent.php?application_id=' . $application_id ?>" class="cancel-btn">
                Cancel Payment
            </a>
        </div>
    </div>
</div>

</body>
</html>