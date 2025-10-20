<?php
session_start();
require_once '../db/user_db.php';

$login_message = '';
$register_message = '';

// Hardcoded admin credentials
$admin_email = 'admin@gmail.com';
$admin_password = 'admin';

// Function to verify reCAPTCHA
function verifyRecaptcha($recaptcha_response) {
    $secret = "6Lcyyu0rAAAAABPZoWsEg4vPWb0aYLfhmPgYYYgY";
    if (empty($recaptcha_response)) return false;

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($result);
    return $json && $json->success;
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_form'])) {
    // Get form data
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : '';
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : '';
    $birthdate = $_POST['birthdate'];
    $email = trim($_POST['register_email']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address']);
    $houseNumber = trim($_POST['houseNumber']);
    $street = trim($_POST['street']);
    $barangay = trim($_POST['barangay']);
    $password = $_POST['register_password'];
    $confirmPassword = $_POST['confirmPassword'];
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Verify reCAPTCHA first
    if (!verifyRecaptcha($recaptcha_response)) {
        $register_message = "reCAPTCHA verification failed. Please try again.";
    } else {
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($birthdate) || empty($email) || 
            empty($mobile) || empty($address) || empty($barangay) || empty($password)) {
            $register_message = "All required fields must be filled out.";
        } 
        // Check if passwords match
        elseif ($password !== $confirmPassword) {
            $register_message = "Passwords do not match!";
        }
        // Check password strength
        elseif (strlen($password) < 10) {
            $register_message = "Password must be at least 10 characters long.";
        }
        // Check if email already exists
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $register_message = "Email already exists! Please use a different email.";
            } else {
                // Construct full name
                $fullName = $firstName . ' ' . $lastName;
                if (!empty($middleName)) {
                    $fullName = $firstName . ' ' . $middleName . ' ' . $lastName;
                }
                if (!empty($suffix)) {
                    $fullName .= ' ' . $suffix;
                }

                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into database
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$fullName, $email, $password_hash]);
                    
                    $register_message = "successful: Registration successful! You can now login.";
                    // Clear form data after successful registration
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { showLoginForm(); });</script>";
                } catch (PDOException $e) {
                    $register_message = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_email'])) {
    $email = trim($_POST['login_email']);
    $password = $_POST['login_password'];
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Verify reCAPTCHA first
    if (!verifyRecaptcha($recaptcha_response)) {
        $login_message = "reCAPTCHA verification failed. Please try again.";
    } else {
        // Hardcoded admin login
        if ($email === $admin_email && $password === $admin_password) {
            header('Location: /revenue/dist'); // admin page
            exit;
        }

        // Regular user login
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = false;
            header('Location: dashboard.php');
            exit;
        } else {
            $login_message = "Invalid email or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Services Management System - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="bg-custom-bg min-h-screen flex flex-col">
    <!-- Header Section -->
    <header class="py-2">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <img src="images/GSM_logo.png" alt="GSM Logo" class="h-10 w-auto">
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-bold" style="font-weight: 700;">
                        <span class="brand-go">Go</span><span class="brand-serve">Serve</span><span class="brand-ph">PH</span>
                    </h1>
                </div>
                <div class="text-right">
                    <div class="text-sm">
                        <div id="currentDateTime" class="font-semibold"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 pt-4 pb-12 flex-1">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <!-- Left Section - Features -->
            <div class="text-center lg:text-left mt-2">
                <h2 class="text-4xl lg:text-5xl font-bold mb-4 animated-gradient ml-2 lg:ml-4">
                    Abot-Kamay mo ang Serbisyong Publiko!
                </h2>
            </div>

            <!-- Right Section - Login Form -->
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm mx-auto w-full glass-card glow-on-hover mt-8">
                <form id="loginForm" class="space-y-5 form-compact" method="POST" action="">
                    <input type="hidden" name="login_form" value="1">
                    
                    <?php if ($login_message): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <?= $login_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <input 
                            type="email" 
                            id="email" 
                            name="login_email" 
                            placeholder="Enter e-mail address"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
                            required
                        >
                    </div>
                    
                    <div>
                        <input 
                            type="password" 
                            id="password" 
                            name="login_password" 
                            placeholder="Enter password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
                            required
                        >
                    </div>

                    <!-- Add reCAPTCHA to login form -->
                    <div>
                        <div class="g-recaptcha" data-sitekey="6Lcyyu0rAAAAAE_0v046KXuaNllw1Z_Wk_HsrHqG"></div>
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-custom-secondary text-white py-3 px-6 rounded-lg font-semibold btn-primary"
                        data-no-loading
                    >
                        Login
                    </button>
                    
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">OR</span>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button 
                            type="button" 
                            class="w-full bg-white border border-gray-300 text-gray-700 py-3 px-6 rounded-lg font-semibold social-btn flex items-center justify-center space-x-2"
                        >
                            <i class="fab fa-google text-red-500"></i>
                            <span>Continue with Google</span>
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-gray-600">
                            No account yet? 
                            <button type="button" id="showRegister" class="text-custom-secondary hover:underline font-semibold">Register here</button>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-custom-primary text-white py-4 mt-8">
        <div class="container mx-auto px-6">
            <div class="flex flex-col lg:flex-row justify-between items-center">
                <div class="text-center lg:text-left mb-2 lg:mb-0">
                    <h3 class="text-lg font-bold mb-1">Government Services Management System</h3>
                    <p class="text-xs opacity-90">
                        For any inquiries, please call 122 or email helpdesk@gov.ph
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex space-x-3">
                        <button type="button" id="footerTerms" class="text-xs hover:underline">TERMS OF SERVICE</button>
                        <span>|</span>
                        <button type="button" id="footerPrivacy" class="text-xs hover:underline">PRIVACY POLICY</button>
                    </div>
                    <div class="flex space-x-2">
                        <a href="#" class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center hover:bg-blue-700 transition-colors">
                            <i class="fab fa-facebook-f text-white text-xs"></i>
                        </a>
                        <a href="#" class="w-6 h-6 bg-blue-400 rounded-full flex items-center justify-center hover:bg-blue-500 transition-colors">
                            <i class="fab fa-twitter text-white text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Registration Form (hidden by default) -->
    <div id="registerFormContainer" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-20 px-4 hidden overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-2xl w-full glass-card form-compact max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-secondary">Create your GoServePH account</h2>
            </div>
            <form id="registerForm" class="space-y-5 pt-4" method="POST" action="">
                <input type="hidden" name="register_form" value="1">
                
                <?php if ($register_message): ?>
                    <div class="bg-<?= strpos($register_message, 'successful') !== false ? 'green' : 'red' ?>-100 border border-<?= strpos($register_message, 'successful') !== false ? 'green' : 'red' ?>-400 text-<?= strpos($register_message, 'successful') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded">
                        <?= str_replace('successful:', '', $register_message) ?>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="firstName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="lastName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Middle Name<span id="middleAsterisk" class="required-asterisk">*</span></label>
                        <input type="text" id="middleName" name="middleName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <label class="inline-flex items-center mt-2 text-sm">
                            <input type="checkbox" id="noMiddleName" class="mr-2"> No middle name
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Suffix</label>
                        <input type="text" name="suffix" placeholder="Jr., Sr., III (optional)" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Birthdate<span class="required-asterisk">*</span></label>
                        <input type="date" name="birthdate" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                        <input type="email" name="register_email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Mobile Number<span class="required-asterisk">*</span></label>
                        <input type="tel" name="mobile" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Address<span class="required-asterisk">*</span></label>
                        <input type="text" name="address" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Lot/Unit, Building, Subdivision">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">House #<span class="required-asterisk">*</span></label>
                        <input type="text" name="houseNumber" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Street<span class="required-asterisk">*</span></label>
                        <input type="text" name="street" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Barangay<span class="required-asterisk">*</span></label>
                        <input type="text" name="barangay" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                        <div class="relative">
                            <input type="password" id="regPassword" name="register_password" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10" aria-describedby="pwdChecklist">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle password visibility" data-target="regPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <ul id="pwdChecklist" class="text-xs text-gray-600 mt-2 space-y-1">
                            <li class="req-item" data-check="length"><span class="req-dot"></span> At least 10 characters</li>
                            <li class="req-item" data-check="upper"><span class="req-dot"></span> Has uppercase letter</li>
                            <li class="req-item" data-check="lower"><span class="req-dot"></span> Has lowercase letter</li>
                            <li class="req-item" data-check="number"><span class="req-dot"></span> Has a number</li>
                            <li class="req-item" data-check="special"><span class="req-dot"></span> Has a special character</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Confirm Password<span class="required-asterisk">*</span></label>
                        <div class="relative">
                            <input type="password" id="confirmPassword" name="confirmPassword" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle confirm password visibility" data-target="confirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="g-recaptcha" data-sitekey="6Lcyyu0rAAAAAE_0v046KXuaNllw1Z_Wk_HsrHqG"></div>
                </div>

                <div class="space-y-2 text-center">
                    <div class="flex items-center text-sm justify-center">
                        <label class="inline-flex items-center">
                            <input type="checkbox" id="agreeTerms" class="mr-2" required>
                            <span>I have read, understood, and agreed to the</span>
                        </label>
                        <button type="button" id="openTerms" class="ml-2 text-custom-secondary hover:underline">Terms of Use</button>
                    </div>
                    <div class="flex items-center text-sm justify-center">
                        <label class="inline-flex items-center">
                            <input type="checkbox" id="agreePrivacy" class="mr-2" required>
                            <span>I have read, understood, and agreed to the</span>
                        </label>
                        <button type="button" id="openPrivacy" class="ml-2 text-custom-secondary hover:underline">Data Privacy Policy</button>
                    </div>
                    <p class="text-xs text-gray-600 text-center">By clicking on the register button below, I hereby agree to both the Terms of Use and Data Privacy Policy</p>
                </div>

                <div class="flex justify-center space-x-3 pt-2">
                    <button type="button" id="cancelRegister" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="bg-custom-secondary text-white px-4 py-2 rounded-lg">Register</button>
                </div>
            </form>
        </div>
    </div>

    <!-- OTP Modal -->
    <div id="otpModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-2 text-center">Two-Factor Verification</h3>
            <p class="text-sm text-gray-600 mb-4 text-center">Please check your registered email for your OTP. You have <span id="otpTimer" class="font-semibold text-custom-secondary">03:00</span> to enter it.</p>
            <form id="otpForm" class="space-y-4">
                <div>
                    <label class="block text-sm mb-2 text-center">Enter OTP</label>
                    <div class="flex justify-center space-x-2" id="otpInputs">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 1">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 2">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 3">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 4">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 5">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 6">
                    </div>
                </div>
                <div id="otpError" class="text-red-500 text-sm hidden text-center">Invalid or expired OTP.</div>
                <div class="flex justify-center space-x-3 pt-2">
                    <button type="button" id="cancelOtp" class="px-4 py-2 rounded-lg bg-red-500 text-white">Cancel</button>
                    <button type="button" id="resendOtp" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800" disabled>Resend OTP</button>
                    <button type="submit" id="submitOtp" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Verify</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms of Service Modal -->
    <div id="termsModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-center">
                <h3 class="text-lg font-semibold text-center">GoServePH Terms of Service Agreement</h3>
                <button type="button" id="closeTerms" class="absolute right-4 text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-4 space-y-4 text-sm leading-6 text-center">
                <p><strong>Welcome to GoServePH!</strong></p>
                <p class="text-justify">This GoServePH Services Agreement ("Agreement") is a binding legal contract for the use of our software systems—which handle data input, monitoring, processing, and analytics—("Services") between GoServePH ("us," "our," or "we") and you, the registered user ("you" or "user").</p>
                <p class="text-justify">By registering for and using our Services, you agree to be bound by the terms of this Agreement. If you are entering into this Agreement on behalf of a company or other legal entity, you represent that you have the authority to bind such entity to these terms.</p>
                
                <h4 class="font-semibold mt-4 text-left">1. Account Registration and Security</h4>
                <p class="text-justify">You must provide accurate, current, and complete information during the registration process and keep your account information updated. You are responsible for safeguarding your password and for all activities that occur under your account.</p>
                
                <h4 class="font-semibold mt-4 text-left">2. Acceptable Use</h4>
                <p class="text-justify">You agree not to misuse the Services or help anyone else to do so. You must not attempt to access, tamper with, or use non-public areas of the Services, our systems, or our technical providers' systems.</p>
                
                <h4 class="font-semibold mt-4 text-left">3. Data Privacy</h4>
                <p class="text-justify">We care about the privacy of our users. Our Data Privacy Policy explains how we collect, use, and protect your personal information. By using our Services, you agree that we can use such data in accordance with our privacy policy.</p>
                
                <h4 class="font-semibold mt-4 text-left">4. Service Modifications</h4>
                <p class="text-justify">We may change, suspend, or discontinue the Services, or any part of them, at any time without notice. We may also impose limits on certain features and services or restrict your access to parts or all of the Services without liability.</p>
                
                <h4 class="font-semibold mt-4 text-left">5. Termination</h4>
                <p class="text-justify">You can stop using our Services at any time. We may terminate or suspend your access to the Services immediately, without prior notice, for conduct that we believe violates this Agreement or is harmful to other users, us, or third parties, or for any other reason.</p>
                
                <h4 class="font-semibold mt-4 text-left">6. Disclaimer of Warranties</h4>
                <p class="text-justify">The Services are provided "as is" without any warranties, express or implied. We do not warrant that the Services will be uninterrupted, timely, secure, or error-free.</p>
                
                <h4 class="font-semibold mt-4 text-left">7. Limitation of Liability</h4>
                <p class="text-justify">To the fullest extent permitted by law, in no event will GoServePH be liable for any indirect, special, incidental, punitive, exemplary, or consequential damages arising out of or in connection with the Services.</p>
                
                <h4 class="font-semibold mt-4 text-left">8. Governing Law</h4>
                <p class="text-justify">This Agreement shall be governed by and construed in accordance with the laws of the Republic of the Philippines, without regard to its conflict of law provisions.</p>
                
                <h4 class="font-semibold mt-4 text-left">9. Changes to Terms</h4>
                <p class="text-justify">We may modify this Agreement from time to time. If we make changes, we will provide notice by posting the updated terms and updating the "Last Updated" date. Your continued use of the Services after any modification constitutes your acceptance of the modified terms.</p>
                
                <p class="text-justify mt-4"><strong>Last Updated:</strong> January 2024</p>
            </div>
            <div class="border-t px-6 py-3 flex justify-center">
                <button type="button" id="closeTermsBottom" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Close</button>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-center">
                <h3 class="text-lg font-semibold text-center">GoServePH Data Privacy Policy</h3>
                <button type="button" id="closePrivacy" class="absolute right-4 text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-4 space-y-4 text-sm leading-6 text-center">
                <p><strong>Protecting the information you and your users handle through our system is our highest priority.</strong> This policy outlines how GoServePH manages, secures, and uses your data.</p>
                
                <h4 class="font-semibold mt-4 text-left">1. Information We Collect</h4>
                <p class="text-justify">We collect information you provide directly to us, including:</p>
                <ul class="list-disc list-inside text-justify ml-4">
                    <li>Personal identification information (name, email address, phone number, etc.)</li>
                    <li>Demographic information (birthdate, address, etc.)</li>
                    <li>Account credentials</li>
                    <li>Service usage data</li>
                </ul>
                
                <h4 class="font-semibold mt-4 text-left">2. How We Use Your Information</h4>
                <p class="text-justify">We use the information we collect to:</p>
                <ul class="list-disc list-inside text-justify ml-4">
                    <li>Provide, maintain, and improve our Services</li>
                    <li>Process transactions and send related information</li>
                    <li>Send administrative messages, responses to comments and questions, and customer support</li>
                    <li>Communicate about products, services, offers, and events</li>
                    <li>Monitor and analyze trends, usage, and activities</li>
                    <li>Detect, prevent, and address technical issues and fraudulent or illegal activities</li>
                </ul>
                
                <h4 class="font-semibold mt-4 text-left">3. Information Sharing</h4>
                <p class="text-justify">We do not sell, trade, or otherwise transfer your personally identifiable information to outside parties except in the following circumstances:</p>
                <ul class="list-disc list-inside text-justify ml-4">
                    <li>With your consent</li>
                    <li>To comply with legal obligations</li>
                    <li>To protect and defend our rights and property</li>
                    <li>With service providers who assist in operating our Services (subject to confidentiality agreements)</li>
                </ul>
                
                <h4 class="font-semibold mt-4 text-left">4. Data Security</h4>
                <p class="text-justify">We implement appropriate technical and organizational security measures designed to protect your personal information. However, no method of transmission over the Internet or electronic storage is 100% secure, so we cannot guarantee absolute security.</p>
                
                <h4 class="font-semibold mt-4 text-left">5. Data Retention</h4>
                <p class="text-justify">We retain personal information for as long as necessary to fulfill the purposes outlined in this policy, unless a longer retention period is required or permitted by law.</p>
                
                <h4 class="font-semibold mt-4 text-left">6. Your Rights</h4>
                <p class="text-justify">You have the right to:</p>
                <ul class="list-disc list-inside text-justify ml-4">
                    <li>Access and receive a copy of your personal information</li>
                    <li>Rectify or update your personal information</li>
                    <li>Delete your personal information</li>
                    <li>Restrict or object to our processing of your personal information</li>
                    <li>Data portability</li>
                </ul>
                
                <h4 class="font-semibold mt-4 text-left">7. Cookies and Tracking</h4>
                <p class="text-justify">We use cookies and similar tracking technologies to track activity on our Services and hold certain information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.</p>
                
                <h4 class="font-semibold mt-4 text-left">8. Children's Privacy</h4>
                <p class="text-justify">Our Services are not intended for individuals under the age of 18. We do not knowingly collect personal information from children under 18.</p>
                
                <h4 class="font-semibold mt-4 text-left">9. Changes to This Policy</h4>
                <p class="text-justify">We may update this privacy policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last Updated" date.</p>
                
                <h4 class="font-semibold mt-4 text-left">10. Contact Us</h4>
                <p class="text-justify">If you have any questions about this Privacy Policy, please contact us at:</p>
                <p class="text-justify">Email: privacy@goserveph.gov.ph<br>
                Address: Data Privacy Office, GoServePH Headquarters, Manila, Philippines</p>
                
                <p class="text-justify mt-4"><strong>Last Updated:</strong> January 2024</p>
            </div>
            <div class="border-t px-6 py-3 flex justify-center">
                <button type="button" id="closePrivacyBottom" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Current Date and Time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-PH', options);
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Form Toggle Functionality
        document.getElementById('showRegister').addEventListener('click', function() {
            document.getElementById('registerFormContainer').classList.remove('hidden');
        });

        document.getElementById('cancelRegister').addEventListener('click', function() {
            document.getElementById('registerFormContainer').classList.add('hidden');
        });

        // Middle Name Toggle
        document.getElementById('noMiddleName').addEventListener('change', function() {
            const middleNameInput = document.getElementById('middleName');
            const middleAsterisk = document.getElementById('middleAsterisk');
            
            if (this.checked) {
                middleNameInput.required = false;
                middleNameInput.disabled = true;
                middleNameInput.value = '';
                middleAsterisk.classList.add('hidden');
            } else {
                middleNameInput.required = true;
                middleNameInput.disabled = false;
                middleAsterisk.classList.remove('hidden');
            }
        });

        // Password Visibility Toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password Validation
        document.getElementById('regPassword').addEventListener('input', function() {
            const password = this.value;
            const checklist = document.getElementById('pwdChecklist');
            
            // Check length
            toggleRequirement(checklist.querySelector('[data-check="length"]'), password.length >= 10);
            
            // Check uppercase
            toggleRequirement(checklist.querySelector('[data-check="upper"]'), /[A-Z]/.test(password));
            
            // Check lowercase
            toggleRequirement(checklist.querySelector('[data-check="lower"]'), /[a-z]/.test(password));
            
            // Check number
            toggleRequirement(checklist.querySelector('[data-check="number"]'), /\d/.test(password));
            
            // Check special character
            toggleRequirement(checklist.querySelector('[data-check="special"]'), /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password));
        });

        function toggleRequirement(element, isValid) {
            if (isValid) {
                element.classList.add('met');
            } else {
                element.classList.remove('met');
            }
        }

        // Modal Controls
        document.getElementById('footerTerms').addEventListener('click', function() {
            document.getElementById('termsModal').classList.remove('hidden');
        });

        document.getElementById('openTerms').addEventListener('click', function() {
            document.getElementById('termsModal').classList.remove('hidden');
        });

        document.getElementById('closeTerms').addEventListener('click', function() {
            document.getElementById('termsModal').classList.add('hidden');
        });

        document.getElementById('closeTermsBottom').addEventListener('click', function() {
            document.getElementById('termsModal').classList.add('hidden');
        });

        document.getElementById('footerPrivacy').addEventListener('click', function() {
            document.getElementById('privacyModal').classList.remove('hidden');
        });

        document.getElementById('openPrivacy').addEventListener('click', function() {
            document.getElementById('privacyModal').classList.remove('hidden');
        });

        document.getElementById('closePrivacy').addEventListener('click', function() {
            document.getElementById('privacyModal').classList.add('hidden');
        });

        document.getElementById('closePrivacyBottom').addEventListener('click', function() {
            document.getElementById('privacyModal').classList.add('hidden');
        });

        // OTP Input Navigation
        document.querySelectorAll('.otp-input').forEach((input, index, inputs) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Show register form if there's a register message (after form submission with error)
        <?php if ($register_message && strpos($register_message, 'successful') === false): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('registerFormContainer').classList.remove('hidden');
            });
        <?php endif; ?>

        // Function to show login form (used after successful registration)
        function showLoginForm() {
            document.getElementById('registerFormContainer').classList.add('hidden');
        }
    </script>
</body>
</html>