<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if payment data exists in session
if (!isset($_SESSION['payment_data'])) {
    die("No payment data found. Please complete the payment form.");
}

$payment_data = $_SESSION['payment_data'];

// Database connection
require_once '../../db/Market/market_db.php';

// Handle phone number and email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone_number'])) {
    $phone_number = $_POST['phone_number'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (empty($phone_number)) {
        $error_message = "Please enter your phone number.";
    } elseif (empty($email)) {
        $error_message = "Please enter your email address.";
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone_number)) {
        $error_message = "Please enter a valid Philippine phone number (09XXXXXXXXX).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Generate verification code
            $verification_code = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10 minutes from now
            
            // UPDATE the existing application_fee record instead of creating new one
            $stmt = $pdo->prepare("
                UPDATE application_fee 
                SET payment_method = ?, phone_number = ?, email = ?, verification_code = ?, 
                    expires_at = ?, status = 'pending', updated_at = NOW()
                WHERE application_id = ? AND status = 'pending'
            ");
            $stmt->execute([
                $payment_data['payment_method'],
                $phone_number,
                $email,
                $verification_code,
                $expires_at,
                $payment_data['application_id']
            ]);
            
            // Store in session for verification
            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['phone_number'] = $phone_number;
            $_SESSION['email'] = $email;
            $_SESSION['expires_at'] = $expires_at;
            
            $success_message = "Verification code sent! Check your phone for the code.";
            
        } catch (PDOException $e) {
            error_log("Payment error: " . $e->getMessage());
            $error_message = "Failed to process payment. Please try again.";
        }
    }
}

// Handle verification and final payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
    $entered_code = $_POST['verification_code'] ?? '';
    $stored_code = $_SESSION['verification_code'] ?? null;
    $expires_at = $_SESSION['expires_at'] ?? null;
    
    if (empty($entered_code)) {
        $verification_error = "Please enter the verification code.";
    } elseif (!$stored_code) {
        $verification_error = "Session expired. Please start over.";
    } elseif (strtotime($expires_at) < time()) {
        $verification_error = "Verification code has expired. Please request a new one.";
    } elseif ($entered_code === $stored_code) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Generate reference number
            $reference_number = 'REF-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // UPDATE payment record to paid
            $update_stmt = $pdo->prepare("
                UPDATE application_fee 
                SET status = 'paid', reference_number = ?, payment_date = NOW(), verified_at = NOW()
                WHERE application_id = ? AND status = 'pending'
            ");
            $update_stmt->execute([$reference_number, $payment_data['application_id']]);
            
            // UPDATE application status to 'paid'
            $app_stmt = $pdo->prepare("UPDATE applications SET status = 'paid', updated_at = NOW() WHERE id = ?");
            $app_stmt->execute([$payment_data['application_id']]);
            
            // Get application details with stall class_id
            $app_stmt = $pdo->prepare("
                SELECT a.*, s.price as stall_price, s.class_id, sr.price as stall_rights_price 
                FROM applications a 
                LEFT JOIN stalls s ON a.stall_id = s.id 
                LEFT JOIN stall_rights sr ON s.class_id = sr.class_id 
                WHERE a.id = ?
            ");
            $app_stmt->execute([$payment_data['application_id']]);
            $application = $app_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($application) {
                // Generate renter ID (R + year + sequence)
                $renter_id = 'R' . date('y') . str_pad($application['id'], 4, '0', STR_PAD_LEFT);
                
                // UPDATE stall status to occupied
                $stall_stmt = $pdo->prepare("UPDATE stalls SET status = 'occupied', updated_at = NOW() WHERE id = ?");
                $stall_stmt->execute([$application['stall_id']]);
                
                // Check if renter already exists
                $check_renter = $pdo->prepare("SELECT id FROM renters WHERE application_id = ?");
                $check_renter->execute([$application['id']]);
                
                if (!$check_renter->fetch()) {
                    // INSERT renter record
                    $renter_stmt = $pdo->prepare("
                        INSERT INTO renters 
                        (renter_id, application_id, user_id, stall_id, first_name, middle_name, last_name, 
                         contact_number, email, business_name, market_name, stall_number, section_name, 
                         class_name, monthly_rent, stall_rights_fee, security_bond, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $renter_stmt->execute([
                        $renter_id,
                        $application['id'],
                        $application['user_id'],
                        $application['stall_id'],
                        $application['first_name'],
                        $application['middle_name'],
                        $application['last_name'],
                        $application['contact_number'],
                        $application['email'],
                        $application['business_name'],
                        $application['market_name'],
                        $application['stall_number'],
                        $application['market_section'],
                        $payment_data['class_name'],
                        $application['stall_price'],
                        $payment_data['stall_rights_fee'],
                        $payment_data['security_bond']
                    ]);
                    
                    // INSERT lease contract
                    $contract_number = 'CNTR' . date('Y') . str_pad($application['id'], 4, '0', STR_PAD_LEFT);
                    $lease_stmt = $pdo->prepare("
                        INSERT INTO lease_contracts 
                        (application_id, renter_id, contract_number, start_date, end_date, monthly_rent, status)
                        VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, 'active')
                    ");
                    $lease_stmt->execute([
                        $application['id'],
                        $renter_id,
                        $contract_number,
                        $application['stall_price']
                    ]);
                    
                    // INSERT stall rights issued
                    $certificate_number = 'SRC' . date('Y') . str_pad($application['id'], 4, '0', STR_PAD_LEFT);
                    $stall_rights_stmt = $pdo->prepare("
                        INSERT INTO stall_rights_issued 
                        (application_id, renter_id, certificate_number, class_id, issue_date, expiry_date, status)
                        VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active')
                    ");
                    $stall_rights_stmt->execute([
                        $application['id'],
                        $renter_id,
                        $certificate_number,
                        $application['class_id']
                    ]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Clear session data
            unset($_SESSION['payment_data'], $_SESSION['verification_code'], $_SESSION['phone_number'], $_SESSION['email'], $_SESSION['expires_at']);

            // Success - redirect to success page
            $_SESSION['success'] = "Payment completed successfully!";
            header('Location: payment_success.php?application_id=' . $payment_data['application_id']);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Payment verification error: " . $e->getMessage());
            $verification_error = "Payment failed. Please try again.";
        }
    } else {
        $verification_error = "Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= strtoupper($payment_data['payment_method']) ?> Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .payment-header {
            background: linear-gradient(135deg, 
                <?= $payment_data['payment_method'] === 'gcash' ? '#00a64f, #007a3d' : '#00a3ff, #0055ff' ?>);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .amount-display {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            margin: 2rem 0;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 400px;
        }
        .countdown {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            color: #ef4444;
            margin: 1rem 0;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Payment Header -->
    <div class="payment-header">
        <div class="max-w-md mx-auto">
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">
                    <?= $payment_data['payment_method'] === 'gcash' ? 'G' : 'M' ?>
                </span>
            </div>
            <h1 class="text-3xl font-bold mb-2"><?= strtoupper($payment_data['payment_method']) ?></h1>
            <p class="text-white/90">Secure Payment Gateway</p>
        </div>
    </div>

    <!-- Verification Modal -->
    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-2xl">📱</span>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Enter Verification Code</h3>
                <p class="text-gray-600">Enter the code sent to your phone</p>
                
                <!-- Countdown Timer -->
                <div id="countdown" class="countdown">10:00</div>
                
                <?php if (isset($_SESSION['verification_code'])): ?>
                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-green-700 font-semibold">Demo Code: <?= $_SESSION['verification_code'] ?></p>
                        <p class="text-green-600 text-sm">Enter this code to verify</p>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" id="verificationForm">
                <div class="mb-6">
                    <label for="verification_input" class="block text-sm font-medium text-gray-700 mb-2">
                        Verification Code
                    </label>
                    <input type="text" 
                           name="verification_code" 
                           id="verification_input"
                           placeholder="Enter 6-digit code"
                           required
                           maxlength="6"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center text-xl tracking-widest">
                </div>

                <?php if (isset($verification_error)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                        <p class="text-red-700 text-sm"><?= htmlspecialchars($verification_error) ?></p>
                    </div>
                <?php endif; ?>

                <div class="flex gap-3">
                    <button type="button" 
                            onclick="closeModal()"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="verifyButton"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200">
                        ✅ Verify & Pay
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="max-w-md mx-auto px-6 py-8">
        <!-- Amount Display -->
        <div class="text-center mb-8">
            <p class="text-gray-600 text-lg mb-2">Total Amount to Pay</p>
            <div class="amount-display text-green-600">
                ₱<?= number_format($payment_data['total_amount'], 2) ?>
            </div>
            <p class="text-gray-500 text-sm">Market Stall Application Fee</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-700"><?= htmlspecialchars($success_message) ?></p>
            </div>
        <?php endif; ?>

        <!-- Payment Form -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6 text-center">Enter Your Details</h2>
            
            <form method="POST" id="paymentForm">
                <!-- Phone Number -->
                <div class="mb-4">
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number *
                    </label>
                    <input type="tel" 
                           name="phone_number" 
                           id="phone_number"
                           placeholder="09123456789"
                           pattern="09[0-9]{9}"
                           required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-2">
                        Your <?= strtoupper($payment_data['payment_method']) ?> registered number
                    </p>
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address *
                    </label>
                    <input type="email" 
                           name="email" 
                           id="email"
                           placeholder="your@email.com"
                           required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-2">
                        For payment confirmation and receipt
                    </p>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 px-6 rounded-lg transition-colors duration-200">
                    📱 Send Verification Code
                </button>
            </form>
        </div>

        <!-- Payment Details -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-800 mb-2">Payment Details</h3>
            <div class="text-sm text-blue-700 space-y-1">
                <p><strong>Merchant:</strong> Market Portal</p>
                <p><strong>Reference ID:</strong> APP-<?= $payment_data['application_id'] ?></p>
                <p><strong>Payment Method:</strong> <?= strtoupper($payment_data['payment_method']) ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-format phone number
        document.getElementById('phone_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });

        // Auto-format verification code
        document.getElementById('verification_input')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // Modal functions
        function openModal() {
            document.getElementById('verificationModal').style.display = 'block';
            startCountdown();
            setTimeout(() => {
                document.getElementById('verification_input').focus();
            }, 100);
        }

        function closeModal() {
            document.getElementById('verificationModal').style.display = 'none';
            clearInterval(countdownInterval);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('verificationModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Countdown timer
        let countdownInterval;
        function startCountdown() {
            let timeLeft = 10 * 60; // 10 minutes in seconds
            const countdownElement = document.getElementById('countdown');
            const verifyButton = document.getElementById('verifyButton');
            
            countdownInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    countdownElement.textContent = "Expired!";
                    countdownElement.style.color = "#ef4444";
                    verifyButton.disabled = true;
                    verifyButton.innerHTML = "⏰ Code Expired";
                    verifyButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                    verifyButton.classList.add('bg-gray-400');
                    
                    // Show expired message
                    const form = document.getElementById('verificationForm');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'bg-red-50 border border-red-200 rounded-lg p-3 mb-4';
                    errorDiv.innerHTML = '<p class="text-red-700 text-sm">Verification code has expired. Please request a new one.</p>';
                    form.insertBefore(errorDiv, form.firstChild);
                }
                
                timeLeft--;
            }, 1000);
        }

        // Auto-open modal if verification code is generated
        <?php if (isset($_SESSION['verification_code'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                openModal();
            });
        <?php endif; ?>
    </script>

</body>
</html>