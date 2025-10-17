<?php
session_start();
require_once '../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../citizen_portal/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;
$payment_type = $_GET['payment_type'] ?? 'application';
$payment_id = $_GET['payment_id'] ?? null;
$payment_ids = $_GET['payment_ids'] ?? null;
$amount = $_GET['amount'] ?? null;

// Check if all required parameters are present
if (!$application_id || !$amount) {
    // If parameters are missing, try to get from session or show error
    if (isset($_SESSION['last_payment_data'])) {
        $last_data = $_SESSION['last_payment_data'];
        header('Location: select_payment_method.php?' . http_build_query($last_data));
        exit;
    } else {
        die("Missing required parameters. Please go back and try again.");
    }
}

// Store current parameters in session for recovery
$_SESSION['last_payment_data'] = [
    'application_id' => $application_id,
    'payment_type' => $payment_type,
    'amount' => $amount,
    'payment_id' => $payment_id,
    'payment_ids' => $payment_ids
];

// Fetch payment details for display - UPDATED FOR NEW NAME STRUCTURE
if ($payment_type === 'application') {
    $stmt = $pdo->prepare("
        SELECT 
            a.first_name,
            a.middle_name,
            a.last_name,
            CONCAT(a.first_name, ' ', IFNULL(CONCAT(a.middle_name, ' '), ''), a.last_name) as full_name,
            a.business_name, 
            a.market_name, 
            a.stall_number
        FROM applications a 
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            r.first_name,
            r.middle_name,
            r.last_name,
            CONCAT(r.first_name, ' ', IFNULL(CONCAT(r.middle_name, ' '), ''), r.last_name) as full_name,
            r.business_name, 
            r.market_name, 
            r.stall_number
        FROM renters r 
        WHERE r.application_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
}

$details = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$details) {
    die("Payment details not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Payment Method</title>
    <link rel="stylesheet" href="../navbar.css">
    <link rel="stylesheet" href="select_payment_method.css">
</head>
<body>
    <?php include '../navbar.php'; ?>

    <!-- Display any error messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message">
            <div class="error-content">
                <span class="error-icon">⚠</span>
                <div class="error-text">
                    <strong>Error</strong>
                    <p><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="payment-container">
        <div class="payment-header">
            <div class="header-content">
                <h1>Select Payment Method</h1>
                <p class="invoice-number">Total Amount: ₱<?= number_format($amount, 2) ?></p>
            </div>
        </div>

        <div class="billing-section">
            <div class="billing-details">
                <div class="section-header">
                    <h2>Payment Details</h2>
                </div>
                <div class="billing-grid">
                    <div class="billing-item">
                        <label>Applicant Name:</label>
                        <span><?= htmlspecialchars($details['full_name']) ?></span>
                        <!-- Show individual name components for verification -->
                        <div class="name-details">
                            <div class="name-detail-item">
                                <span class="name-detail-label">First Name:</span>
                                <span class="name-detail-value"><?= htmlspecialchars($details['first_name']) ?></span>
                            </div>
                            <?php if (!empty($details['middle_name'])): ?>
                            <div class="name-detail-item">
                                <span class="name-detail-label">Middle Name:</span>
                                <span class="name-detail-value"><?= htmlspecialchars($details['middle_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="name-detail-item">
                                <span class="name-detail-label">Last Name:</span>
                                <span class="name-detail-value"><?= htmlspecialchars($details['last_name']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="billing-item">
                        <label>Business Name:</label>
                        <span><?= htmlspecialchars($details['business_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Market Location:</label>
                        <span><?= htmlspecialchars($details['market_name']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Stall Number:</label>
                        <span><?= htmlspecialchars($details['stall_number']) ?></span>
                    </div>
                    <div class="billing-item">
                        <label>Payment Type:</label>
                        <span class="payment-type-badge"><?= $payment_type === 'application' ? 'Application Fee' : 'Monthly Rent' ?></span>
                    </div>
                </div>
            </div>

            <div class="payment-summary">
                <div class="section-header">
                    <h2>Amount Due</h2>
                </div>
                <div class="summary-card">
                    <div class="total-amount-display">
                        ₱<?= number_format($amount, 2) ?>
                    </div>
                    <p class="summary-note">Total amount to be paid</p>
                </div>
            </div>
        </div>

        <div class="payment-methods-section">
            <div class="section-header">
                <h2>Choose Payment Method</h2>
            </div>
            
            <form id="paymentForm" method="POST" action="process_payment.php">
                <input type="hidden" name="application_id" value="<?= $application_id ?>">
                <input type="hidden" name="payment_type" value="<?= $payment_type ?>">
                <input type="hidden" name="amount" value="<?= $amount ?>">
                <?php if ($payment_id): ?>
                    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                <?php endif; ?>
                <?php if ($payment_ids): ?>
                    <input type="hidden" name="payment_ids" value="<?= $payment_ids ?>">
                <?php endif; ?>

                <div class="payment-options">
                    <div class="payment-method">
                        <input type="radio" name="payment_method" value="gcash" id="gcash" required>
                        <label for="gcash" class="method-label">
                            <div class="method-icon">
                                <img src="images/gcash-logo.png" alt="GCash" onerror="this.style.display='none'">
                                <div class="fallback-icon">💳</div>
                            </div>
                            <div class="method-info">
                                <span class="method-name">GCash</span>
                                <span class="method-desc">Pay using your GCash wallet</span>
                            </div>
                            <div class="method-selector">
                                <div class="radio-indicator"></div>
                            </div>
                        </label>
                    </div>

                    <div class="payment-method">
                        <input type="radio" name="payment_method" value="maya" id="maya">
                        <label for="maya" class="method-label">
                            <div class="method-icon">
                                <img src="images/maya-logo.png" alt="Maya" onerror="this.style.display='none'">
                                <div class="fallback-icon">💳</div>
                            </div>
                            <div class="method-info">
                                <span class="method-name">Maya</span>
                                <span class="method-desc">Pay using your Maya wallet</span>
                            </div>
                            <div class="method-selector">
                                <div class="radio-indicator"></div>
                            </div>
                        </label>
                    </div>

                    <div class="payment-method">
                        <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer">
                        <label for="bank_transfer" class="method-label">
                            <div class="method-icon">
                                <img src="images/bank-transfer.png" alt="Bank Transfer" onerror="this.style.display='none'">
                                <div class="fallback-icon">🏦</div>
                            </div>
                            <div class="method-info">
                                <span class="method-name">Bank Transfer</span>
                                <span class="method-desc">Transfer funds from your bank account</span>
                            </div>
                            <div class="method-selector">
                                <div class="radio-indicator"></div>
                            </div>
                        </label>
                    </div>

                    <div class="payment-method">
                        <input type="radio" name="payment_method" value="over_the_counter" id="over_the_counter">
                        <label for="over_the_counter" class="method-label">
                            <div class="method-icon">
                                <img src="images/otc.png" alt="Over the Counter" onerror="this.style.display='none'">
                                <div class="fallback-icon">🏪</div>
                            </div>
                            <div class="method-info">
                                <span class="method-name">Over the Counter</span>
                                <span class="method-desc">Pay at designated payment centers</span>
                            </div>
                            <div class="method-selector">
                                <div class="radio-indicator"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="phoneNumberSection" class="phone-number-section" style="display: none;">
                    <div class="form-group">
                        <label for="phone_number">Mobile Number <span class="required">*</span></label>
                        <input type="tel" id="phone_number" name="phone_number" 
                               placeholder="Enter your mobile number (e.g., 09171234567)" 
                               pattern="[0-9]{11}" maxlength="11">
                        <small class="form-text">Please enter your 11-digit mobile number</small>
                    </div>
                </div>

                <div class="payment-actions">
                    <button type="submit" id="pay-button" class="pay-now-btn">
                        <span class="btn-text">Confirm Payment</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span>
                            Processing...
                        </span>
                    </button>
                    <a href="pay_application_fee.php?application_id=<?= $application_id ?>&payment_type=<?= $payment_type ?><?= $payment_id ? '&payment_id=' . $payment_id : '' ?><?= $payment_ids ? '&payment_ids=' . $payment_ids : '' ?>&amount=<?= $amount ?>" 
                       class="cancel-btn">
                        Back
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/payment_methods.js"></script>
</body>
</html>