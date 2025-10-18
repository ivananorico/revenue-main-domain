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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    header('Content-Type: application/json');
    
    $upload_type = $_POST['upload_type'] ?? '';
    $application_id = $_POST['application_id'] ?? '';
    
    // Validate upload
    if (!$application_id || !$upload_type) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $allowed_types = ['lease_contract', 'business_permit'];
    if (!in_array($upload_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid document type']);
        exit;
    }
    
    // Verify application belongs to user and is in paid status
    try {
        $stmt = $pdo->prepare("SELECT id, status FROM applications WHERE id = ? AND user_id = ?");
        $stmt->execute([$application_id, $user_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            echo json_encode(['success' => false, 'message' => 'Application not found or access denied']);
            exit;
        }
        
        if ($application['status'] !== 'paid') {
            echo json_encode(['success' => false, 'message' => 'Document upload only allowed for paid applications']);
            exit;
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $file = $_FILES['document_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit (max 5MB)',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $error_message = $error_messages[$file['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => "File upload error: " . $error_message]);
        exit;
    }
    
    // File validation
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_size = $file['size'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, JPEG, PNG files are allowed.']);
        exit;
    }
    
    if ($file_size > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum file size is 5MB.']);
        exit;
    }
    
    // Create upload directory in revenue/market_portal/uploads/applications/
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/revenue/market_portal/uploads/applications/';

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!is_writable($upload_dir)) {
    echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
    exit;
}

    
    if (!is_writable($upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit;
    }
    
    // Generate unique filename
    $new_filename = $upload_type . '_' . $application_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
        exit;
    }
    
    // Verify file was moved
    if (!file_exists($file_path)) {
        echo json_encode(['success' => false, 'message' => 'File was not saved properly']);
        exit;
    }
    
    // Database path (relative to market_portal)
    $db_file_path = 'uploads/applications/' . $new_filename;
    
    // Insert into documents table
    try {
        // Check if document already exists
        $check_stmt = $pdo->prepare("SELECT id FROM documents WHERE application_id = ? AND document_type = ?");
        $check_stmt->execute([$application_id, $upload_type]);
        $existing_doc = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_doc) {
            // Update existing document
            $stmt = $pdo->prepare("
                UPDATE documents 
                SET file_name = :file_name, 
                    file_path = :file_path, 
                    file_size = :file_size, 
                    file_extension = :file_extension,
                    uploaded_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':file_name' => $file['name'],
                ':file_path' => $db_file_path,
                ':file_size' => $file_size,
                ':file_extension' => $file_extension,
                ':id' => $existing_doc['id']
            ]);
        } else {
            // Insert new document
            $stmt = $pdo->prepare("
                INSERT INTO documents 
                (application_id, document_type, file_name, file_path, file_size, file_extension, uploaded_at)
                VALUES (:application_id, :document_type, :file_name, :file_path, :file_size, :file_extension, NOW())
            ");
            
            $stmt->execute([
                ':application_id' => $application_id,
                ':document_type' => $upload_type,
                ':file_name' => $file['name'],
                ':file_path' => $db_file_path,
                ':file_size' => $file_size,
                ':file_extension' => $file_extension
            ]);
        }
        
        // Check if both required documents are uploaded and update application status
        $check_docs_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT document_type) as doc_count 
            FROM documents 
            WHERE application_id = ? 
            AND document_type IN ('lease_contract', 'business_permit')
        ");
        $check_docs_stmt->execute([$application_id]);
        $doc_count = $check_docs_stmt->fetch(PDO::FETCH_ASSOC)['doc_count'];
        
        if ($doc_count == 2) {
            // Both documents are uploaded, update application status to 'documents_submitted'
            $update_status_stmt = $pdo->prepare("UPDATE applications SET status = 'documents_submitted', updated_at = NOW() WHERE id = ?");
            $update_status_stmt->execute([$application_id]);
        }
        
        // Update application timestamp regardless of status change
        $update_app_stmt = $pdo->prepare("UPDATE applications SET updated_at = NOW() WHERE id = ?");
        $update_app_stmt->execute([$application_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => ucfirst(str_replace('_', ' ', $upload_type)) . " uploaded successfully!",
            'status_updated' => ($doc_count == 2)
        ]);
        exit;
        
    } catch (PDOException $e) {
        // Clean up the uploaded file if database insert failed
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        echo json_encode(['success' => false, 'message' => 'Failed to save document record to database']);
        exit;
    }
}

// Continue with normal page display for GET requests

// Fetch application details for paid status
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
            s.name AS stall_name, 
            s.price AS stall_price,
            s.length, s.width, s.height,
            m.name AS market_name,
            sr.class_name,
            sr.description AS class_description,
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

    // Fetch documents
    $docStmt = $pdo->prepare("
        SELECT document_type, file_name, file_path, file_size, file_extension, uploaded_at
        FROM documents 
        WHERE application_id = ?
        ORDER BY uploaded_at DESC
    ");
    $docStmt->execute([$application_id]);
    $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch payment details
    $payment_stmt = $pdo->prepare("
        SELECT * FROM application_fee 
        WHERE application_id = ? AND status = 'paid'
        ORDER BY id DESC LIMIT 1
    ");
    $payment_stmt->execute([$application_id]);
    $payment_details = $payment_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: ../apply_stall.php');
    exit;
}

// ========== PAID STATUS CONDITIONAL FUNCTIONS ==========

// Function to check if file exists and get web path
function getFilePath($filePath) {
    if (!$filePath) return false;

    // Correct full path on server
    $fullPath = __DIR__ . '/../../../market_portal/' . ltrim($filePath, '/');

    // Check file exists
    if (file_exists($fullPath)) {
        // Return URL relative to web root
        return '/revenue/market_portal/' . ltrim($filePath, '/');
    } else {
        error_log("File missing: " . $fullPath);
        return false;
    }
}


// Function to display downloadable document
function displayDownloadableFile($filePath, $fileType, $fileName, $fileId = null) {
    $fileUrl = getFilePath($filePath);
    
    if (!$fileUrl) {
        return '<div class="document-card bg-red-50 border-red-200">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-xl">❌</span>
                        </div>
                        <h4 class="font-semibold text-red-800 mb-1">' . htmlspecialchars($fileName) . '</h4>
                        <p class="text-red-600 text-sm">File not found</p>
                    </div>
                </div>';
    }

    return '
        <div class="document-card bg-blue-50 border-blue-200 hover:border-blue-300 cursor-pointer" onclick="openDocumentModal(\'' . htmlspecialchars($fileUrl) . '\', \'' . htmlspecialchars($fileName) . '\')">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="text-xl">📄</span>
                </div>
                <h4 class="font-semibold text-blue-800 mb-1">' . htmlspecialchars($fileName) . '</h4>
                <p class="text-blue-600 text-sm mb-3">' . htmlspecialchars($fileType) . '</p>
                <a href="' . htmlspecialchars($fileUrl) . '" download 
                   class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded transition-colors duration-200 inline-block"
                   onclick="event.stopPropagation()">
                    📥 Download
                </a>
            </div>
        </div>
    ';
}

// Function to display uploaded document
function displayUploadedFile($filePath, $fileType, $fileName, $fileExtension) {
    $fileUrl = getFilePath($filePath);
    
    if (!$fileUrl) {
        return '<div class="text-red-600 text-sm">
                    <span class="font-semibold">' . htmlspecialchars($fileName) . '</span>
                    <span class="text-red-500 ml-2">(File missing)</span>
                </div>';
    }

    $fileIcon = getFileIcon($fileExtension);

    return '
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex items-center space-x-3">
                <span class="text-xl">' . $fileIcon . '</span>
                <div>
                    <div class="font-medium text-gray-800">' . htmlspecialchars($fileName) . '</div>
                    <div class="text-sm text-gray-500">' . strtoupper($fileExtension) . ' • ' . htmlspecialchars($fileType) . '</div>
                </div>
            </div>
            <button onclick="openDocumentModal(\'' . htmlspecialchars($fileUrl) . '\', \'' . htmlspecialchars($fileName) . '\')" 
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded transition-colors duration-200">
                👁️ View
            </button>
        </div>
    ';
}

// Function to get file icon
function getFileIcon($extension) {
    $icons = [
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝'
    ];
    
    return $icons[$extension] ?? '📁';
}

// Function to check if lease contract is uploaded
function hasLeaseContract($documents) {
    foreach ($documents as $document) {
        if ($document['document_type'] === 'lease_contract') {
            return true;
        }
    }
    return false;
}

// Function to check if business permit is uploaded
function hasBusinessPermit($documents) {
    foreach ($documents as $document) {
        if ($document['document_type'] === 'business_permit') {
            return true;
        }
    }
    return false;
}

// Function to get specific document
function getDocument($documents, $documentType) {
    foreach ($documents as $document) {
        if ($document['document_type'] === $documentType) {
            return $document;
        }
    }
    return null;
}

// Define downloadable documents for payment phase
$downloadableDocuments = [
    [
        'path' => 'documents_to_sign/Lease_of_Contract.png',
        'name' => 'Lease of Contract Template',
        'type' => 'Contract Template',
        'id' => 'lease-contract'
    ],
    [
        'path' => 'documents_to_sign/Stall_Rights.png',
        'name' => 'Stall Rights Agreement',
        'type' => 'Agreement Template',
        'id' => 'stall-rights'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Application - Municipal Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .document-card {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .completed {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
        .required {
            border-color: #f59e0b;
            background-color: #fffbeb;
        }
        .action-step {
            border-left: 4px solid #e5e7eb;
            padding-left: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }
        .action-step.completed {
            border-left-color: #10b981;
        }
        .step-number {
            position: absolute;
            left: -0.75rem;
            top: 0;
            width: 1.5rem;
            height: 1.5rem;
            background: #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .action-step.completed .step-number {
            background: #10b981;
            color: white;
        }
        .step-status.completed {
            color: #10b981;
            font-weight: bold;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 90vw;
            max-height: 90vh;
            overflow: hidden;
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        .modal-body {
            padding: 0;
            max-height: 70vh;
            overflow: auto;
        }
        .upload-status {
            display: none;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }
        .upload-status.success {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
        }
        .upload-status.error {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #7f1d1d;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8 max-w-6xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Complete Your Application</h1>
            <p class="text-gray-600">Application ID: #<?= $application['id'] ?></p>
            <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4 inline-block">
                <p class="text-green-700 font-semibold">Payment Reference: <?= $payment_details['reference_number'] ?? 'N/A' ?></p>
            </div>
        </div>

        <!-- Status Card -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-green-800 mb-2">✅ Payment Completed</h2>
                    <p class="text-green-700">Your payment has been processed! Please complete the final document requirements.</p>
                </div>
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded-full font-semibold">
                    Paid
                </div>
            </div>
        </div>

        <!-- Action Required Section -->
        <div class="application-section action-required mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 Action Required</h2>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                <div class="action-header mb-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Complete Your Application</h3>
                    <p class="text-gray-600">Please complete the following steps to finalize your stall application:</p>
                </div>
                
                <div class="action-steps">
                    <!-- Step 1: Download Documents -->
                    <div class="action-step <?php echo hasLeaseContract($documents) && hasBusinessPermit($documents) ? 'completed' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4 class="text-lg font-semibold text-gray-800 mb-2">Download Required Documents</h4>
                            <p class="text-gray-600 mb-4">Download the Lease Contract and Stall Rights Agreement templates</p>
                            <?php if (!hasLeaseContract($documents) || !hasBusinessPermit($documents)): ?>
                                <div class="step-action">
                                    <a href="#download-section" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 inline-block">
                                        Download Documents
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="step-status completed">✅ Completed</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Step 2: Upload Documents -->
                    <div class="action-step <?php echo hasLeaseContract($documents) && hasBusinessPermit($documents) ? 'completed' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4 class="text-lg font-semibold text-gray-800 mb-2">Submit Signed Documents</h4>
                            <p class="text-gray-600 mb-4">Upload your signed Lease Contract and Business Permit</p>
                            <?php if (!hasLeaseContract($documents) || !hasBusinessPermit($documents)): ?>
                                <div class="step-action">
                                    <a href="#upload-section" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 inline-block">
                                        Upload Documents
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="step-status completed">✅ Completed</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Downloadable Documents Section -->
        <div id="download-section" class="application-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Documents to Download</h2>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                <div class="documents-grid grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <?php foreach ($downloadableDocuments as $doc): ?>
                        <?php echo displayDownloadableFile($doc['path'], $doc['type'], $doc['name'], $doc['id']); ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="document-instructions bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-800 mb-3">📝 Instructions:</h4>
                    <ol class="text-blue-700 space-y-2 list-decimal list-inside">
                        <li><strong>Download</strong> both documents above</li>
                        <li><strong>Print</strong> the Lease Contract template</li>
                        <li><strong>Sign</strong> the document in designated areas</li>
                        <li><strong>Use the signed Lease Contract</strong> to apply for your Business Permit</li>
                        <li><strong>Upload</strong> both signed documents below</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Upload Section -->
        <div id="upload-section" class="application-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Submit Required Documents</h2>
            <div class="upload-section-grid grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Lease Contract Upload -->
                <div class="document-card <?php echo hasLeaseContract($documents) ? 'completed' : 'required'; ?>">
                    <div class="upload-card-header flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-800">Lease Contract</h4>
                        <?php if (hasLeaseContract($documents)): ?>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">✅ Uploaded</span>
                        <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-semibold">📤 Required</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-600 mb-4">Signed Lease Contract document</p>
                    
                    <?php if (hasLeaseContract($documents)): ?>
                        <?php 
                        $leaseContract = getDocument($documents, 'lease_contract');
                        echo displayUploadedFile(
                            $leaseContract['file_path'], 
                            'Lease Contract',
                            $leaseContract['file_name'],
                            $leaseContract['file_extension']
                        ); 
                        ?>
                        <div class="mt-4">
                            <button onclick="openUploadModal('lease_contract')" 
                                    class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded transition-colors duration-200">
                                Replace Document
                            </button>
                        </div>
                    <?php else: ?>
                        <button onclick="openUploadModal('lease_contract')" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded transition-colors duration-200">
                            Upload Lease Contract
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Business Permit Upload -->
                <div class="document-card <?php echo hasBusinessPermit($documents) ? 'completed' : 'required'; ?>">
                    <div class="upload-card-header flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold text-gray-800">Business Permit</h4>
                        <?php if (hasBusinessPermit($documents)): ?>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">✅ Uploaded</span>
                        <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-semibold">📤 Required</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-600 mb-4">Valid Business Permit document</p>
                    
                    <?php if (hasBusinessPermit($documents)): ?>
                        <?php 
                        $businessPermit = getDocument($documents, 'business_permit');
                        echo displayUploadedFile(
                            $businessPermit['file_path'], 
                            'Business Permit',
                            $businessPermit['file_name'],
                            $businessPermit['file_extension']
                        ); 
                        ?>
                        <div class="mt-4">
                            <button onclick="openUploadModal('business_permit')" 
                                    class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded transition-colors duration-200">
                                Replace Document
                            </button>
                        </div>
                    <?php else: ?>
                        <button onclick="openUploadModal('business_permit')" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded transition-colors duration-200">
                            Upload Business Permit
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Progress Status -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <span class="text-lg font-semibold text-gray-800">Document Completion Progress</span>
                <span class="text-lg font-semibold text-gray-700">
                    <?= (hasLeaseContract($documents) + hasBusinessPermit($documents)) ?> / 2
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                <div class="bg-green-600 h-3 rounded-full transition-all duration-500" 
                     style="width: <?= ((hasLeaseContract($documents) + hasBusinessPermit($documents)) / 2) * 100 ?>%">
                </div>
            </div>
            <p class="text-sm text-gray-600">
                <?php if (hasLeaseContract($documents) && hasBusinessPermit($documents)): ?>
                    ✅ All documents submitted! Your application is complete and ready for final review.
                <?php else: ?>
                    Please upload all required documents to complete your application.
                <?php endif; ?>
            </p>
        </div>

        <!-- Application Summary -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Application Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Business Name</p>
                    <p class="font-medium"><?= htmlspecialchars($application['business_name']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Market</p>
                    <p class="font-medium"><?= htmlspecialchars($application['market_name']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Stall Number</p>
                    <p class="font-medium"><?= htmlspecialchars($application['stall_number']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Monthly Rent</p>
                    <p class="font-medium">₱<?= number_format($application['stall_price'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex justify-between items-center">
            <a href="../market-dashboard.php" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200">
                ← Back to Dashboard
            </a>
            <button onclick="window.print()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200">
                🖨️ Print Instructions
            </button>
        </div>
    </div>

    <!-- Document View Modal -->
    <div id="documentModal" class="modal-overlay">
        <div class="modal-content w-full max-w-4xl">
            <div class="modal-header">
                <h3 id="modalTitle" class="text-xl font-semibold text-gray-800">Document Preview</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700 text-2xl" onclick="closeDocumentModal()">×</button>
            </div>
            <div class="modal-body">
                <iframe id="documentFrame" src="" style="width: 100%; height: 70vh; border: none;"></iframe>
            </div>
            <div class="modal-footer p-4 border-t border-gray-200 flex justify-between">
                <button onclick="closeDocumentModal()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded transition-colors duration-200">
                    Close
                </button>
                <a id="downloadLink" href="" download 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded transition-colors duration-200">
                    📥 Download
                </a>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div id="uploadModal" class="modal-overlay" style="display: none;">
        <div class="modal-content w-full max-w-md">
            <div class="modal-header">
                <h3 id="uploadModalTitle" class="text-xl font-semibold text-gray-800">Upload Document</h3>
                <button class="modal-close text-gray-500 hover:text-gray-700 text-2xl" onclick="closeUploadModal()">×</button>
            </div>
            <div class="p-6">
                <form id="documentUploadForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                        <input type="file" name="document_file" id="document_file" class="file-input" required accept=".pdf,.jpg,.jpeg,.png">
                        <p class="text-sm text-gray-500 mt-1">Accepted formats: PDF, JPG, JPEG, PNG (Max: 5MB)</p>
                    </div>
                    <input type="hidden" name="upload_type" id="upload_type" value="">
                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                    
                    <div class="upload-status" id="uploadStatus"></div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="closeUploadModal()" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-4 rounded transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded transition-colors duration-200" id="uploadButton">
                            Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentUploadType = '';

        // Document View Modal Functions
        function openDocumentModal(fileUrl, fileName) {
            const modal = document.getElementById('documentModal');
            const frame = document.getElementById('documentFrame');
            const title = document.getElementById('modalTitle');
            const downloadLink = document.getElementById('downloadLink');
            
            title.textContent = fileName;
            frame.src = fileUrl;
            downloadLink.href = fileUrl;
            downloadLink.download = fileName;
            
            modal.style.display = 'flex';
        }

        function closeDocumentModal() {
            const modal = document.getElementById('documentModal');
            const frame = document.getElementById('documentFrame');
            
            modal.style.display = 'none';
            frame.src = '';
        }

        // Upload Modal Functions
        function openUploadModal(uploadType) {
            currentUploadType = uploadType;
            const modal = document.getElementById('uploadModal');
            const title = document.getElementById('uploadModalTitle');
            const uploadTypeInput = document.getElementById('upload_type');
            
            // Set modal title based on document type
            if (uploadType === 'lease_contract') {
                title.textContent = 'Upload Signed Lease Contract';
            } else if (uploadType === 'business_permit') {
                title.textContent = 'Upload Business Permit';
            }
            
            uploadTypeInput.value = uploadType;
            modal.style.display = 'flex';
            
            // Reset form
            document.getElementById('documentUploadForm').reset();
            document.getElementById('uploadStatus').style.display = 'none';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            currentUploadType = '';
        }

        // Handle upload form submission
        document.getElementById('documentUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('document_file');
            const uploadStatus = document.getElementById('uploadStatus');
            const uploadButton = document.getElementById('uploadButton');
            
            if (!fileInput.files.length) {
                uploadStatus.className = 'upload-status error';
                uploadStatus.textContent = 'Please select a file to upload.';
                uploadStatus.style.display = 'block';
                return;
            }
            
            let formData = new FormData();
            formData.append('document_file', fileInput.files[0]);
            formData.append('upload_type', currentUploadType);
            formData.append('application_id', '<?php echo $application_id; ?>');
            
            uploadButton.disabled = true;
            uploadButton.textContent = 'Uploading...';
            uploadStatus.style.display = 'none';
            
            // Send upload request
            fetch('view_paid.php?application_id=<?php echo $application_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    uploadStatus.className = 'upload-status success';
                    uploadStatus.textContent = data.message;
                    uploadStatus.style.display = 'block';
                    
                    // Show additional message if status was updated
                    if (data.status_updated) {
                        uploadStatus.textContent += ' All required documents have been submitted. Your application status has been updated.';
                    }
                    
                    // Refresh page after 2 seconds to show the uploaded document and updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    uploadStatus.className = 'upload-status error';
                    uploadStatus.textContent = data.message;
                    uploadStatus.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                uploadStatus.className = 'upload-status error';
                uploadStatus.textContent = 'An error occurred during upload. Please try again.';
                uploadStatus.style.display = 'block';
            })
            .finally(() => {
                uploadButton.disabled = false;
                uploadButton.textContent = 'Upload Document';
            });
        });

        // Close modals when clicking outside
        document.getElementById('documentModal').addEventListener('click', function(e) {
            if (e.target === this) closeDocumentModal();
        });
        
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) closeUploadModal();
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDocumentModal();
                closeUploadModal();
            }
        });
    </script>

</body>
</html>