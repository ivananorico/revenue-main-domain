<?php
session_start();
require_once '../../db/Market/market_db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// NO SECURITY - Just get parameters
$application_id = $_GET['application_id'] ?? null;
$payment_type = $_GET['payment_type'] ?? null;
$method = $_GET['method'] ?? null;
$ref = $_GET['ref'] ?? null;
$amount = $_GET['amount'] ?? null;

// Simple parameter check
if (!$application_id || !$payment_type || !$method || !$ref || !$amount) {
    echo "Missing parameters";
    exit;
}

try {
    if ($payment_type === 'application') {
        // Get application data - UPDATED FOR NEW NAME STRUCTURE
        $stmt = $pdo->prepare("
            SELECT 
                af.*,
                a.first_name,
                a.middle_name,
                a.last_name,
                a.business_name,
                a.market_name,
                a.stall_number,
                r.renter_id,
                lc.contract_number,
                src.certificate_number
            FROM application_fee af
            JOIN applications a ON af.application_id = a.id
            LEFT JOIN renters r ON r.application_id = a.id
            LEFT JOIN lease_contracts lc ON lc.application_id = a.id
            LEFT JOIN stall_rights_issued src ON src.application_id = a.id
            WHERE af.application_id = ?
            LIMIT 1
        ");
        $stmt->execute([$application_id]);
        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment_data) {
            echo "Payment data not found";
            exit;
        }

        // Create full name from individual components
        $full_name = $payment_data['first_name'];
        if (!empty($payment_data['middle_name'])) {
            $full_name .= ' ' . $payment_data['middle_name'];
        }
        $full_name .= ' ' . $payment_data['last_name'];
        $payment_data['full_name'] = $full_name;

    } else {
        // Get rent payment data - UPDATED FOR NEW NAME STRUCTURE
        $stmt = $pdo->prepare("
            SELECT 
                mp.*,
                r.first_name,
                r.middle_name,
                r.last_name,
                r.business_name,
                r.market_name,
                r.stall_number,
                r.renter_id
            FROM monthly_payments mp
            JOIN renters r ON mp.renter_id = r.renter_id
            WHERE r.application_id = ?
            ORDER BY mp.paid_date DESC
            LIMIT 1
        ");
        $stmt->execute([$application_id]);
        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment_data) {
            echo "Rent payment data not found";
            exit;
        }

        // Create full name from individual components
        $full_name = $payment_data['first_name'];
        if (!empty($payment_data['middle_name'])) {
            $full_name .= ' ' . $payment_data['middle_name'];
        }
        $full_name .= ' ' . $payment_data['last_name'];
        $payment_data['full_name'] = $full_name;
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success-header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 10px; margin-bottom: 20px; }
        .success-icon { font-size: 60px; margin-bottom: 10px; }
        .details { margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 5px; }
        .detail-item { margin: 10px 0; }
        .detail-label { font-weight: bold; color: #555; }
        .amount { font-size: 2em; font-weight: bold; color: #4CAF50; text-align: center; margin: 20px 0; }
        .reference { background: #e8f5e8; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0; }
        .buttons { text-align: center; margin-top: 30px; }
        .btn { padding: 10px 20px; margin: 0 10px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .name-details { background: #f0f8ff; padding: 10px; border-radius: 5px; margin-top: 5px; }
        .name-detail { font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-header">
            <div class="success-icon">✅</div>
            <h1>Payment Successful!</h1>
            <p>Your payment has been processed successfully</p>
        </div>

        <div class="details">
            <h2>Payment Details</h2>
            <div class="detail-item">
                <span class="detail-label">Applicant Name:</span>
                <?= htmlspecialchars($payment_data['full_name']) ?>
                <div class="name-details">
                    <div class="name-detail"><strong>First Name:</strong> <?= htmlspecialchars($payment_data['first_name']) ?></div>
                    <?php if (!empty($payment_data['middle_name'])): ?>
                        <div class="name-detail"><strong>Middle Name:</strong> <?= htmlspecialchars($payment_data['middle_name']) ?></div>
                    <?php endif; ?>
                    <div class="name-detail"><strong>Last Name:</strong> <?= htmlspecialchars($payment_data['last_name']) ?></div>
                </div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Business Name:</span>
                <?= htmlspecialchars($payment_data['business_name']) ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Market Location:</span>
                <?= htmlspecialchars($payment_data['market_name']) ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Stall Number:</span>
                <?= htmlspecialchars($payment_data['stall_number']) ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Method:</span>
                <?= strtoupper($method) ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Type:</span>
                <?= $payment_type === 'application' ? 'Application Fee' : 'Monthly Rent' ?>
            </div>
        </div>

        <div class="amount">₱<?= number_format($amount, 2) ?></div>

        <div class="reference">
            <strong>Reference Number:</strong><br>
            <?= htmlspecialchars($ref) ?>
        </div>

        <?php if ($payment_type === 'application' && isset($payment_data['renter_id'])): ?>
        <div class="details">
            <h2>Account Information</h2>
            <div class="detail-item">
                <span class="detail-label">Renter ID:</span>
                <?= htmlspecialchars($payment_data['renter_id']) ?>
            </div>
            <?php if (isset($payment_data['contract_number'])): ?>
            <div class="detail-item">
                <span class="detail-label">Lease Contract:</span>
                <?= htmlspecialchars($payment_data['contract_number']) ?>
            </div>
            <?php endif; ?>
            <?php if (isset($payment_data['certificate_number'])): ?>
            <div class="detail-item">
                <span class="detail-label">Stall Rights Certificate:</span>
                <?= htmlspecialchars($payment_data['certificate_number']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="buttons">
            <button onclick="window.print()" class="btn">Print Receipt</button>
            <a href="../dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>