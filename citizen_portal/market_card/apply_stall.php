<?php
session_start();
require_once '../../db/Market/market_db.php';

// FIX: Redirect to index.php instead of login.php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../citizen_portal/index.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Guest';
$email     = $_SESSION['email'] ?? 'Not set';

// Fetch application info for this user
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               s.name AS stall_name, 
               s.status AS stall_status,
               m.name AS market_name,
               sr.class_name,
               sec.name AS section_name,
               CONCAT(a.first_name, ' ', COALESCE(a.middle_name, ''), ' ', a.last_name) AS full_name
        FROM applications a
        LEFT JOIN stalls s ON a.stall_id = s.id
        LEFT JOIN maps m ON s.map_id = m.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.user_id = :user_id
        ORDER BY a.application_date DESC
        LIMIT 1
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $application = $stmt->fetch();
} catch (PDOException $e) {
    // If applications table doesn't exist yet, just continue
    $application = null;
}

// Fetch renter info, lease contract, and monthly payments if application exists and is approved
$renter = null;
$leaseContract = null;
$nextMonthPayment = null;
if ($application && $application['status'] === 'approved') {
    try {
        // Get renter information
        $stmt = $pdo->prepare("
            SELECT r.*,
                   CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) AS full_name
            FROM renters r 
            WHERE r.application_id = :application_id
        ");
        $stmt->bindParam(':application_id', $application['id']);
        $stmt->execute();
        $renter = $stmt->fetch();

        if ($renter) {
            // Get lease contract information
            $stmt = $pdo->prepare("
                SELECT lc.* 
                FROM lease_contracts lc 
                WHERE lc.application_id = :application_id
                LIMIT 1
            ");
            $stmt->bindParam(':application_id', $application['id']);
            $stmt->execute();
            $leaseContract = $stmt->fetch();

            // Get the next pending monthly payment
            $stmt = $pdo->prepare("
                SELECT * 
                FROM monthly_payments 
                WHERE renter_id = :renter_id 
                AND status = 'pending' 
                ORDER BY due_date ASC 
                LIMIT 1
            ");
            $stmt->bindParam(':renter_id', $renter['renter_id']);
            $stmt->execute();
            $nextMonthPayment = $stmt->fetch();
        }
    } catch (PDOException $e) {
        // Handle error if tables don't exist
        error_log("Error fetching renter/lease/payment data: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Stall - Municipal Services</title>
    <link rel="stylesheet" href="apply_stall.css">
    <link rel="stylesheet" href="../navbar.css">
</head>
<body>

<?php include '../navbar.php'; ?>

<div class="apply-stall-container">
    <div class="apply-stall-content">
        <header class="apply-stall-header">
            <h2>Market Stall Rental</h2>
        </header>

        <main class="apply-stall-main">
            <?php if (!$application): ?>
                <!-- No application found - Show Apply button -->
                <div class="no-application-section">
                    <p class="application-text">Click the button below to apply for a market stall rental</p>
                    <a href="../../market_portal/market_portal.php?user_id=<?php echo $user_id; ?>&name=<?php echo urlencode($full_name); ?>&email=<?php echo urlencode($email); ?>" 
                       class="apply-button">
                        Apply for Stall
                    </a>
                </div>
            <?php else: ?>
                <!-- Application found - Show application details -->
                <div class="application-details-section">
                    <h3>Your Stall Application</h3>
                    
                    <?php if ($application['status'] === 'paid'): ?>
                    <!-- Notification for Paid Status -->
                    <div class="notification-banner paid-notification">
                        <div class="notification-icon">📋</div>
                        <div class="notification-content">
                            <h4>Action Required: Download and Submit Documents</h4>
                            <p>Your payment has been processed successfully. To complete your application, please:</p>
                            <ol>
                                <li>Download the Lease Contract and Stall Rights Agreement</li>
                                <li>Print and sign the Lease Contract</li>
                                <li>Use the signed Lease Contract to obtain your Business Permit</li>
                                <li>Upload both signed documents in the application details page</li>
                            </ol>
                            <p><strong>Click "View Full Application" below to access the documents and upload forms.</strong></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="application-status">
                        <div class="status-badge status-<?php echo strtolower($application['status']); ?>">
                            <?php 
                            $statusDisplay = [
                                'pending' => 'Pending Review',
                                'approved' => 'Approved - Ready for Payment',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                                'payment_phase' => 'Ready for Payment',
                                'paid' => 'Payment Completed - Action Required',
                                'documents_submitted' => 'Documents Submitted'
                            ];
                            echo $statusDisplay[$application['status']] ?? ucfirst($application['status']);
                            ?>
                        </div>
                    </div>
                    
                    <div class="application-info">
                        <?php if ($application['status'] === 'approved'): ?>
                            <!-- Show only specific details for approved status -->
                            <div class="info-row">
                                <div class="info-label">Market Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['market_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Stall Number:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['stall_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Full Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['full_name']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Business Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['business_name']); ?></div>
                            </div>
                            
                            <!-- Show Monthly Rent Information -->
                            <?php if ($renter): ?>
                                <div class="info-row">
                                    <div class="info-label">Monthly Rent:</div>
                                    <div class="info-value">₱<?php echo number_format($renter['monthly_rent'], 2); ?></div>
                                </div>
                                <?php if ($leaseContract): ?>
                                <div class="info-row">
                                    <div class="info-label">Lease Period:</div>
                                    <div class="info-value">
                                        <?php echo date('M j, Y', strtotime($leaseContract['start_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($leaseContract['end_date'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Show Next Month's Pending Payment -->
                            <?php if ($nextMonthPayment): ?>
                                <div class="payment-info">
                                    <div class="info-row highlight">
                                        <div class="info-label">Next Payment Due:</div>
                                        <div class="info-value">
                                            <strong><?php echo date('F Y', strtotime($nextMonthPayment['month_year'] . '-01')); ?></strong>
                                        </div>
                                    </div>
                                    <div class="info-row highlight">
                                        <div class="info-label">Due Date:</div>
                                        <div class="info-value">
                                            <?php echo date('M j, Y', strtotime($nextMonthPayment['due_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="info-row highlight">
                                        <div class="info-label">Amount Due:</div>
                                        <div class="info-value">
                                            <strong>₱<?php echo number_format($nextMonthPayment['amount'], 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($renter): ?>
                                <div class="info-row">
                                    <div class="info-label">Payment Status:</div>
                                    <div class="info-value">All payments are up to date</div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <!-- Show all details for other statuses -->
                            <div class="info-row">
                                <div class="info-label">Application ID:</div>
                                <div class="info-value">#<?php echo $application['id']; ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Stall Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['stall_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Market:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['market_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Section:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['section_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Business Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['business_name']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Application Type:</div>
                                <div class="info-value"><?php echo ucfirst($application['application_type']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Application Date:</div>
                                <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($application['application_date'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="application-actions">
                        <?php if ($application['status'] === 'approved'): ?>
                            <!-- Show Pay button for approved status -->
                            <a href="payment_rent.php?application_id=<?= $application['id'] ?>" class="pay-button">
                                Pay Rent
                            </a>
                        <?php elseif ($application['status'] === 'payment_phase'): ?>
                            <!-- Show Pay Now button for payment_phase status -->
                            <a href="pay_application_fee.php?application_id=<?= $application['id'] ?>" class="pay-button">
                                Pay Application Fee
                            </a>
                        <?php else: ?>
                            <!-- Show View Full Application button for other statuses -->
                            <a href="view_application.php?application_id=<?= $application['id'] ?>" class="view-button">
                                View Full Application
                            </a>
                        <?php endif; ?>

                        <?php if ($application['status'] === 'rejected' || $application['status'] === 'cancelled'): ?>
                            <a href="../../market_portal/market_portal.php?user_id=<?php echo $user_id; ?>&name=<?php echo urlencode($full_name); ?>&email=<?php echo urlencode($email); ?>" 
                               class="apply-button">
                                Apply Again
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <footer class="apply-stall-footer">
            <a href="../../citizen_portal/dashboard.php" class="back-link">← Back to Dashboard</a>
        </footer>
    </div>
</div>
</body>
</html>