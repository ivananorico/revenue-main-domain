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

$full_name = $_SESSION['full_name'] ?? 'Guest';
$user_id = $_SESSION['user_id'];

// Get application_id from URL parameter
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : null;

if (!$application_id) {
    $_SESSION['error'] = "No application specified.";
    header('Location: market-dashboard.php');
    exit;
}

// Database connection
require_once '../../db/Market/market_db.php';

$application = null;
$stall_rights_fee = 0;

try {
    // Get application details with fees
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            s.name as stall_name, 
            s.price as stall_price,
            m.name as market_name,
            sr.class_name,
            sr.price as stall_rights_price
        FROM applications a 
        LEFT JOIN stalls s ON a.stall_id = s.id 
        LEFT JOIN maps m ON s.map_id = m.id 
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['error'] = "Application not found or access denied.";
        header('Location: market-dashboard.php');
        exit;
    }

    // Get stall rights fee
    $stall_rights_fee = $application['stall_rights_price'] ?? 0;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: market-dashboard.php');
    exit;
}

// Calculate fees
$application_fee = 100.00;
$security_bond = 10000.00;
$total_amount = ($application['stall_price'] ?? 0) + $stall_rights_fee + $application_fee + $security_bond;

// Handle form submission - Redirect to confirmation page with data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    
    // Validate payment method
    if (!in_array($payment_method, ['maya', 'gcash'])) {
        $error_message = "Please select a valid payment method.";
    } else {
        try {
            // Clear any existing verification codes from database before proceeding
            $clear_stmt = $pdo->prepare("
                UPDATE application_fee 
                SET verification_code = NULL, expires_at = NULL, verification_attempts = 0
                WHERE application_id = ? AND status = 'pending'
            ");
            $clear_stmt->execute([$application_id]);
            
            // Clear any verification session data
            unset($_SESSION['verification_code'], $_SESSION['phone_number'], $_SESSION['email'], $_SESSION['expires_at']);
            
            // Store payment data in session and redirect to confirmation page
            $_SESSION['payment_data'] = [
                'application_id' => $application_id,
                'payment_method' => $payment_method,
                'application_fee' => $application_fee,
                'security_bond' => $security_bond,
                'stall_rights_fee' => $stall_rights_fee,
                'total_amount' => $total_amount,
                'stall_price' => $application['stall_price'] ?? 0,
                'business_name' => $application['business_name'],
                'market_name' => $application['market_name'],
                'stall_number' => $application['stall_number'],
                'class_name' => $application['class_name']
            ];
            
            // Redirect to confirmation page
            header('Location: payment_fee.php');
            exit;
            
        } catch (PDOException $e) {
            error_log("Clear verification error: " . $e->getMessage());
            $error_message = "Failed to process payment. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - Market Portal</title>
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
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Complete Your Payment</h1>
            <p class="text-xl text-gray-600">Secure payment for your market stall application</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <!-- Application Summary -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Application Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Application ID</p>
                        <p class="font-semibold text-lg">#<?= $application['id'] ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Business Name</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['business_name']) ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Stall Location</p>
                        <p class="font-semibold"><?= htmlspecialchars($application['market_name']) ?> - <?= htmlspecialchars($application['stall_number']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Stall Class</p>
                        <p class="font-semibold">Class <?= $application['class_name'] ?></p>
                        
                        <p class="text-sm text-gray-600 mt-4">Applicant Name</p>
                        <p class="font-semibold"><?= htmlspecialchars($full_name) ?></p>
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

                    <!-- REMOVED target="_blank" from the form -->
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
                        <div class="fee-item">
                            <span class="text-gray-600">Monthly Stall Rent</span>
                            <span class="font-semibold">₱<?= number_format($application['stall_price'], 2) ?></span>
                        </div>
                        
                        <div class="fee-item">
                            <span class="text-gray-600">Stall Rights Fee (Class <?= $application['class_name'] ?>)</span>
                            <span class="font-semibold">₱<?= number_format($stall_rights_fee, 2) ?></span>
                        </div>
                        
                        <div class="fee-item">
                            <span class="text-gray-600">Application Fee</span>
                            <span class="font-semibold">₱<?= number_format($application_fee, 2) ?></span>
                        </div>
                        
                        <div class="fee-item">
                            <span class="text-gray-600">Security Bond</span>
                            <span class="font-semibold">₱<?= number_format($security_bond, 2) ?></span>
                        </div>
                        
                        <div class="fee-item fee-total">
                            <span class="text-gray-800">Total Amount</span>
                            <span class="text-green-600">₱<?= number_format($total_amount, 2) ?></span>
                        </div>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-2">How to Pay</h3>
                        <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                            <li>Select your preferred payment method</li>
                            <li>Click "Continue to Payment"</li>
                            <li>Complete the payment process on the next page</li>
                            <li>Your stall will be reserved upon successful payment</li>
                        </ol>
                    </div>

                    <!-- Important Notes -->
                    <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <h3 class="font-semibold text-yellow-800 mb-2">Important Notes</h3>
                        <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                            <li>Payment must be completed within 24 hours</li>
                            <li>Your stall will be reserved upon successful payment</li>
                            <li>Contact support if you encounter any issues</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="text-center mt-8">
                <a href="../market_card/view_documents/view_documents.php?application_id=<?= $application_id ?>" 
                   class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200 font-semibold">
                    ← Back to Application Details
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