<?php
session_start();
require_once '../../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    $_SESSION['error'] = "No application specified.";
    header('Location: ../apply_stall.php');
    exit;
}

// Fetch application details for payment phase
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
            s.name AS stall_name, 
            s.price AS stall_price,
            m.name AS market_name,
            sr.class_name,
            sr.price as stall_rights_price,
            sec.name AS section_name
        FROM applications a
        LEFT JOIN stalls s ON a.stall_id = s.id
        LEFT JOIN maps m ON s.map_id = m.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['error'] = "Application not found or access denied.";
        header('Location: ../apply_stall.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: ../apply_stall.php');
    exit;
}

// Calculate fees
$application_fee = 100.00;
$security_bond = 10000.00;
$stall_rights_fee = $application['stall_rights_price'] ?? 0;
$total_amount = ($application['stall_price'] ?? 0) + $stall_rights_fee + $application_fee + $security_bond;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ready for Payment - Municipal Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .payment-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .fee-breakdown {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .fee-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .fee-item:last-child {
            border-bottom: none;
        }

        .fee-label {
            color: #374151;
        }

        .fee-value {
            font-weight: 600;
            color: #059669;
        }

        .fee-total {
            border-top: 2px solid #059669;
            padding-top: 1rem;
            margin-top: 0.5rem;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .btn-pay {
            background-color: #8b5cf6;
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-pay:hover {
            background-color: #7c3aed;
        }
    </style>
</head>
<body class="bg-gray-50">
     <?php include '../../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8 max-w-6xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Ready for Payment</h1>
            <p class="text-gray-600">Application ID: #<?= $application['id'] ?></p>
        </div>

        <!-- Status Card -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-yellow-800 mb-2">💰 Application Status: Ready for Payment</h2>
                    <p class="text-yellow-700">Your application has been approved! Please proceed with payment to secure your stall.</p>
                </div>
                <div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-full font-semibold">
                    Payment Phase
                </div>
            </div>
        </div>

        <!-- PAYMENT PHASE SECTION -->
        <div class="payment-section">
            <div class="text-center mb-6">
                <h2 class="text-3xl font-bold text-white mb-2">Ready for Payment</h2>
                <p class="text-white/90 text-lg">Your application has been approved! Please complete the payment to proceed.</p>
            </div>
            
            <div class="fee-breakdown">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Payment Summary</h3>
                <div class="fee-item">
                    <span class="fee-label">Monthly Stall Rent:</span>
                    <span class="fee-value">₱<?= number_format($application['stall_price'], 2) ?></span>
                </div>
                <div class="fee-item">
                    <span class="fee-label">Stall Rights Fee (Class <?= $application['class_name'] ?>):</span>
                    <span class="fee-value">₱<?= number_format($stall_rights_fee, 2) ?></span>
                </div>
                <div class="fee-item">
                    <span class="fee-label">Application Fee:</span>
                    <span class="fee-value">₱<?= number_format($application_fee, 2) ?></span>
                </div>
                <div class="fee-item">
                    <span class="fee-label">Security Bond:</span>
                    <span class="fee-value">₱<?= number_format($security_bond, 2) ?></span>
                </div>
                <div class="fee-item fee-total">
                    <span class="fee-label">Total Amount Due:</span>
                    <span class="fee-value">₱<?= number_format($total_amount, 2) ?></span>
                </div>
                
                <div class="mt-6 text-center">
                    <button onclick="location.href='../../digital_card/market_payment_details.php?application_id=<?= $application_id ?>'" 
                            class="btn-pay px-8 py-3 text-lg font-semibold">
                        💳 Proceed to Payment
                    </button>
                    <p class="text-sm text-gray-600 mt-2">Secure payment gateway • Multiple payment options available</p>
                </div>
            </div>
        </div>

        <!-- Application Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Application Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600">Business Name</p>
                        <p class="font-medium"><?= htmlspecialchars($application['business_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Market</p>
                        <p class="font-medium"><?= htmlspecialchars($application['market_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Stall Class</p>
                        <p class="font-medium"><?= htmlspecialchars($application['class_name']) ?></p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600">Section</p>
                        <p class="font-medium"><?= htmlspecialchars($application['section_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Monthly Rent</p>
                        <p class="font-medium text-lg">₱<?= number_format($application['stall_price'], 2) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Application Date</p>
                        <p class="font-medium"><?= date('F j, Y g:i A', strtotime($application['application_date'])) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-green-800 mb-3">Payment Instructions</h3>
            <ol class="list-decimal list-inside text-green-700 space-y-2">
                <li>Click the "Proceed to Payment" button above</li>
                <li>You will be redirected to our secure payment gateway</li>
                <li>Choose your preferred payment method</li>
                <li>Complete the payment process</li>
                <li>Keep the payment confirmation for your records</li>
                <li>Return to this portal after payment to complete document submission</li>
            </ol>
        </div>

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-3">What Happens After Payment?</h3>
            <ul class="list-disc list-inside text-blue-700 space-y-2">
                <li>Your payment will be verified within 24 hours</li>
                <li>Once verified, you'll receive your stall rights certificate</li>
                <li>Lease contract will be generated and sent to you</li>
                <li>You can start setting up your stall after final approval</li>
                <li>Monthly rental payments will begin from your start date</li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <button onclick="location.href='../market_card.php'" 
                    class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 w-full sm:w-auto">
                ← Back to Dashboard
            </button>
            <div class="flex gap-3 w-full sm:w-auto">
                <button onclick="window.print()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 w-full sm:w-auto">
                    Print Summary
                </button>
                <button onclick="location.href='../../digital_card/market_payment_details.php?application_id=<?= $application_id ?>'" 
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 w-full sm:w-auto">
                    Proceed to Payment
                </button>
            </div>
        </div>
    </div>
</body>
</html>