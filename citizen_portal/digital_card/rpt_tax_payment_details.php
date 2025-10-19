<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if we're coming from pay_tax.php with URL parameters
if (isset($_GET['application_id']) && isset($_GET['amount'])) {
    // Create payment data from URL parameters
    $application_id = $_GET['application_id'];
    $amount = $_GET['amount'];
    $payment_for = $_GET['payment_for'] ?? 'single_quarter';
    $quarter_id = $_GET['quarter_id'] ?? null;
    
    // Fetch application and property details
    require_once '../../db/RPT/rpt_db.php';
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ra.*,
                l.land_id,
                l.location as property_address,
                l.barangay,
                l.municipality,
                l.lot_area,
                l.land_use,
                l.tdn_no as land_tdn,
                lat.assessment_year,
                lat.land_tax_id
            FROM rpt_applications ra
            LEFT JOIN land l ON ra.id = l.application_id
            LEFT JOIN land_assessment_tax lat ON l.land_id = lat.land_id
            WHERE ra.id = ? AND ra.user_id = ? AND ra.status = 'approved'
            ORDER BY lat.assessment_year DESC 
            LIMIT 1
        ");
        $stmt->execute([$application_id, $_SESSION['user_id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            // Get payment details for the specific quarter if quarter_id is provided
            $payment_details = [];
            if ($quarter_id) {
                $payment_stmt = $pdo->prepare("
                    SELECT * FROM quarterly 
                    WHERE quarter_id = ? AND land_tax_id = ?
                ");
                $payment_stmt->execute([$quarter_id, $application['land_tax_id']]);
                $payment_details = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else if ($payment_for === 'all_quarters') {
                // Get all unpaid quarters
                $payment_stmt = $pdo->prepare("
                    SELECT * FROM quarterly 
                    WHERE land_tax_id = ? AND status IN ('unpaid', 'overdue')
                    ORDER BY quarter_no ASC
                ");
                $payment_stmt->execute([$application['land_tax_id']]);
                $payment_details = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Create payment data array
            $_SESSION['tax_payment_data'] = [
                'application_id' => $application_id,
                'land_id' => $application['land_id'],
                'property_address' => $application['property_address'],
                'land_tdn' => $application['land_tdn'],
                'assessment_year' => $application['assessment_year'],
                'lot_area' => $application['lot_area'],
                'land_use' => $application['land_use'],
                'amount' => $amount,
                'payment_for' => $payment_for,
                'quarter_id' => $quarter_id,
                'payment_details' => $payment_details
            ];
        }
    } catch (PDOException $e) {
        error_log("Payment data setup error: " . $e->getMessage());
    }
}

// Redirect if not logged in or no payment data
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tax_payment_data'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_data = $_SESSION['tax_payment_data'];
$full_name = $_SESSION['full_name'] ?? 'Guest';

// Database connection
require_once '../../db/RPT/rpt_db.php';

$error_message = '';

// Handle form submission - Payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    
    // Validate payment method
    if (!in_array($payment_method, ['maya', 'gcash'])) {
        $error_message = "Please select a valid payment method.";
    } else {
        try {
            // =============================================
            // CLEANUP ONLY EXPIRED PAYMENTS (NOT ALL)
            // =============================================
            
            // Only clear expired verification data, not all pending payments
            $clear_stmt = $pdo->prepare("
                UPDATE quarterly 
                SET verification_code = NULL, 
                    expires_at = NULL, 
                    verification_attempts = 0,
                    payment_method = NULL,
                    phone_number = NULL,
                    email = NULL,
                    verified_at = NULL
                WHERE land_tax_id IN (
                    SELECT land_tax_id FROM land_assessment_tax WHERE land_id = ?
                )
                AND status IN ('unpaid', 'overdue')
                AND expires_at < NOW()  -- ONLY expired ones
            ");
            $clear_stmt->execute([$payment_data['land_id']]);
            
            // =============================================
            // PREPARE PAYMENT DATA FOR PROCESSING
            // =============================================
            
            // Update payment data with selected method
            $payment_data['payment_method'] = $payment_method;
            
            // Store updated payment data in session
            $_SESSION['tax_payment_data'] = $payment_data;
            
            // Generate a unique transaction ID if not exists
            if (!isset($payment_data['transaction_id'])) {
                $payment_data['transaction_id'] = 'RPT_' . date('YmdHis') . '_' . $user_id;
                $_SESSION['tax_payment_data']['transaction_id'] = $payment_data['transaction_id'];
            }
            
            // Store payment timestamp
            $_SESSION['payment_start_time'] = time();
            
            // Redirect to payment processing page
            header('Location: rpt_tax_payment_process.php');
            exit;
            
        } catch (PDOException $e) {
            error_log("Payment method selection error: " . $e->getMessage());
            $error_message = "Failed to process payment method. Please try again.";
        }
    }
}

// Determine payment description
$quarter_labels = ['1' => '1st Quarter', '2' => '2nd Quarter', '3' => '3rd Quarter', '4' => '4th Quarter'];
$payment_description = '';
if ($payment_data['payment_for'] === 'all_quarters') {
    $payment_description = 'All Unpaid Quarters';
} elseif (!empty($payment_data['payment_details'])) {
    $payment_description = $quarter_labels[$payment_data['payment_details'][0]['quarter_no']] ?? 'Quarter ' . $payment_data['payment_details'][0]['quarter_no'];
} else {
    $payment_description = 'Property Tax Payment';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Method - Real Property Tax</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .payment-method {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .payment-method:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .payment-method.selected {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        
        .payment-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .maya-icon {
            background: linear-gradient(135deg, #00a3ff, #0055ff);
            color: white;
        }
        
        .gcash-icon {
            background: linear-gradient(135deg, #00a64f, #007a3d);
            color: white;
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
        
        .fee-total {
            border-top: 2px solid #059669;
            padding-top: 1rem;
            margin-top: 0.5rem;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .payment-details-item {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .cleanup-notice {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-unpaid {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Complete Your Tax Payment</h1>
            <p class="text-xl text-gray-600">Secure payment for your real property quarterly tax</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <!-- Cleanup Notice -->
            <div class="cleanup-notice">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-blue-700 font-medium">Security Feature: Only expired payment attempts are automatically cleaned up for your protection.</span>
                </div>
            </div>

            <!-- Property Summary -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Property Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Property Location</p>
                        <p class="font-semibold text-lg"><?= htmlspecialchars($payment_data['property_address']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Tax Declaration No.</p>
                        <p class="font-semibold"><?= htmlspecialchars($payment_data['land_tdn']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Assessment Year</p>
                        <p class="font-semibold"><?= htmlspecialchars($payment_data['assessment_year']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Land Area</p>
                        <p class="font-semibold"><?= number_format($payment_data['lot_area'], 2) ?> sqm</p>
                        
                        <p class="text-sm text-gray-600 mt-4">Land Use</p>
                        <p class="font-semibold"><?= htmlspecialchars($payment_data['land_use']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Property Owner</p>
                        <p class="font-semibold"><?= htmlspecialchars($full_name) ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column - Payment Methods -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Choose Payment Method</h2>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Direct form submission to self for cleanup processing -->
                    <form id="paymentForm" method="POST">
                        <!-- Maya Payment Method -->
                        <div class="payment-method mb-4" onclick="selectPaymentMethod('maya')">
                            <input type="radio" name="payment_method" value="maya" id="maya" class="hidden" required>
                            <div class="payment-icon maya-icon">
                                M
                            </div>
                            <h3 class="font-semibold text-lg text-gray-800 mb-2">Maya</h3>
                            <p class="text-gray-600 text-sm">Pay using Maya wallet</p>
                        </div>

                        <!-- GCash Payment Method -->
                        <div class="payment-method" onclick="selectPaymentMethod('gcash')">
                            <input type="radio" name="payment_method" value="gcash" id="gcash" class="hidden" required>
                            <div class="payment-icon gcash-icon">
                                G
                            </div>
                            <h3 class="font-semibold text-lg text-gray-800 mb-2">GCash</h3>
                            <p class="text-gray-600 text-sm">Pay using GCash wallet</p>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                id="submitButton"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 px-6 rounded-lg transition-colors duration-200 mt-6 hidden">
                            ✅ Continue to Payment
                        </button>
                    </form>
                </div>

                <!-- Right Column - Payment Summary -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Payment Summary</h2>
                    
                    <div class="space-y-4">
                        <!-- Payment Type -->
                        <div class="fee-item">
                            <span class="text-gray-600">Payment Type</span>
                            <span class="font-semibold"><?= htmlspecialchars($payment_description) ?></span>
                        </div>

                        <!-- Payment Details -->
                        <?php if ($payment_data['payment_for'] === 'all_quarters' && !empty($payment_data['payment_details'])): ?>
                            <div class="mb-4">
                                <h4 class="font-semibold text-gray-700 mb-2">Included Quarters:</h4>
                                <?php foreach ($payment_data['payment_details'] as $payment): ?>
                                    <div class="payment-details-item">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-600"><?= $quarter_labels[$payment['quarter_no']] ?? 'Quarter ' . $payment['quarter_no'] ?></span>
                                                <span class="status-badge <?= $payment['status'] === 'overdue' ? 'status-overdue' : 'status-unpaid' ?>">
                                                    <?= $payment['status'] === 'overdue' ? 'Overdue' : 'Unpaid' ?>
                                                </span>
                                            </div>
                                            <span class="font-semibold">₱<?= number_format($payment['tax_amount'] + ($payment['penalty'] ?? 0), 2) ?></span>
                                        </div>
                                        <?php if ($payment['penalty'] > 0): ?>
                                            <div class="text-sm text-red-600 mt-1">
                                                Includes penalty: ₱<?= number_format($payment['penalty'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($payment_data['payment_for'] === 'single_quarter' && !empty($payment_data['payment_details'])): ?>
                            <div class="payment-details-item">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600">Quarterly Tax</span>
                                    <span class="font-semibold">₱<?= number_format($payment_data['payment_details'][0]['tax_amount'], 2) ?></span>
                                </div>
                                <?php if ($payment_data['payment_details'][0]['penalty'] > 0): ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Penalty</span>
                                        <span class="font-semibold text-red-600">₱<?= number_format($payment_data['payment_details'][0]['penalty'], 2) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="fee-item fee-total">
                            <span class="text-gray-800">Total Amount</span>
                            <span class="text-green-600">₱<?= number_format($payment_data['amount'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-2">How to Pay</h3>
                        <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                            <li>Select your preferred payment method</li>
                            <li>Click "Continue to Payment"</li>
                            <li>Complete the payment process on the next page</li>
                            <li>Your tax payment will be recorded upon successful payment</li>
                        </ol>
                    </div>

                    <!-- Important Notes -->
                    <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <h3 class="font-semibold text-yellow-800 mb-2">Important Notes</h3>
                        <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                            <li>Payment must be completed within 24 hours</li>
                            <li>Late payments may incur additional penalties</li>
                            <li>Keep your payment confirmation for reference</li>
                            <li>Contact the Municipal Treasurer's Office for any issues</li>
                            <li>Only expired payment attempts are automatically cleaned up</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="text-center mt-8">
                <a href="rpt_tax_payment.php?application_id=<?= $payment_data['application_id'] ?>" 
                   class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200 font-semibold">
                    ← Back to Tax Payments
                </a>
            </div>
        </div>
    </div>

    <script>
        let selectedMethod = '';
        
        function selectPaymentMethod(method) {
            selectedMethod = method;
            
            // Remove selected class from all methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selected class to chosen method
            document.querySelector(`[value="${method}"]`).closest('.payment-method').classList.add('selected');
            
            // Check the radio button
            document.getElementById(method).checked = true;
            
            // Show submit button
            document.getElementById('submitButton').classList.remove('hidden');
        }

        // Form submission handling
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            if (!selectedMethod) {
                e.preventDefault();
                alert('Please select a payment method.');
                return;
            }
        });
    </script>

</body>
</html>