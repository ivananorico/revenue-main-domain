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
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .rpt-register-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Success Container */
        .success-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .success-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .success-message {
            padding: 3rem 2.5rem;
            text-align: center;
            position: relative;
        }

        /* Success Icon */
        .success-message i {
            font-size: 5rem;
            color: #10b981;
            margin-bottom: 1.5rem;
            display: block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Success Text */
        .success-message h3 {
            font-size: 1.8rem;
            color: #1e293b;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .success-message p {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto 2.5rem;
        }

        /* Application Details */
        .application-details {
            background-color: #f1f5f9;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
            border-left: 4px solid #3b82f6;
        }

        .application-details h4 {
            color: #1e293b;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .application-details h4:before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 20px;
            background-color: #3b82f6;
            margin-right: 10px;
            border-radius: 3px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
        }

        .detail-label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        .status-pending {
            color: #f59e0b;
            background-color: #fffbeb;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            width: fit-content;
        }

        /* Error Message */
        .error-message {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
            border-left: 4px solid #ef4444;
        }

        .error-message i {
            font-size: 3rem;
            color: #ef4444;
            margin-bottom: 1rem;
            display: block;
        }

        .error-message h4 {
            color: #dc2626;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .error-message p {
            color: #7f1d1d;
            margin-bottom: 1.5rem;
        }

        /* Success Actions */
        .success-actions {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }

        .btn-primary, .btn-secondary {
            padding: 0.9rem 1.8rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .success-message {
                padding: 2rem 1.5rem;
            }
            
            .success-message h3 {
                font-size: 1.5rem;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .success-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .rpt-register-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }
            
            .success-message {
                padding: 1.5rem 1rem;
            }
            
            .application-details {
                padding: 1.5rem;
            }
        }
    </style>
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