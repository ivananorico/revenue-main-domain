<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Preserve GET or POST parameters
$application_id = $_GET['application_id'] ?? $_POST['application_id'] ?? null;
$month_year     = $_GET['month_year'] ?? $_POST['month_year'] ?? null;
$amount         = $_GET['amount'] ?? $_POST['amount'] ?? 0;
$payment_for    = $_GET['payment_for'] ?? $_POST['payment_for'] ?? 'single_month';
$payment_type   = $_GET['payment_type'] ?? $_POST['payment_type'] ?? 'monthly_rent';

if (!$application_id) {
    $_SESSION['error'] = "No application specified.";
    header('Location: ../market-dashboard.php');
    exit;
}

// Database connection
require_once '../../db/Market/market_db.php';

$application = null;
$payment_details = [];

try {
    // Get application and renter details
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            s.name as stall_name, 
            s.price as monthly_rent,
            m.name as market_name,
            sec.name as section_name,
            r.renter_id,
            r.business_name,
            r.first_name,
            r.last_name,
            r.contact_number,
            r.email
        FROM applications a 
        LEFT JOIN stalls s ON a.stall_id = s.id 
        LEFT JOIN maps m ON s.map_id = m.id 
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN renters r ON a.id = r.application_id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['error'] = "Application not found or access denied.";
        header('Location: ../market-dashboard.php');
        exit;
    }

    // Get unpaid payments for display
    if ($payment_for === 'all_months') {
        $payments_stmt = $pdo->prepare("
            SELECT * FROM monthly_payments 
            WHERE renter_id = ? AND status = 'pending'
            ORDER BY month_year ASC
        ");
        $payments_stmt->execute([$application['renter_id']]);
        $payment_details = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // For single month payment
        $payment_stmt = $pdo->prepare("
            SELECT * FROM monthly_payments 
            WHERE renter_id = ? AND month_year = ? AND status = 'pending'
        ");
        $payment_stmt->execute([$application['renter_id'], $month_year]);
        $payment_details = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: ../market-dashboard.php');
    exit;
}

// Handle form submission - Automated cleanup before new payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    
    // Validate payment method
    if (!in_array($payment_method, ['maya', 'gcash'])) {
        $error_message = "Please select a valid payment method.";
    } else {
        try {
            // =============================================
            // AUTOMATED CLEANUP FUNCTIONALITY
            // =============================================
            
            // 1. Clear any existing pending payment verification data for this renter
            $clear_stmt = $pdo->prepare("
                UPDATE monthly_payments 
                SET verification_code = NULL, 
                    expires_at = NULL, 
                    verification_attempts = 0,
                    payment_method = NULL,
                    phone_number = NULL,
                    email = NULL,
                    verified_at = NULL
                WHERE renter_id = ? 
                AND status = 'pending'
                AND (expires_at < NOW() OR expires_at IS NOT NULL)
            ");
            $clear_stmt->execute([$application['renter_id']]);
            
            // 2. Clear ALL payment-related session data
            $payment_session_keys = [
                'verification_code', 'phone_number', 'email', 'expires_at',
                'payment_data', 'verification_attempts', 'rent_payment_data'
            ];
            foreach ($payment_session_keys as $key) {
                unset($_SESSION[$key]);
            }
            
            // =============================================
            // STORE PAYMENT DATA AND REDIRECT
            // =============================================
            
            // Store payment data in session for the next page
            $_SESSION['rent_payment_data'] = [
                'application_id' => $application_id,
                'renter_id' => $application['renter_id'],
                'payment_method' => $payment_method,
                'amount' => $amount,
                'payment_for' => $payment_for,
                'payment_type' => $payment_type,
                'month_year' => $month_year,
                'business_name' => $application['business_name'],
                'market_name' => $application['market_name'],
                'stall_name' => $application['stall_name'],
                'renter_name' => $application['first_name'] . ' ' . $application['last_name'],
                'contact_number' => $application['contact_number'],
                'email' => $application['email'],
                'payment_details' => $payment_details
            ];
            
            // Redirect to payment processing page
            header('Location: market_rent_payment_process.php');
            exit;
            
        } catch (PDOException $e) {
            error_log("Payment initialization error: " . $e->getMessage());
            $error_message = "Failed to initialize payment. Please try again. Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Payment Details - Market Portal</title>
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
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Complete Your Rent Payment</h1>
            <p class="text-xl text-gray-600">Secure payment for your market stall monthly rent</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <!-- Cleanup Notice -->
            <div class="cleanup-notice">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-blue-700 font-medium">Security Feature: All incomplete payment attempts are automatically cleaned up for your protection.</span>
                </div>
            </div>

            <!-- Application Summary -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Renter Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Renter ID</p>
                        <p class="font-semibold text-lg"><?= htmlspecialchars($application['renter_id']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Business Name</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['business_name']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Stall Location</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['market_name']) ?> - <?= htmlspecialchars($application['stall_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Section</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['section_name']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Renter Name</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?></p>

                        <p class="text-sm text-gray-600 mt-4">Contact</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['contact_number']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column - Payment Methods -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Choose Payment Method</h2>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- UPDATED: Direct form submission to self for cleanup processing -->
                    <form id="paymentForm" method="POST">
                        <!-- Hidden fields to pass all necessary data -->
                        <input type="hidden" name="application_id" value="<?= $application_id ?>">
                        <input type="hidden" name="month_year" value="<?= htmlspecialchars($month_year) ?>">
                        <input type="hidden" name="amount" value="<?= $amount ?>">
                        <input type="hidden" name="payment_for" value="<?= $payment_for ?>">
                        <input type="hidden" name="payment_type" value="<?= $payment_type ?>">
                        
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
                            <span class="font-semibold">
                                <?= $payment_for === 'all_months' ? 'All Unpaid Months' : 'Single Month (' . date('F Y', strtotime($month_year . '-01')) . ')' ?>
                            </span>
                        </div>

                        <!-- Payment Details -->
                        <?php if ($payment_for === 'all_months' && !empty($payment_details)): ?>
                            <div class="mb-4">
                                <h4 class="font-semibold text-gray-700 mb-2">Included Months:</h4>
                                <?php foreach ($payment_details as $payment): ?>
                                    <div class="payment-details-item">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600"><?= date('F Y', strtotime($payment['month_year'] . '-01')) ?></span>
                                            <span class="font-semibold">₱<?= number_format($payment['amount'] + ($payment['late_fee'] ?? 0), 2) ?></span>
                                        </div>
                                        <?php if ($payment['late_fee'] > 0): ?>
                                            <div class="text-sm text-red-600 mt-1">
                                                Includes late fee: ₱<?= number_format($payment['late_fee'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($payment_for === 'single_month' && !empty($payment_details)): ?>
                            <div class="payment-details-item">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600">Monthly Rent</span>
                                    <span class="font-semibold">₱<?= number_format($payment_details[0]['amount'], 2) ?></span>
                                </div>
                                <?php if ($payment_details[0]['late_fee'] > 0): ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Late Fee</span>
                                        <span class="font-semibold text-red-600">₱<?= number_format($payment_details[0]['late_fee'], 2) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="fee-item fee-total">
                            <span class="text-gray-800">Total Amount</span>
                            <span class="text-green-600">₱<?= number_format($amount, 2) ?></span>
                        </div>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-2">How to Pay</h3>
                        <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                            <li>Select your preferred payment method</li>
                            <li>Click "Continue to Payment"</li>
                            <li>Complete the payment process on the next page</li>
                            <li>Your rent payment will be recorded upon successful payment</li>
                        </ol>
                    </div>

                    <!-- Important Notes -->
                    <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <h3 class="font-semibold text-yellow-800 mb-2">Important Notes</h3>
                        <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                            <li>Payment must be completed within 24 hours</li>
                            <li>Late payments may incur additional fees</li>
                            <li>Contact support if you encounter any issues</li>
                            <li>Incomplete payments are automatically cleaned up after expiration</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="text-center mt-8">
                <a href="../market_card/payment_rent.php?application_id=<?= $application_id ?>" 
                   class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200 font-semibold">
                    ← Back to Rent Payments
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