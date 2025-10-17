<?php
session_start();
require_once '../../db/RPT/rpt_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Guest';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Personal Information
    $first_name = htmlspecialchars($_POST['first_name']);
    $middle_name = htmlspecialchars($_POST['middle_name'] ?? '');
    $last_name = htmlspecialchars($_POST['last_name']);
    $gender = htmlspecialchars($_POST['gender']);
    $date_of_birth = htmlspecialchars($_POST['date_of_birth']);
    $civil_status = htmlspecialchars($_POST['civil_status']);
    $house_number = htmlspecialchars($_POST['house_number'] ?? '');
    $street = htmlspecialchars($_POST['street'] ?? '');
    $barangay = htmlspecialchars($_POST['barangay']);
    $city = htmlspecialchars($_POST['city']);
    $zip_code = htmlspecialchars($_POST['zip_code'] ?? '');
    $contact_number = htmlspecialchars($_POST['contact_number']);
    $email = htmlspecialchars($_POST['email']);
    
    // Application Type and Property Location
    $application_type = htmlspecialchars($_POST['application_type']);
    $property_type = htmlspecialchars($_POST['property_type']);
    $property_address = htmlspecialchars($_POST['property_address']);
    $property_barangay = htmlspecialchars($_POST['property_barangay']);
    $property_municipality = htmlspecialchars($_POST['property_municipality']);
    
    // For Transfer Applications
    $previous_tdn = htmlspecialchars($_POST['previous_tdn'] ?? '');
    $previous_owner = htmlspecialchars($_POST['previous_owner'] ?? '');

    // File upload handling
    $upload_errors = [];
    $uploaded_files = [];

    // Create uploads directory if it doesn't exist - FIXED PATH
    $upload_dir = __DIR__ . '/../../RPT/uploads/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $upload_errors[] = "Cannot create upload directory";
        }
    }

    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        $upload_errors[] = "Upload directory is not writable. Please check permissions.";
    }

    // Allowed file types
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    // Base file fields for all applications
    $file_fields = [
        'tct' => 'TCT',
        'valid_id' => 'Valid ID',
        'barangay_clearance' => 'Barangay Clearance',
        'location_plan' => 'Location Plan',
        'survey_plan' => 'Survey Plan'
    ];

    // Add transfer-specific documents
    if ($application_type === 'transfer') {
        $file_fields['deed_of_sale'] = 'Deed of Sale';
        $file_fields['tax_clearance'] = 'Tax Clearance';
        $file_fields['tax_declaration'] = 'Tax Declaration';
    }

    foreach ($file_fields as $field => $description) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            
            // Debug file info
            error_log("Processing file: " . $file['name'] . ", Size: " . $file['size'] . ", Temp: " . $file['tmp_name']);
            
            // Check if file is actually uploaded
            if (!is_uploaded_file($file['tmp_name'])) {
                $upload_errors[] = "$description file upload failed";
                continue;
            }

            // Check file size
            if ($file['size'] == 0) {
                $upload_errors[] = "$description file is empty";
                continue;
            }
            
            if ($file['size'] > $max_file_size) {
                $upload_errors[] = "$description file is too large (max 5MB)";
                continue;
            }

            // Check file type using both extension and MIME type
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_mimes = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                'application/pdf'
            ];

            if (!in_array($file_extension, $allowed_types) || !in_array($mime_type, $allowed_mimes)) {
                $upload_errors[] = "$description file type not allowed. Allowed: PDF, JPG, PNG. Detected: $mime_type";
                continue;
            }

            // Generate unique filename
            $new_filename = $field . '_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            // Move uploaded file with error checking
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Verify the file was actually moved and is readable
                if (file_exists($upload_path) && filesize($upload_path) > 0) {
                    $uploaded_files[$field] = [
                        'filename' => $new_filename,
                        'original_name' => $file['name'],
                        'path' => $upload_path,
                        'type' => $field,
                        'size' => $file['size']
                    ];
                    error_log("File uploaded successfully: " . $upload_path);
                } else {
                    $upload_errors[] = "$description file was not saved properly";
                }
            } else {
                $upload_errors[] = "Failed to upload $description file. Check directory permissions.";
                error_log("File move failed for: " . $file['tmp_name'] . " to " . $upload_path);
            }
        } else {
            $upload_error_code = $_FILES[$field]['error'] ?? 'N/A';
            // Check required files based on application type
            if ($field === 'tct') {
                // TCT is always required
                if (empty($_FILES['tct']['name'])) {
                    $upload_errors[] = "TCT is required";
                }
            } elseif ($field === 'valid_id' || $field === 'barangay_clearance') {
                // Valid ID and Barangay Clearance are always required
                if (empty($_FILES[$field]['name'])) {
                    $upload_errors[] = "$description is required";
                }
            } elseif ($application_type === 'transfer' && ($field === 'tax_clearance' || $field === 'tax_declaration')) {
                // Tax clearance and tax declaration are required for transfers
                if (empty($_FILES[$field]['name'])) {
                    $upload_errors[] = "$description is required for transfer applications";
                }
            }
        }
    }

    // If no upload errors, proceed with database insertion
    if (empty($upload_errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO rpt_applications 
                (user_id, application_type, first_name, middle_name, last_name, gender, date_of_birth, civil_status,
                 house_number, street, barangay, city, zip_code, contact_number, email,
                 property_type, property_address, property_barangay, property_municipality,
                 previous_tdn, previous_owner, status, application_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
            $stmt->execute([
                $user_id, $application_type, $first_name, $middle_name, $last_name, $gender, $date_of_birth, $civil_status,
                $house_number, $street, $barangay, $city, $zip_code, $contact_number, $email,
                $property_type, $property_address, $property_barangay, $property_municipality,
                $previous_tdn, $previous_owner
            ]);
            
            $application_id = $pdo->lastInsertId();
            
            // Save uploaded file references to database
            foreach ($uploaded_files as $field => $file_info) {
                $stmt = $pdo->prepare("INSERT INTO rpt_documents 
                    (application_id, document_type, file_name, file_path, uploaded_at) 
                    VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$application_id, $file_info['type'], $file_info['filename'], $file_info['path']]);
            }
            
            $success_message = "Property registration submitted successfully! Our assessor will visit your property for assessment.";
            
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please fix the following errors: " . implode(", ", $upload_errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Registration - RPT System</title>
    <link rel="stylesheet" href="../../citizen_portal/navbar.css">
    <link rel="stylesheet" href="register_rpt.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../citizen_portal/navbar.php'; ?>
    
    <div class="rpt-register-container">
        <div class="rpt-register-header">
            <h1><i class="fas fa-home"></i> Property Registration</h1>
            <p>Register your property for Real Property Tax</p>
            <div class="registration-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Our assessor will visit your property for assessment. Tax calculation will be done after site inspection.
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
                <?php if (isset($uploaded_files) && count($uploaded_files) > 0): ?>
                    <br><small>Files uploaded: <?php echo count($uploaded_files); ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="rpt-register-form" id="rptRegisterForm" enctype="multipart/form-data">
            <!-- Application Type -->
            <div class="form-section">
                <h2><i class="fas fa-file-alt"></i> Application Type</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="application_type">Application Type *</label>
                        <select id="application_type" name="application_type" required onchange="toggleTransferFields()">
                            <option value="">Select Application Type</option>
                            <option value="new">New Registration (No TDN)</option>
                            <option value="transfer">Transfer of Ownership</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Property Owner Information -->
            <div class="form-section">
                <h2><i class="fas fa-user"></i> Property Owner Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required value="<?php echo $_POST['first_name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="<?php echo $_POST['middle_name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required value="<?php echo $_POST['last_name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($_POST['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($_POST['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($_POST['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="civil_status">Civil Status *</label>
                        <select id="civil_status" name="civil_status" required>
                            <option value="">Select Civil Status</option>
                            <option value="Single" <?php echo ($_POST['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo ($_POST['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                            <option value="Divorced" <?php echo ($_POST['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo ($_POST['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="house_number">House Number</label>
                        <input type="text" id="house_number" name="house_number" value="<?php echo $_POST['house_number'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="street">Street</label>
                        <input type="text" id="street" name="street" value="<?php echo $_POST['street'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="barangay">Barangay *</label>
                        <input type="text" id="barangay" name="barangay" required value="<?php echo $_POST['barangay'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">City *</label>
                        <input type="text" id="city" name="city" required value="<?php echo $_POST['city'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="zip_code">ZIP Code</label>
                        <input type="text" id="zip_code" name="zip_code" pattern="[0-9]{4}" value="<?php echo $_POST['zip_code'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number">Contact Number *</label>
                        <input type="tel" id="contact_number" name="contact_number" required placeholder="09XXXXXXXXX" value="<?php echo $_POST['contact_number'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required placeholder="your@email.com" value="<?php echo $_POST['email'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Property Location -->
            <div class="form-section">
                <h2><i class="fas fa-map-marker-alt"></i> Property Location</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="property_type">Type of Property *</label>
                        <select id="property_type" name="property_type" required>
                            <option value="">Select Property Type</option>
                            <option value="land_only" <?php echo ($_POST['property_type'] ?? '') === 'land_only' ? 'selected' : ''; ?>>Land Only</option>
                            <option value="land_with_house" <?php echo ($_POST['property_type'] ?? '') === 'land_with_house' ? 'selected' : ''; ?>>Land with House/Building</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="property_address">Property Address *</label>
                        <input type="text" id="property_address" name="property_address" required 
                               placeholder="Street, Purok, Subdivision, or Sitio" value="<?php echo $_POST['property_address'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="property_barangay">Property Barangay *</label>
                        <input type="text" id="property_barangay" name="property_barangay" required value="<?php echo $_POST['property_barangay'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="property_municipality">Property Municipality/City *</label>
                        <input type="text" id="property_municipality" name="property_municipality" required value="<?php echo $_POST['property_municipality'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Transfer Application Fields (Hidden by default) -->
            <div class="form-section transfer-fields" id="transferFields" style="display: none;">
                <h2><i class="fas fa-exchange-alt"></i> Transfer Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="previous_tdn">Previous TDN *</label>
                        <input type="text" id="previous_tdn" name="previous_tdn" placeholder="Previous Tax Declaration Number" value="<?php echo $_POST['previous_tdn'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="previous_owner">Previous Owner *</label>
                        <input type="text" id="previous_owner" name="previous_owner" placeholder="Name of previous owner" value="<?php echo $_POST['previous_owner'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Required Documents Upload -->
            <div class="form-section">
                <h2><i class="fas fa-paperclip"></i> Upload Required Documents</h2>
                <div class="documents-upload">
                    <p><strong>Upload digital copies of the following required documents:</strong></p>
                    
                    <div class="upload-grid">
                        <!-- TCT (Always Required) -->
                        <div class="upload-item required">
                            <div class="upload-header">
                                <i class="fas fa-file-contract"></i>
                                <div class="upload-info">
                                    <span class="upload-title">TCT (Transfer Certificate of Title) *</span>
                                    <small class="upload-desc">For titled properties</small>
                                </div>
                            </div>
                            <input type="file" id="tct" name="tct" accept=".pdf,.jpg,.jpeg,.png" required>
                            <label for="tct" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="tct_info"></div>
                        </div>

                        <!-- Deed of Sale (Transfer Only) -->
                        <div class="upload-item transfer-only" style="display: none;">
                            <div class="upload-header">
                                <i class="fas fa-file-signature"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Deed of Sale *</span>
                                    <small class="upload-desc">For properties acquired through sale</small>
                                </div>
                            </div>
                            <input type="file" id="deed_of_sale" name="deed_of_sale" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="deed_of_sale" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="deed_of_sale_info"></div>
                        </div>

                        <!-- Valid ID -->
                        <div class="upload-item required">
                            <div class="upload-header">
                                <i class="fas fa-id-card"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Valid ID *</span>
                                    <small class="upload-desc">Government-issued identification</small>
                                </div>
                            </div>
                            <input type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                            <label for="valid_id" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="valid_id_info"></div>
                        </div>

                        <!-- Barangay Clearance -->
                        <div class="upload-item required">
                            <div class="upload-header">
                                <i class="fas fa-file-certificate"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Barangay Clearance *</span>
                                    <small class="upload-desc">From property location barangay</small>
                                </div>
                            </div>
                            <input type="file" id="barangay_clearance" name="barangay_clearance" accept=".pdf,.jpg,.jpeg,.png" required>
                            <label for="barangay_clearance" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="barangay_clearance_info"></div>
                        </div>

                        <!-- Tax Clearance (Transfer only) -->
                        <div class="upload-item transfer-only" style="display: none;">
                            <div class="upload-header">
                                <i class="fas fa-receipt"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Tax Clearance *</span>
                                    <small class="upload-desc">Proof of no tax delinquency</small>
                                </div>
                            </div>
                            <input type="file" id="tax_clearance" name="tax_clearance" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="tax_clearance" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="tax_clearance_info"></div>
                        </div>

                        <!-- Tax Declaration (Transfer only) -->
                        <div class="upload-item transfer-only" style="display: none;">
                            <div class="upload-header">
                                <i class="fas fa-file-invoice"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Tax Declaration *</span>
                                    <small class="upload-desc">Previous tax declaration</small>
                                </div>
                            </div>
                            <input type="file" id="tax_declaration" name="tax_declaration" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="tax_declaration" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="tax_declaration_info"></div>
                        </div>

                        <!-- Location Plan -->
                        <div class="upload-item">
                            <div class="upload-header">
                                <i class="fas fa-map"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Location Plan</span>
                                    <small class="upload-desc">Property location sketch</small>
                                </div>
                            </div>
                            <input type="file" id="location_plan" name="location_plan" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="location_plan" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="location_plan_info"></div>
                        </div>

                        <!-- Survey Plan -->
                        <div class="upload-item">
                            <div class="upload-header">
                                <i class="fas fa-ruler-combined"></i>
                                <div class="upload-info">
                                    <span class="upload-title">Survey Plan</span>
                                    <small class="upload-desc">Cadastral or survey plan</small>
                                </div>
                            </div>
                            <input type="file" id="survey_plan" name="survey_plan" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="survey_plan" class="upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose File
                            </label>
                            <div class="file-info" id="survey_plan_info"></div>
                        </div>
                    </div>

                    <div class="upload-notes">
                        <p><i class="fas fa-info-circle"></i> <strong>File Requirements:</strong></p>
                        <ul>
                            <li>Maximum file size: 5MB per file</li>
                            <li>Allowed formats: PDF, JPG, PNG only</li>
                            <li>TCT is required for all applications</li>
                            <li>Deed of Sale is required for transfer applications</li>
                            <li>Required documents must be uploaded</li>
                            <li>Ensure documents are clear and readable</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Site Visit Notice -->
            <div class="form-section">
                <div class="site-visit-notice">
                    <h3><i class="fas fa-clipboard-check"></i> Property Assessment Notice</h3>
                    <p>Our Municipal Assessor will conduct a site visit to:</p>
                    <ul>
                        <li>Verify property location and boundaries</li>
                        <li>Assess property condition and improvements</li>
                        <li>Determine lot number, survey number, and total area</li>
                        <li>Take measurements and photographs for documentation</li>
                    </ul>
                    <p><strong>Please ensure someone is available at the property during assessment.</strong></p>
                </div>
            </div>

            <!-- Declaration -->
            <div class="form-section declaration">
                <div class="declaration-content">
                    <h3><i class="fas fa-shield-alt"></i> Declaration</h3>
                    <p>I hereby certify that:</p>
                    <ul>
                        <li>The information provided in this registration form is true and correct</li>
                        <li>I am the lawful owner/authorized representative of this property</li>
                        <li>I agree to allow property assessment by the Municipal Assessor</li>
                        <li>I understand that any false information may result in penalties</li>
                    </ul>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="declaration_agree" name="declaration_agree" required>
                        <label for="declaration_agree">I certify that the above information is true and correct *</label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="window.location.href='rpt_dashboard.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Registration
                </button>
            </div>
        </form>
    </div>

    <script>
        // File upload preview functionality
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = this.files[0];
                const fileInfo = document.getElementById(this.id + '_info');
                
                if (file) {
                    const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                    fileInfo.innerHTML = `
                        <div class="file-preview">
                            <i class="fas fa-file"></i>
                            <div class="file-details">
                                <span class="file-name">${file.name}</span>
                                <span class="file-size">${fileSize} MB</span>
                            </div>
                        </div>
                    `;
                } else {
                    fileInfo.innerHTML = '';
                }
            });
        });

        // Toggle transfer fields
        function toggleTransferFields() {
            const applicationType = document.getElementById('application_type').value;
            const transferFields = document.getElementById('transferFields');
            const transferDocs = document.querySelectorAll('.transfer-only');
            
            if (applicationType === 'transfer') {
                transferFields.style.display = 'block';
                transferDocs.forEach(doc => doc.style.display = 'block');
                // Make transfer fields required
                document.getElementById('previous_tdn').required = true;
                document.getElementById('previous_owner').required = true;
                document.getElementById('deed_of_sale').required = true;
                document.getElementById('tax_clearance').required = true;
                document.getElementById('tax_declaration').required = true;
            } else {
                transferFields.style.display = 'none';
                transferDocs.forEach(doc => doc.style.display = 'none');
                // Remove required from transfer fields
                document.getElementById('previous_tdn').required = false;
                document.getElementById('previous_owner').required = false;
                document.getElementById('deed_of_sale').required = false;
                document.getElementById('tax_clearance').required = false;
                document.getElementById('tax_declaration').required = false;
            }
        }

        // Form validation
        document.getElementById('rptRegisterForm').addEventListener('submit', function(e) {
            const declarationChecked = document.getElementById('declaration_agree').checked;
            if (!declarationChecked) {
                e.preventDefault();
                alert('Please certify that the information provided is true and correct before submitting.');
                return false;
            }

            // Check required files
            const validId = document.getElementById('valid_id').files.length;
            const barangayClearance = document.getElementById('barangay_clearance').files.length;
            const tct = document.getElementById('tct').files.length;
            
            if (!validId || !barangayClearance || !tct) {
                e.preventDefault();
                alert('Please upload all required documents (TCT, Valid ID and Barangay Clearance).');
                return false;
            }

            // Check transfer-specific documents
            const applicationType = document.getElementById('application_type').value;
            if (applicationType === 'transfer') {
                const deedOfSale = document.getElementById('deed_of_sale').files.length;
                const taxClearance = document.getElementById('tax_clearance').files.length;
                const taxDeclaration = document.getElementById('tax_declaration').files.length;
                
                if (!deedOfSale || !taxClearance || !taxDeclaration) {
                    e.preventDefault();
                    alert('Please upload all required documents for transfer (Deed of Sale, Tax Clearance and Tax Declaration).');
                    return false;
                }
            }
        });

        // Set initial state on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleTransferFields();
        });
    </script>
</body>
</html>