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

// Fetch documents, assessment schedules, and assessment data for each application
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

        // Fetch land assessment data if application is assessed or approved
        if ($application['status'] === 'assessed' || $application['status'] === 'approved') {
            // Fetch land data
            $stmt = $pdo->prepare("
                SELECT l.*, lat.* 
                FROM land l 
                LEFT JOIN land_assessment_tax lat ON l.land_id = lat.land_id 
                WHERE l.application_id = ? 
                ORDER BY lat.assessment_year DESC 
                LIMIT 1
            ");
            $stmt->execute([$application['id']]);
            $application['land_assessment'] = $stmt->fetch();

            // Fetch building data if property type is land_with_house
            if ($application['property_type'] === 'land_with_house' && $application['land_assessment']) {
                $stmt = $pdo->prepare("
                    SELECT b.*, bat.* 
                    FROM building b 
                    LEFT JOIN building_assessment_tax bat ON b.building_id = bat.building_id 
                    WHERE b.land_id = ? 
                    ORDER BY bat.assessment_year DESC 
                    LIMIT 1
                ");
                $stmt->execute([$application['land_assessment']['land_id']]);
                $application['building_assessment'] = $stmt->fetch();
            }

            // Fetch total tax
            if ($application['land_assessment']) {
                $stmt = $pdo->prepare("
                    SELECT tt.* 
                    FROM total_tax tt 
                    WHERE tt.land_tax_id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$application['land_assessment']['land_tax_id']]);
                $application['total_tax'] = $stmt->fetch();
            }

            // Fetch quarterly payments
            if ($application['land_assessment']) {
                $stmt = $pdo->prepare("
                    SELECT * FROM quarterly 
                    WHERE land_tax_id = ? 
                    ORDER BY quarter_no
                ");
                $stmt->execute([$application['land_assessment']['land_tax_id']]);
                $application['quarterly_payments'] = $stmt->fetchAll();
            }
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
    <style>
        .assessment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .assessment-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            border-left: 4px solid #007bff;
        }
        .assessment-card.building {
            border-left-color: #28a745;
        }
        .assessment-card.total {
            border-left-color: #dc3545;
        }
        .assessment-item {
            display: flex;
            justify-content: between;
            margin-bottom: 0.5rem;
        }
        .assessment-item label {
            font-weight: 600;
            color: #495057;
            min-width: 200px;
        }
        .assessment-item .value {
            font-weight: 500;
            color: #212529;
        }
        .highlight {
            color: #dc3545;
            font-weight: 600;
        }
        .quarterly-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .quarterly-table th,
        .quarterly-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .quarterly-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .payment-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .payment-status.unpaid {
            background-color: #fff3cd;
            color: #856404;
        }
        .payment-status.paid {
            background-color: #d1edff;
            color: #004085;
        }
        .payment-status.overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
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
                <a href="../rpt_dashboard.php" class="btn btn-primary">
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
                                
                                <!-- Land Assessment -->
                                <?php if (!empty($application['land_assessment'])): ?>
                                    <div class="assessment-card">
                                        <h5><i class="fas fa-map"></i> Land Assessment</h5>
                                        <div class="assessment-grid">
                                            <div class="assessment-item">
                                                <label>TDN Number:</label>
                                                <span class="value"><?php echo htmlspecialchars($application['land_assessment']['tdn_no'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Lot Area:</label>
                                                <span class="value"><?php echo number_format($application['land_assessment']['lot_area'], 2); ?> sqm</span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Land Use:</label>
                                                <span class="value"><?php echo htmlspecialchars($application['land_assessment']['land_use']); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Market Value per sqm:</label>
                                                <span class="value">₱<?php echo number_format($application['land_assessment']['land_value_per_sqm'], 2); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Assessment Level:</label>
                                                <span class="value"><?php echo ($application['land_assessment']['land_assessed_lvl'] * 100); ?>%</span>
                                            </div>
                                            <div class="assessment-item highlight">
                                                <label>Assessed Value:</label>
                                                <span class="value">₱<?php echo number_format($application['land_assessment']['land_assessed_value'], 2); ?></span>
                                            </div>
                                            <div class="assessment-item highlight">
                                                <label>Annual Tax:</label>
                                                <span class="value">₱<?php echo number_format($application['land_assessment']['land_total_tax'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Building Assessment -->
                                <?php if (!empty($application['building_assessment'])): ?>
                                    <div class="assessment-card building">
                                        <h5><i class="fas fa-building"></i> Building Assessment</h5>
                                        <div class="assessment-grid">
                                            <div class="assessment-item">
                                                <label>TDN Number:</label>
                                                <span class="value"><?php echo htmlspecialchars($application['building_assessment']['tdn_no'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Building Area:</label>
                                                <span class="value"><?php echo number_format($application['building_assessment']['building_area'], 2); ?> sqm</span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Building Type:</label>
                                                <span class="value"><?php echo htmlspecialchars($application['building_assessment']['building_type']); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Construction Type:</label>
                                                <span class="value"><?php echo htmlspecialchars($application['building_assessment']['construction_type']); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Market Value per sqm:</label>
                                                <span class="value">₱<?php echo number_format($application['building_assessment']['building_value_per_sqm'], 2); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Assessment Level:</label>
                                                <span class="value"><?php echo ($application['building_assessment']['building_assessed_lvl'] * 100); ?>%</span>
                                            </div>
                                            <div class="assessment-item highlight">
                                                <label>Assessed Value:</label>
                                                <span class="value">₱<?php echo number_format($application['building_assessment']['building_assessed_value'], 2); ?></span>
                                            </div>
                                            <div class="assessment-item highlight">
                                                <label>Annual Tax:</label>
                                                <span class="value">₱<?php echo number_format($application['building_assessment']['building_total_tax'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Total Tax Summary -->
                                <?php if (!empty($application['total_tax'])): ?>
                                    <div class="assessment-card total">
                                        <h5><i class="fas fa-calculator"></i> Tax Summary</h5>
                                        <div class="assessment-grid">
                                            <div class="assessment-item">
                                                <label>Total Assessed Value:</label>
                                                <span class="value highlight">₱<?php echo number_format($application['total_tax']['total_assessed_value'], 2); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Total Annual Tax:</label>
                                                <span class="value highlight">₱<?php echo number_format($application['total_tax']['total_tax'], 2); ?></span>
                                            </div>
                                            <div class="assessment-item">
                                                <label>Payment Type:</label>
                                                <span class="value"><?php echo ucfirst($application['total_tax']['payment_type']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Quarterly Payments -->
                                <?php if (!empty($application['quarterly_payments'])): ?>
                                    <div class="assessment-card">
                                        <h5><i class="fas fa-calendar-alt"></i> Quarterly Payments</h5>
                                        <table class="quarterly-table">
                                            <thead>
                                                <tr>
                                                    <th>Quarter</th>
                                                    <th>Due Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date Paid</th>
                                                    <th>OR Number</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($application['quarterly_payments'] as $payment): ?>
                                                    <tr>
                                                        <td>Q<?php echo $payment['quarter_no']; ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($payment['due_date'])); ?></td>
                                                        <td>₱<?php echo number_format($payment['tax_amount'], 2); ?></td>
                                                        <td>
                                                            <span class="payment-status <?php echo $payment['status']; ?>">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php echo $payment['date_paid'] ? date('M j, Y', strtotime($payment['date_paid'])) : 'Not Paid'; ?>
                                                        </td>
                                                        <td><?php echo $payment['or_no'] ?: 'N/A'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
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