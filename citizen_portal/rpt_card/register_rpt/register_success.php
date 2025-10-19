<?php
// register_success.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../../../db/RPT/rpt_db.php';

// Initialize application variable
$application = null;
$error_message = '';

// Get application ID from URL
$application_id = $_GET['application_id'] ?? 0;

// Fetch application details
if ($application_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM rpt_applications WHERE id = ? AND user_id = ?");
        $stmt->execute([$application_id, $_SESSION['user_id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            $error_message = "Application not found or you don't have permission to view it.";
        }
    } catch (PDOException $e) {
        error_log("Error fetching application: " . $e->getMessage());
        $error_message = "An error occurred while retrieving application details.";
    }
} else {
    $error_message = "No application ID provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - RPT System</title>
    <link rel="stylesheet" href="../../citizen_portal/navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="register_success.css">
</head>
<body>
    <?php include '../../../citizen_portal/navbar.php'; ?>
    
    <div class="rpt-register-container">
        <div class="success-container">
            <div class="success-message">
                <?php if ($application): ?>
                    <i class="fas fa-check-circle"></i>
                    <h3>Application Submitted Successfully!</h3>
                    <p>Property registration submitted successfully! Our assessor will visit your property for assessment.</p>
                    
                    <div class="application-details">
                        <h4>Application Details:</h4>
                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="detail-label">Application ID:</span>
                                <span class="detail-value">RPT-<?php echo str_pad($application['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value status-pending">Pending Assessment</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Property Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['property_address']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Property Type:</span>
                                <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $application['property_type'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Submitted:</span>
                                <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($application['application_date'])); ?></span>
                            </div>
                            <?php if ($application['application_type'] === 'transfer' && !empty($application['previous_owner'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Previous Owner:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['previous_owner']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <h3>Application Information Not Available</h3>
                    <p><?php echo $error_message ?: 'Unable to retrieve application details.'; ?></p>
                    
                    <div class="error-message">
                        <h4>What to do next?</h4>
                        <p>Please check your dashboard to view your applications or contact support if you believe this is an error.</p>
                    </div>
                <?php endif; ?>
                
                <div class="success-actions">
                    <button type="button" class="btn-secondary" onclick="window.location.href='../rpt_dashboard.php'">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </button>
                    <button type="button" class="btn-primary" onclick="window.location.href='register_rpt.php'">
                        <i class="fas fa-plus"></i> Register Another Property
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>