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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        .rpt-application-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Header Section */
        .rpt-application-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .rpt-application-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #10b981, #ef4444);
        }

        .rpt-application-header h1 {
            font-size: 2.5rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .rpt-application-header h1 i {
            color: #3b82f6;
            margin-right: 1rem;
        }

        .rpt-application-header p {
            font-size: 1.2rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        .header-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Button Styles */
        .btn {
            padding: 0.9rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            border: none;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(100, 116, 139, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        /* Application Cards */
        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .application-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .application-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .application-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #10b981);
        }

        .application-header {
            padding: 2rem 2rem 1rem;
            display: flex;
            justify-content: between;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .application-info {
            flex: 1;
        }

        .application-info h3 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .application-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .application-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .application-meta i {
            color: #3b82f6;
        }

        .application-status {
            flex-shrink: 0;
        }

        .status-badge {
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-for_assessment { background: #dbeafe; color: #2563eb; }
        .status-assessed { background: #dcfce7; color: #16a34a; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }

        /* Content Sections */
        .application-details,
        .personal-info-section,
        .documents-section,
        .assessment-section {
            padding: 1.5rem 2rem;
            border-top: 1px solid #f1f5f9;
        }

        .application-details h4,
        .personal-info-section h4,
        .documents-section h4,
        .assessment-section h4 {
            color: #1e293b;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .application-details h4 i,
        .personal-info-section h4 i,
        .documents-section h4 i,
        .assessment-section h4 i {
            color: #3b82f6;
        }

        /* Grid Layouts */
        .detail-grid,
        .info-grid,
        .assessment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .detail-item,
        .info-item,
        .assessment-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-item label,
        .info-item label,
        .assessment-item label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-item span,
        .info-item span,
        .assessment-item span {
            color: #1e293b;
            font-weight: 600;
            font-size: 1rem;
        }

        .text-muted {
            color: #94a3b8 !important;
            font-style: italic;
        }

        .assessment-item.highlight {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 1rem;
            border-radius: 12px;
            border-left: 4px solid #3b82f6;
        }

        .assessment-item.highlight span {
            color: #1e40af;
            font-size: 1.1rem;
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .document-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .document-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }

        .document-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border-radius: 10px;
            color: white;
            font-size: 1.2rem;
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .document-date {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Document actions removed - view and download buttons are gone */

        /* Application Actions */
        .application-actions {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }

        /* Error and Empty States */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            color: #dc2626;
        }

        .error-message i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }

        .no-applications {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .no-applications-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .no-applications h3 {
            color: #475569;
            margin-bottom: 1rem;
        }

        .no-applications p {
            color: #64748b;
            margin-bottom: 2rem;
        }

        .no-documents {
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            padding: 2rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px dashed #e2e8f0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .rpt-application-container {
                padding: 1rem;
            }

            .rpt-application-header {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }

            .rpt-application-header h1 {
                font-size: 2rem;
            }

            .application-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1.5rem;
            }

            .application-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .detail-grid,
            .info-grid,
            .assessment-grid {
                grid-template-columns: 1fr;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .application-details,
            .personal-info-section,
            .documents-section,
            .assessment-section {
                padding: 1rem 1.5rem;
            }

            .header-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .rpt-application-header h1 {
                font-size: 1.75rem;
            }

            .application-info h3 {
                font-size: 1.25rem;
            }

            .document-item {
                flex-direction: column;
                text-align: center;
            }
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
                <a href="register_rpt.php" class="btn btn-primary">
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
                                            <!-- View and Download buttons removed -->
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
    </script>
</body>
</html>