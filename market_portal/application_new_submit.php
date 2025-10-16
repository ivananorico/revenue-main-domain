<?php
session_start();
require_once '../db/Market/market_db.php';

// Get data from POST
$stall_id = $_POST['stall_id'] ?? null;
$user_id = $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;

// Redirect if missing essential data
if (!$stall_id || !$user_id) {
    $_SESSION['error'] = "Missing required information. Please try again.";
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
        
        // Store error and redirect back to application form
        $_SESSION['error'] = $error;
        $_SESSION['form_data'] = $_POST;
        header('Location: application_new.php?stall_id=' . $stall_id);
        exit;
    }
} else {
    // If not a POST request, redirect to market portal
    $_SESSION['error'] = "Invalid request method.";
    header('Location: market_portal.php');
    exit;
}
?>