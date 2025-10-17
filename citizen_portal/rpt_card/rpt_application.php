<?php
session_start();
require_once '../../db/RPT/rpt_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// Fetch documents for each application
if (!empty($applications)) {
    foreach ($applications as &$application) {
        $stmt = $pdo->prepare("
            SELECT * FROM rpt_documents 
            WHERE application_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$application['id']]);
        $application['documents'] = $stmt->fetchAll();
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
    <link rel="stylesheet" href="rpt_application.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../citizen_portal/navbar.php'; ?>
    
    <div class="rpt-application-container">
        <div class="rpt-application-header">
            <h1><i class="fas fa-file-alt"></i> My RPT Applications</h1>
            <p>View and track your Real Property Tax applications</p>
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
                <a href="register_rpt.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Register Property
                </a>
            </div>
        <?php else: ?>
            <div class="applications-list">
                <?php foreach ($applications as $application): ?>
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
                                            <div class="document-actions">
                                                <a href="<?php echo $document['file_path']; ?>" 
                                                   target="_blank" 
                                                   class="btn-view" 
                                                   title="View Document">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo $document['file_path']; ?>" 
                                                   download 
                                                   class="btn-download" 
                                                   title="Download Document">
                                                    <i class="fas fa-download"></i>
                                                </a>
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
                                <a href="pay_tax.php?application_id=<?php echo $application['id']; ?>" class="btn-primary">
                                    <i class="fas fa-credit-card"></i> Pay Tax
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($application['status'] === 'pending' || $application['status'] === 'for_assessment'): ?>
                                <button type="button" class="btn-secondary" onclick="cancelApplication(<?php echo $application['id']; ?>)">
                                    <i class="fas fa-times"></i> Cancel Application
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-info view-details" onclick="toggleApplicationDetails(<?php echo $application['id']; ?>)">
                                <i class="fas fa-chevron-down"></i> View Details
                            </button>
                        </div>

                        <!-- Detailed View (Hidden by default) -->
                        <div class="application-details-expanded" id="details-<?php echo $application['id']; ?>" style="display: none;">
                            <div class="details-grid">
                                <div class="personal-info">
                                    <h5>Personal Information</h5>
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
                                
                                <div class="timeline">
                                    <h5>Application Timeline</h5>
                                    <div class="timeline-steps">
                                        <div class="timeline-step <?php echo $application['status'] !== 'cancelled' && $application['status'] !== 'rejected' ? 'completed' : ''; ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-paper-plane"></i>
                                            </div>
                                            <div class="step-content">
                                                <span class="step-title">Application Submitted</span>
                                                <span class="step-date"><?php echo date('M j, Y g:i A', strtotime($application['application_date'])); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="timeline-step <?php echo in_array($application['status'], ['for_assessment', 'assessed', 'approved']) ? 'completed' : ''; ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-clipboard-check"></i>
                                            </div>
                                            <div class="step-content">
                                                <span class="step-title">Property Assessment</span>
                                                <span class="step-date">
                                                    <?php if ($application['assessment_date']): ?>
                                                        <?php echo date('M j, Y', strtotime($application['assessment_date'])); ?>
                                                    <?php else: ?>
                                                        Pending
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="timeline-step <?php echo $application['status'] === 'approved' ? 'completed' : ''; ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="step-content">
                                                <span class="step-title">Approval</span>
                                                <span class="step-date">
                                                    <?php if ($application['status'] === 'approved'): ?>
                                                        Approved
                                                    <?php else: ?>
                                                        Pending
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleApplicationDetails(applicationId) {
            const detailsElement = document.getElementById('details-' + applicationId);
            const button = event.target.closest('.view-details');
            
            if (detailsElement.style.display === 'none') {
                detailsElement.style.display = 'block';
                button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
            } else {
                detailsElement.style.display = 'none';
                button.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
            }
        }

        function cancelApplication(applicationId) {
            if (confirm('Are you sure you want to cancel this application? This action cannot be undone.')) {
                // You can implement AJAX cancellation here
                fetch('cancel_application.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'application_id=' + applicationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Application cancelled successfully.');
                        location.reload();
                    } else {
                        alert('Error cancelling application: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error cancelling application.');
                    console.error('Error:', error);
                });
            }
        }

        // Add smooth scrolling to details
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const applicationCard = this.closest('.application-card');
                applicationCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });
    </script>
</body>
</html>