<?php
session_start();
require_once '../../../db/RPT/rpt_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Guest';

// Fetch user's RPT applications
try {
    $stmt = $pdo->prepare("
        SELECT * FROM rpt_applications 
        WHERE user_id = ? 
        ORDER BY application_date DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching applications: " . $e->getMessage();
}

// Fetch documents and assessment schedules for each application
if (!empty($applications)) {
    foreach ($applications as &$application) {
        // Fetch documents
        $stmt = $pdo->prepare("
            SELECT * FROM rpt_documents 
            WHERE application_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$application['id']]);
        $application['documents'] = $stmt->fetchAll();

        // Fetch assessment schedule if status is for_assessment
        if ($application['status'] === 'for_assessment') {
            $stmt = $pdo->prepare("
                SELECT * FROM rpt_assessment_schedule 
                WHERE application_id = ? 
                AND status = 'scheduled'
                ORDER BY visit_date ASC
            ");
            $stmt->execute([$application['id']]);
            $application['assessment_schedule'] = $stmt->fetch();
        }
    }
    unset($application); // break the reference
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My RPT Applications - RPT System</title>
    <link rel="stylesheet" href="../../citizen_portal/navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="rpt_application.css">
</head>
<body>
    <?php include '../../../citizen_portal/navbar.php'; ?>
    
    <div class="rpt-application-container">
        <div class="rpt-application-header">
            <h1><i class="fas fa-file-alt"></i> My RPT Applications</h1>
            <p>View and track your Real Property Tax applications</p>
            <div class="header-actions">
                <a href="../rpt_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="register_rpt.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Register New Property
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($applications)): ?>
            <div class="no-applications">
                <div class="no-applications-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3>No Applications Found</h3>
                <p>You haven't submitted any RPT applications yet.</p>
                <a href="register_rpt.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Register Property
                </a>
            </div>
        <?php else: ?>
            <div class="applications-list">
                <?php foreach ($applications as $application): ?>
                    <!-- Assessment Notification for scheduled visits -->
                    <?php if ($application['status'] === 'for_assessment' && !empty($application['assessment_schedule'])): ?>
                        <div class="assessment-notification">
                            <div class="notification-header">
                                <div class="notification-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="notification-content">
                                    <h3>Property Assessment Scheduled</h3>
                                    <p>An assessor has been scheduled to visit your property for evaluation.</p>
                                </div>
                            </div>
                            
                            <div class="assessment-details-grid">
                                <div class="assessment-detail-item highlight">
                                    <label>Visit Date:</label>
                                    <span>
                                        <i class="fas fa-calendar-day"></i>
                                        <?php echo date('F j, Y (l)', strtotime($application['assessment_schedule']['visit_date'])); ?>
                                    </span>
                                </div>
                                <div class="assessment-detail-item">
                                    <label>Assigned Assessor:</label>
                                    <span>
                                        <i class="fas fa-user-tie"></i>
                                        <?php echo htmlspecialchars($application['assessment_schedule']['assessor_name']); ?>
                                    </span>
                                </div>
                                <div class="assessment-detail-item">
                                    <label>Property Location:</label>
                                    <span>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($application['property_address'] . ', ' . $application['property_barangay']); ?>
                                    </span>
                                </div>
                                <div class="assessment-detail-item">
                                    <label>Scheduled On:</label>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($application['assessment_schedule']['scheduled_at'])); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($application['assessment_schedule']['notes'])): ?>
                                <div class="assessment-detail-item" style="margin-top: 1rem;">
                                    <label>Additional Notes:</label>
                                    <span><?php echo htmlspecialchars($application['assessment_schedule']['notes']); ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Urgent notice for upcoming visits (within 2 days) -->
                            <?php 
                            $visitDate = new DateTime($application['assessment_schedule']['visit_date']);
                            $today = new DateTime();
                            $daysUntilVisit = $today->diff($visitDate)->days;
                            ?>
                            <?php if ($daysUntilVisit <= 2 && $visitDate >= $today): ?>
                                <div class="urgent-notice">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <p>
                                        <strong>Important:</strong> 
                                        Please ensure someone is available at the property during the scheduled visit. 
                                        <?php if ($daysUntilVisit === 0): ?>
                                            The assessor is visiting <strong>today</strong>.
                                        <?php elseif ($daysUntilVisit === 1): ?>
                                            The assessor is visiting <strong>tomorrow</strong>.
                                        <?php else: ?>
                                            The assessor is visiting in <strong><?php echo $daysUntilVisit; ?> days</strong>.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="application-card" data-application-id="<?php echo $application['id']; ?>">
                        <div class="application-header">
                            <div class="application-info">
                                <h3>Application #<?php echo $application['id']; ?></h3>
                                <div class="application-meta">
                                    <span class="application-type">
                                        <i class="fas fa-tag"></i>
                                        <?php echo ucfirst($application['application_type']); ?> Application
                                    </span>
                                    <span class="application-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($application['application_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="application-status">
                                <span class="status-badge status-<?php echo strtolower($application['status']); ?>">
                                    <?php 
                                    $statusDisplay = [
                                        'pending' => 'Pending Review',
                                        'for_assessment' => 'For Assessment',
                                        'assessed' => 'Assessed',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        'cancelled' => 'Cancelled'
                                    ];
                                    echo $statusDisplay[$application['status']] ?? ucfirst($application['status']);
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="application-details">
                            <h4><i class="fas fa-home"></i> Property Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Property Type:</label>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $application['property_type'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <label>Property Location:</label>
                                    <span><?php echo htmlspecialchars($application['property_address'] . ', ' . $application['property_barangay'] . ', ' . $application['property_municipality']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <label>TDN Number:</label>
                                    <span class="<?php echo empty($application['tdn_number']) ? 'text-muted' : ''; ?>">
                                        <?php echo !empty($application['tdn_number']) ? $application['tdn_number'] : 'Not assigned yet'; ?>
                                    </span>
                                </div>
                                <?php if ($application['application_type'] === 'transfer'): ?>
                                <div class="detail-item">
                                    <label>Previous TDN:</label>
                                    <span><?php echo htmlspecialchars($application['previous_tdn'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <label>Previous Owner:</label>
                                    <span><?php echo htmlspecialchars($application['previous_owner'] ?? 'N/A'); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Personal Information Section -->
                        <div class="personal-info-section">
                            <h4><i class="fas fa-user"></i> Personal Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Full Name:</label>
                                    <span><?php echo htmlspecialchars($application['first_name'] . ' ' . ($application['middle_name'] ? $application['middle_name'] . ' ' : '') . $application['last_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Contact:</label>
                                    <span><?php echo htmlspecialchars($application['contact_number']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Email:</label>
                                    <span><?php echo htmlspecialchars($application['email']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Address:</label>
                                    <span><?php echo htmlspecialchars($application['house_number'] . ' ' . $application['street'] . ', ' . $application['barangay'] . ', ' . $application['city'] . ' ' . $application['zip_code']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <div class="documents-section">
                            <h4><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <?php if (!empty($application['documents'])): ?>
                                <div class="documents-grid">
                                    <?php foreach ($application['documents'] as $document): ?>
                                        <div class="document-item">
                                            <div class="document-icon">
                                                <?php 
                                                $icon = 'fa-file';
                                                if (strpos($document['document_type'], 'tct') !== false) $icon = 'fa-file-contract';
                                                elseif (strpos($document['document_type'], 'deed') !== false) $icon = 'fa-file-signature';
                                                elseif (strpos($document['document_type'], 'id') !== false) $icon = 'fa-id-card';
                                                elseif (strpos($document['document_type'], 'clearance') !== false) $icon = 'fa-file-certificate';
                                                elseif (strpos($document['document_type'], 'tax') !== false) $icon = 'fa-file-invoice';
                                                elseif (strpos($document['document_type'], 'plan') !== false) $icon = 'fa-map';
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="document-info">
                                                <span class="document-name"><?php echo ucfirst(str_replace('_', ' ', $document['document_type'])); ?></span>
                                                <span class="document-date"><?php echo date('M j, Y g:i A', strtotime($document['uploaded_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-documents">No documents uploaded for this application.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Assessment Details (if assessed) -->
                        <?php if ($application['status'] === 'assessed' || $application['status'] === 'approved'): ?>
                            <div class="assessment-section">
                                <h4><i class="fas fa-clipboard-check"></i> Assessment Details</h4>
                                <div class="assessment-grid">
                                    <?php if (!empty($application['land_classification'])): ?>
                                        <div class="assessment-item">
                                            <label>Land Classification:</label>
                                            <span><?php echo ucfirst(str_replace('_', ' ', $application['land_classification'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($application['land_actual_use'])): ?>
                                        <div class="assessment-item">
                                            <label>Land Use:</label>
                                            <span><?php echo ucfirst($application['land_actual_use']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($application['market_value_land'])): ?>
                                        <div class="assessment-item">
                                            <label>Market Value (Land):</label>
                                            <span>₱<?php echo number_format($application['market_value_land'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($application['market_value_improvement'])): ?>
                                        <div class="assessment-item">
                                            <label>Market Value (Improvement):</label>
                                            <span>₱<?php echo number_format($application['market_value_improvement'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($application['total_assessed_value'])): ?>
                                        <div class="assessment-item highlight">
                                            <label>Total Assessed Value:</label>
                                            <span>₱<?php echo number_format($application['total_assessed_value'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($application['total_tax_due'])): ?>
                                        <div class="assessment-item highlight">
                                            <label>Total Tax Due:</label>
                                            <span>₱<?php echo number_format($application['total_tax_due'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Application Actions -->
                        <div class="application-actions">
                            <?php if ($application['status'] === 'approved' && !empty($application['tdn_number'])): ?>
                                <a href="pay_tax.php?application_id=<?php echo $application['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-credit-card"></i> Pay Tax
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add smooth scrolling for better user experience
        document.querySelectorAll('.application-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!e.target.closest('.btn')) {
                    this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });

        // Add hover effects for cards
        document.querySelectorAll('.application-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Auto-refresh the page every 5 minutes to check for new assessment schedules
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>