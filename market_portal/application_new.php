<?php
session_start();
require_once '../db/Market/market_db.php';

// Get data from POST
$stall_id = $_POST['stall_id'] ?? null;
$stall_name = $_POST['stall_name'] ?? '';
$stall_price = $_POST['stall_price'] ?? 0;
$stall_dimensions = $_POST['stall_dimensions'] ?? '';
$stall_class = $_POST['stall_class'] ?? '';
$stall_rights = $_POST['stall_rights'] ?? '';
$user_id = $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;
$name = $_POST['name'] ?? $_SESSION['full_name'] ?? '';
$email = $_POST['email'] ?? $_SESSION['email'] ?? '';

// Redirect if missing essential data
if (!$stall_id || !$user_id) {
    $_SESSION['error'] = "Missing required information. Please try again.";
    header('Location: market_portal.php');
    exit;
}

// Fetch stall details for display WITH SECTION INFORMATION
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               m.name as map_name, 
               sr.class_name, 
               sr.description as class_description,
               sec.name as section_name
        FROM stalls s 
        LEFT JOIN maps m ON s.map_id = m.id 
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id 
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.id = ?
    ");
    $stmt->execute([$stall_id]);
    $stall = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $stall = null;
}

if (!$stall) {
    $_SESSION['error'] = "Stall not found.";
    header('Location: market_portal.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    try {
        // File upload configuration
        $uploadDir = __DIR__ . '/uploads/applications/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Get additional form data with new address fields
        $full_name = $_POST['full_name'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $civil_status = $_POST['civil_status'] ?? '';
        
        // New address fields (without province)
        $house_number = $_POST['house_number'] ?? '';
        $street = $_POST['street'] ?? '';
        $barangay = $_POST['barangay'] ?? '';
        $city = $_POST['city'] ?? '';
        $zip_code = $_POST['zip_code'] ?? '';
        
        $contact_number = $_POST['contact_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $market_name = $_POST['market_name'] ?? '';
        $market_section = $_POST['market_section'] ?? '';
        $stall_number = $_POST['stall_number'] ?? '';
        $business_name = $_POST['business_name'] ?? '';
        $certification_agree = isset($_POST['certification_agree']) ? 1 : 0;
        
        // Validate required fields including new address fields
        if (empty($full_name) || empty($gender) || empty($date_of_birth) || empty($civil_status) || 
            empty($house_number) || empty($street) || empty($barangay) || empty($city) || 
            empty($zip_code) || empty($contact_number) || empty($business_name)) {
            throw new Exception('All required fields must be filled');
        }
        
        // Insert application into database with new address fields
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (user_id, stall_id, application_type, full_name, gender, date_of_birth, civil_status, 
             house_number, street, barangay, city, zip_code,
             contact_number, email, market_name, market_section, stall_number,
             business_name, certification_agree, status) 
            VALUES (?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $user_id, 
            $stall_id, 
            $full_name, 
            $gender, 
            $date_of_birth, 
            $civil_status,
            $house_number,
            $street,
            $barangay,
            $city,
            $zip_code,
            $contact_number, 
            $email,
            $market_name,
            $market_section,
            $stall_number,
            $business_name,
            $certification_agree
        ]);
        
        $application_id = $pdo->lastInsertId();
        
        // Handle document uploads and insert into documents table
        
        // Barangay Certificate
        if (isset($_FILES['barangay_certificate']) && $_FILES['barangay_certificate']['error'] === UPLOAD_ERR_OK) {
            $barangayCert = $_FILES['barangay_certificate'];
            $fileExtension = strtolower(pathinfo($barangayCert['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception('Barangay certificate must be JPG, PNG, or PDF');
            }
            
            if ($barangayCert['size'] > $maxFileSize) {
                throw new Exception('Barangay certificate file size must be less than 5MB');
            }
            
            $barangayCertName = "barangay_cert_{$user_id}_" . time() . "." . $fileExtension;
            $barangayCertPath = 'uploads/applications/' . $barangayCertName;
            $barangayCertFullPath = $uploadDir . $barangayCertName;
            
            if (!move_uploaded_file($barangayCert['tmp_name'], $barangayCertFullPath)) {
                throw new Exception('Failed to upload barangay certificate');
            }
            
            // Insert into documents table
            $docStmt = $pdo->prepare("
                INSERT INTO documents 
                (application_id, document_type, file_name, file_path, file_size, file_extension) 
                VALUES (?, 'barangay_certificate', ?, ?, ?, ?)
            ");
            $docStmt->execute([
                $application_id,
                $barangayCert['name'],
                $barangayCertPath,
                $barangayCert['size'],
                $fileExtension
            ]);
        } else {
            throw new Exception('Barangay certificate is required');
        }
        
        // ID Picture
        if (isset($_FILES['id_picture']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
            $idPicture = $_FILES['id_picture'];
            $fileExtension = strtolower(pathinfo($idPicture['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                throw new Exception('ID picture must be JPG or PNG');
            }
            
            if ($idPicture['size'] > $maxFileSize) {
                throw new Exception('ID picture file size must be less than 5MB');
            }
            
            $idPictureName = "id_picture_{$user_id}_" . time() . "." . $fileExtension;
            $idPicturePath = 'uploads/applications/' . $idPictureName;
            $idPictureFullPath = $uploadDir . $idPictureName;
            
            if (!move_uploaded_file($idPicture['tmp_name'], $idPictureFullPath)) {
                throw new Exception('Failed to upload ID picture');
            }
            
            // Insert into documents table
            $docStmt = $pdo->prepare("
                INSERT INTO documents 
                (application_id, document_type, file_name, file_path, file_size, file_extension) 
                VALUES (?, 'id_picture', ?, ?, ?, ?)
            ");
            $docStmt->execute([
                $application_id,
                $idPicture['name'],
                $idPicturePath,
                $idPicture['size'],
                $fileExtension
            ]);
        } else {
            throw new Exception('ID picture is required');
        }
        
        // Update stall status to reserved
        $updateStmt = $pdo->prepare("UPDATE stalls SET status = 'reserved' WHERE id = ?");
        $updateStmt->execute([$stall_id]);
        
        // Store success message and redirect
        $_SESSION['success'] = "Application submitted successfully! Your application ID is #{$application_id}. Your application is now under review.";
        $_SESSION['application_id'] = $application_id;
        
        header('Location: application_success.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Clean up uploaded files if there was an error
        if (isset($uploadDir)) {
            $files = glob($uploadDir . "*_{$user_id}_*");
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Stall - Market Stall Portal</title>
    <link rel="stylesheet" href="application.css">
    <link rel="stylesheet" href="../citizen_portal/navbar.css">
</head>
<body>
<?php include '../citizen_portal/navbar.php'; ?>

<div class="portal-container">
    <div class="application-container">
        <div class="portal-header">
            <h1>Apply for Market Stall</h1>
            <p>Complete the application form below</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stall Summary -->
        <div class="stall-summary">
            <h3>Stall Information</h3>
            <div class="stall-details-grid">
                <div><strong>Stall Name:</strong> <?php echo htmlspecialchars($stall_name); ?></div>
                <div><strong>Class:</strong> <?php echo htmlspecialchars($stall_class); ?></div>
                <div><strong>Monthly Rent:</strong> ₱<?php echo number_format($stall_price, 2); ?></div>
                <div><strong>Dimensions:</strong> <?php echo htmlspecialchars($stall_dimensions); ?></div>
                <div><strong>Location:</strong> <?php echo htmlspecialchars($stall['map_name'] ?? 'N/A'); ?></div>
                <div><strong>Market Section:</strong> <?php echo htmlspecialchars($stall['section_name'] ?? 'No Section Assigned'); ?></div>
            </div>
        </div>

        <!-- Application Form -->
        <form class="application-form" method="POST" enctype="multipart/form-data">
            <!-- Hidden fields -->
            <input type="hidden" name="stall_id" value="<?php echo htmlspecialchars($stall_id); ?>">
            <input type="hidden" name="stall_name" value="<?php echo htmlspecialchars($stall_name); ?>">
            <input type="hidden" name="stall_price" value="<?php echo htmlspecialchars($stall_price); ?>">
            <input type="hidden" name="stall_dimensions" value="<?php echo htmlspecialchars($stall_dimensions); ?>">
            <input type="hidden" name="stall_class" value="<?php echo htmlspecialchars($stall_class); ?>">
            <input type="hidden" name="stall_rights" value="<?php echo htmlspecialchars($stall_rights); ?>">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

            <!-- Personal Information -->
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo htmlspecialchars($name); ?>"
                               placeholder="Enter your full name">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="civil_status">Civil Status <span class="required">*</span></label>
                        <select id="civil_status" name="civil_status" required>
                            <option value="">Select civil status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <h3>Address Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="house_number">House/Lot/Unit Number <span class="required">*</span></label>
                            <input type="text" id="house_number" name="house_number" required 
                                   placeholder="e.g., 123, Unit 4B, Lot 5">
                        </div>
                        <div class="form-group">
                            <label for="street">Street Name <span class="required">*</span></label>
                            <input type="text" id="street" name="street" required 
                                   placeholder="e.g., Main Street, Rizal Avenue">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="barangay">Barangay <span class="required">*</span></label>
                            <input type="text" id="barangay" name="barangay" required 
                                   placeholder="e.g., Sta. Monica, Barangay 123">
                        </div>
                        <div class="form-group">
                            <label for="city">City/Municipality <span class="required">*</span></label>
                            <input type="text" id="city" name="city" required 
                                   placeholder="e.g., Quezon City, Manila">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="zip_code">ZIP Code <span class="required">*</span></label>
                            <input type="text" id="zip_code" name="zip_code" required 
                                   placeholder="e.g., 1100, 1000" maxlength="10">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_number">Contact Number <span class="required">*</span></label>
                        <input type="tel" id="contact_number" name="contact_number" required 
                               placeholder="Enter your contact number">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               placeholder="Enter your email address">
                    </div>
                </div>
            </div>

            <!-- Stall Information -->
            <div class="form-section">
                <h3>Stall Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="market_name">Market Name <span class="required">*</span></label>
                        <input type="text" id="market_name" name="market_name" required 
                               value="<?php echo htmlspecialchars($stall['map_name'] ?? ''); ?>"
                               placeholder="Enter market name" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                        <label for="market_section_display">Market Section <span class="required">*</span></label>
                        <input type="text" id="market_section_display" name="market_section_display" required 
                               value="<?php echo htmlspecialchars($stall['section_name'] ?? 'No Section Assigned'); ?>"
                               placeholder="Market section will be auto-filled" readonly class="readonly-field">
                        <input type="hidden" name="market_section" value="<?php echo htmlspecialchars($stall['section_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="stall_number">Stall Number <span class="required">*</span></label>
                        <input type="text" id="stall_number" name="stall_number" required 
                               value="<?php echo htmlspecialchars($stall_name); ?>"
                               placeholder="Enter stall number" readonly class="readonly-field">
                    </div>
                </div>
                <div class="form-group">
                    <label for="business_name">Business Name <span class="required">*</span></label>
                    <input type="text" id="business_name" name="business_name" required 
                           placeholder="Enter your business name">
                </div>
            </div>

            <!-- Document Uploads -->
            <div class="form-section">
                <h3>Required Documents</h3>
                
                <div class="form-group">
                    <label for="barangay_certificate">Barangay Certificate <span class="required">*</span></label>
                    <div class="file-upload">
                        <input type="file" id="barangay_certificate" name="barangay_certificate" 
                               accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="file-info">Accepted formats: JPG, PNG, PDF (Max: 5MB)</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="id_picture">Current ID Picture <span class="required">*</span></label>
                    <div class="file-upload">
                        <input type="file" id="id_picture" name="id_picture" 
                               accept=".jpg,.jpeg,.png" required>
                        <div class="file-info">Accepted formats: JPG, PNG (Max: 5MB)</div>
                    </div>
                </div>
            </div>

            <!-- Certification -->
            <div class="form-section">
                <h3>Certification</h3>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="certification_agree" name="certification_agree" required>
                        <label for="certification_agree">
                            I hereby certify that all information provided in this application is true and correct. 
                            I understand that any false information may result in the rejection of my application 
                            or termination of my stall rights.
                            <span class="required">*</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Buttons -->
            <div class="form-buttons">
                <a href="market_portal.php" class="btn-cancel">Cancel</a>
                <button type="submit" name="submit_application" class="btn-submit" id="submitButton">
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// File input change handlers
function validateFile(input, maxSize) {
    const file = input.files[0];
    if (file) {
        const fileSize = file.size / 1024 / 1024; // MB
        if (fileSize > maxSize) {
            alert('File size must be less than ' + maxSize + 'MB');
            input.value = '';
            return false;
        }
    }
    return true;
}

// Add event listeners for all file inputs
document.getElementById('barangay_certificate').addEventListener('change', function(e) {
    validateFile(this, 5);
});

document.getElementById('id_picture').addEventListener('change', function(e) {
    validateFile(this, 5);
});

// Enable submit button only when certification is checked
document.getElementById('certification_agree').addEventListener('change', function(e) {
    document.getElementById('submitButton').disabled = !this.checked;
});

// Initially disable submit button
document.getElementById('submitButton').disabled = true;
</script>
</body>
</html>