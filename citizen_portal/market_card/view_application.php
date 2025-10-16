<?php
session_start();
require_once '../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    $_SESSION['error'] = "No application specified.";
    header('Location: apply_stall.php');
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
    
    // Create upload directory in market_portal
    $upload_dir = __DIR__ . '/../../market_portal/uploads/applications/';
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit;
        }
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

// Fetch application details
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
            s.name AS stall_name, 
            s.status AS stall_status,
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
        header('Location: apply_stall.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: apply_stall.php');
    exit;
}

// Fetch documents for this application
try {
    $docStmt = $pdo->prepare("
        SELECT document_type, file_name, file_path, file_size, file_extension, uploaded_at
        FROM documents 
        WHERE application_id = ?
        ORDER BY uploaded_at DESC
    ");
    $docStmt->execute([$application_id]);
    $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching documents: " . $e->getMessage());
    $documents = [];
}

// Function to check if file exists and get web path
function getFilePath($filePath) {
    if (!$filePath) {
        return false;
    }

    // Path: from citizen_portal/market_card → up to revenue/market_portal/
    $relativePath = '../../market_portal/' . ltrim($filePath, '/');
    $fullPath = __DIR__ . '/../../market_portal/' . ltrim($filePath, '/');

    if (!file_exists($fullPath)) {
        return false;
    }

    return $relativePath;
}

// Function to display file
function displayFile($filePath, $fileType, $fileName, $fileExtension) {
    $fileUrl = getFilePath($filePath);
    
    if (!$fileUrl) {
        return '<span class="file-missing">File not found</span>';
    }

    // Get file icon based on extension
    $fileIcon = getFileIcon($fileExtension);

    return '
        <div class="file-display">
            <div class="file-info">
                <div class="file-icon">' . $fileIcon . '</div>
                <div class="file-details">
                    <div class="file-name" title="' . htmlspecialchars($fileName) . '">' . htmlspecialchars($fileName) . '</div>
                    <div class="file-type">' . strtoupper($fileExtension) . ' File</div>
                </div>
            </div>
        </div>
    ';
}

// Function to display downloadable document
function displayDownloadableFile($filePath, $fileType, $fileName, $fileId = null) {
    $fileUrl = getFilePath($filePath);
    
    if (!$fileUrl) {
        return '<span class="file-missing">File not found</span>';
    }

    return '
        <div class="file-display downloadable-file">
            <div class="file-info">
                <div class="file-icon">📥</div>
                <div class="file-details">
                    <div class="file-name" title="' . htmlspecialchars($fileName) . '">' . htmlspecialchars($fileName) . '</div>
                    <div class="file-type">' . htmlspecialchars($fileType) . '</div>
                </div>
            </div>
            <div class="file-actions">
                <a href="' . htmlspecialchars($fileUrl) . '" download class="btn-download">Download</a>
            </div>
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

// Function to get display name for document type
function getDocumentTypeDisplayName($documentType) {
    $displayNames = [
        'barangay_certificate' => 'Barangay Certificate',
        'id_picture' => 'ID Picture',
        'stall_rights_certificate' => 'Stall Rights Certificate',
        'business_permit' => 'Business Permit',
        'lease_contract' => 'Lease Contract'
    ];
    
    return $displayNames[$documentType] ?? ucfirst(str_replace('_', ' ', $documentType));
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

// Function to format complete address from components
function formatCompleteAddress($application) {
    $parts = [
        $application['house_number'],
        $application['street'],
        $application['barangay'],
        $application['city'],
        $application['zip_code']
    ];
    
    // Filter out empty parts
    $parts = array_filter($parts, function($part) {
        return !empty($part) && trim($part) !== '';
    });
    
    return implode(', ', $parts);
}

// Function to format full name from components
function formatFullName($application) {
    $parts = [
        $application['first_name'],
        $application['middle_name'],
        $application['last_name']
    ];
    
    // Filter out empty parts
    $parts = array_filter($parts, function($part) {
        return !empty($part) && trim($part) !== '';
    });
    
    return implode(' ', $parts);
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
    <title>View Application - Municipal Services</title>
    <link rel="stylesheet" href="apply_stall.css">
    <link rel="stylesheet" href="../navbar.css">
    <link rel="stylesheet" href="view_application.css">
    <link rel="stylesheet" href="view_application_modal.css">
</head>
<body>

<?php include '../navbar.php'; ?>

<div class="application-details-container">
    <div class="application-content">
        <div class="application-header">
            <h1>Stall Application Details</h1>
            <p>Application ID: #<?php echo $application['id']; ?></p>
        </div>

        <!-- Application Status -->
        <div class="application-section">
            <h2 class="section-title">Application Status</h2>
            <div class="info-card">
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo strtolower($application['status']); ?>">
                            <?php 
                            $statusDisplay = [
                                'pending' => 'Pending Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                                'payment_phase' => 'Ready for Payment',
                                'paid' => 'Payment Completed',
                                'documents_submitted' => 'Documents Submitted'
                            ];
                            echo $statusDisplay[$application['status']] ?? ucfirst($application['status']);
                            ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Application Date:</div>
                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($application['application_date'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Last Updated:</div>
                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($application['updated_at'])); ?></div>
                </div>
            </div>
        </div>

        <?php if ($application['status'] === 'paid'): ?>
        <!-- SIMPLIFIED VIEW FOR PAID STATUS -->
        
        <!-- Action Required Section -->
        <div class="application-section action-required">
            <h2 class="section-title">📋 Action Required</h2>
            <div class="action-card">
                <div class="action-header">
                    <h3>Complete Your Application</h3>
                    <p>Please complete the following steps to finalize your stall application:</p>
                </div>
                
                <div class="action-steps">
                    <div class="action-step <?php echo hasLeaseContract($documents) && hasBusinessPermit($documents) ? 'completed' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Download Required Documents</h4>
                            <p>Download the Lease Contract and Stall Rights Agreement templates</p>
                            <?php if (!hasLeaseContract($documents) || !hasBusinessPermit($documents)): ?>
                                <div class="step-action">
                                    <a href="#download-section" class="btn-primary">Download Documents</a>
                                </div>
                            <?php else: ?>
                                <div class="step-status completed">✅ Completed</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="action-step <?php echo hasLeaseContract($documents) && hasBusinessPermit($documents) ? 'completed' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Submit Signed Documents</h4>
                            <p>Upload your signed Lease Contract and Business Permit</p>
                            <?php if (!hasLeaseContract($documents) || !hasBusinessPermit($documents)): ?>
                                <div class="step-action">
                                    <a href="#upload-section" class="btn-primary">Upload Documents</a>
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
        <div id="download-section" class="application-section">
            <h2 class="section-title">Documents to Download</h2>
            <div class="downloadable-documents">
                <div class="documents-grid">
                    <?php foreach ($downloadableDocuments as $doc): ?>
                        <?php echo displayDownloadableFile($doc['path'], $doc['type'], $doc['name'], $doc['id']); ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="document-instructions">
                    <h4>📝 Instructions:</h4>
                    <ol>
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
        <div id="upload-section" class="application-section">
            <h2 class="section-title">Submit Required Documents</h2>
            <div class="upload-section-grid">
                <!-- Lease Contract Upload -->
                <div class="upload-card <?php echo hasLeaseContract($documents) ? 'completed' : 'required'; ?>">
                    <div class="upload-card-header">
                        <h4>Lease Contract</h4>
                        <?php if (hasLeaseContract($documents)): ?>
                            <span class="upload-status-badge completed">✅ Uploaded</span>
                        <?php else: ?>
                            <span class="upload-status-badge required">📤 Required</span>
                        <?php endif; ?>
                    </div>
                    <p>Signed Lease Contract document</p>
                    <?php if (hasLeaseContract($documents)): ?>
                        <?php 
                        $leaseContract = getDocument($documents, 'lease_contract');
                        echo displayFile(
                            $leaseContract['file_path'], 
                            getDocumentTypeDisplayName($leaseContract['document_type']),
                            $leaseContract['file_name'],
                            $leaseContract['file_extension']
                        ); 
                        ?>
                        <button onclick="openUploadModal('lease_contract')" class="btn-secondary">Replace Document</button>
                    <?php else: ?>
                        <button onclick="openUploadModal('lease_contract')" class="btn-primary">Upload Lease Contract</button>
                    <?php endif; ?>
                </div>

                <!-- Business Permit Upload -->
                <div class="upload-card <?php echo hasBusinessPermit($documents) ? 'completed' : 'required'; ?>">
                    <div class="upload-card-header">
                        <h4>Business Permit</h4>
                        <?php if (hasBusinessPermit($documents)): ?>
                            <span class="upload-status-badge completed">✅ Uploaded</span>
                        <?php else: ?>
                            <span class="upload-status-badge required">📤 Required</span>
                        <?php endif; ?>
                    </div>
                    <p>Valid Business Permit document</p>
                    <?php if (hasBusinessPermit($documents)): ?>
                        <?php 
                        $businessPermit = getDocument($documents, 'business_permit');
                        echo displayFile(
                            $businessPermit['file_path'], 
                            getDocumentTypeDisplayName($businessPermit['document_type']),
                            $businessPermit['file_name'],
                            $businessPermit['file_extension']
                        ); 
                        ?>
                        <button onclick="openUploadModal('business_permit')" class="btn-secondary">Replace Document</button>
                    <?php else: ?>
                        <button onclick="openUploadModal('business_permit')" class="btn-primary">Upload Business Permit</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- FULL VIEW FOR OTHER STATUSES -->

        <!-- Personal Information -->
        <div class="application-section">
            <h2 class="section-title">Personal Information</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars(formatFullName($application)); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Gender:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['gender']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date of Birth:</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($application['date_of_birth'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Civil Status:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['civil_status']); ?></div>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-label">Complete Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars(formatCompleteAddress($application)); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contact Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['contact_number']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['email']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stall Information -->
        <div class="application-section">
            <h2 class="section-title">Stall Information</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-label">Business Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['business_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Application Type:</div>
                        <div class="info-value"><?php echo ucfirst($application['application_type']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Stall Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['stall_number']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Market Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['market_name']); ?></div>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-label">Market Section:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['section_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Stall Class:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['class_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Monthly Rent:</div>
                        <div class="info-value">₱<?php echo number_format($application['stall_price'], 2); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Dimensions:</div>
                        <div class="info-value">
                            <?php echo $application['length'] . 'm × ' . $application['width'] . 'm × ' . $application['height'] . 'm'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uploaded Documents -->
        <div class="application-section">
            <h2 class="section-title">Uploaded Documents</h2>
            <div class="info-grid">
                <?php if (empty($documents)): ?>
                    <div class="no-documents">
                        No documents uploaded for this application.
                    </div>
                <?php else: ?>
                    <?php 
                    $leaseContract = getDocument($documents, 'lease_contract');
                    $businessPermit = getDocument($documents, 'business_permit');
                    $otherDocuments = array_filter($documents, function($doc) {
                        return !in_array($doc['document_type'], ['lease_contract', 'business_permit']);
                    });
                    
                    if ($leaseContract): ?>
                        <div class="info-card highlighted">
                            <h4>✅ Signed Lease of Contract</h4>
                            <?php echo displayFile(
                                $leaseContract['file_path'], 
                                getDocumentTypeDisplayName($leaseContract['document_type']),
                                $leaseContract['file_name'],
                                $leaseContract['file_extension']
                            ); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($businessPermit): ?>
                        <div class="info-card highlighted">
                            <h4>✅ Business Permit</h4>
                            <?php echo displayFile(
                                $businessPermit['file_path'], 
                                getDocumentTypeDisplayName($businessPermit['document_type']),
                                $businessPermit['file_name'],
                                $businessPermit['file_extension']
                            ); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach ($otherDocuments as $document): ?>
                        <div class="info-card">
                            <h4><?php echo getDocumentTypeDisplayName($document['document_type']); ?></h4>
                            <?php echo displayFile(
                                $document['file_path'], 
                                getDocumentTypeDisplayName($document['document_type']),
                                $document['file_name'],
                                $document['file_extension']
                            ); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

        <!-- Application Timeline (Consistent for all statuses) -->
        <div class="application-section">
            <h2 class="section-title">Application Timeline</h2>
            <div class="info-card">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y', strtotime($application['application_date'])); ?></div>
                        <div class="timeline-content">
                            <strong>Application Submitted</strong><br>
                            Your application was successfully submitted for review.
                        </div>
                    </div>
                    
                    <?php if ($application['status'] === 'paid'): ?>
                    <!-- Additional timeline items for paid status -->
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y', strtotime($application['updated_at'])); ?></div>
                        <div class="timeline-content">
                            <strong>Payment Completed</strong><br>
                            Your payment has been processed successfully. Please complete the document requirements.
                        </div>
                    </div>
                    
                    <?php if (hasLeaseContract($documents) || hasBusinessPermit($documents)): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y'); ?></div>
                        <div class="timeline-content">
                            <strong>Document Submission In Progress</strong><br>
                            <?php
                            $uploadedDocs = [];
                            if (hasLeaseContract($documents)) $uploadedDocs[] = "Lease Contract";
                            if (hasBusinessPermit($documents)) $uploadedDocs[] = "Business Permit";
                            ?>
                            You have uploaded: <?php echo implode(', ', $uploadedDocs); ?>.
                            <?php if (count($uploadedDocs) < 2): ?>
                                Please upload the remaining documents.
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php elseif ($application['status'] === 'documents_submitted'): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y', strtotime($application['updated_at'])); ?></div>
                        <div class="timeline-content">
                            <strong>Documents Submitted</strong><br>
                            All required documents have been submitted. Your application is now under final review.
                        </div>
                    </div>
                    <?php elseif ($application['status'] === 'approved'): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y', strtotime($application['updated_at'])); ?></div>
                        <div class="timeline-content">
                            <strong>Application Approved</strong><br>
                            Your application has been approved. You can now proceed with the stall setup.
                        </div>
                    </div>
                    <?php elseif ($application['status'] === 'rejected'): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y', strtotime($application['updated_at'])); ?></div>
                        <div class="timeline-content">
                            <strong>Application Rejected</strong><br>
                            Your application was not approved. Please check the reviewer notes for more information.
                        </div>
                    </div>
                    <?php elseif ($application['status'] === 'cancelled'): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('M j, Y', strtotime($application['updated_at'])); ?></div>
                        <div class="timeline-content">
                            <strong>Application Cancelled</strong><br>
                            This application has been cancelled.
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Future step indicator -->
                    <?php if ($application['status'] !== 'approved' && $application['status'] !== 'rejected' && $application['status'] !== 'cancelled'): ?>
                    <div class="timeline-item future">
                        <div class="timeline-date">Next Step</div>
                        <div class="timeline-content">
                            <strong>
                                <?php if ($application['status'] === 'paid'): ?>
                                    Final Review
                                <?php else: ?>
                                    Processing
                                <?php endif; ?>
                            </strong><br>
                            <?php if ($application['status'] === 'paid'): ?>
                                Your application will be reviewed once all documents are submitted.
                            <?php else: ?>
                                Your application is being processed. We will notify you of any updates.
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="application-actions">
            <a href="apply_stall.php" class="btn-back">← Back to My Applications</a>
            <?php if ($application['status'] === 'paid'): ?>
                <button onclick="window.print()" class="btn-secondary">Print Instructions</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div id="uploadModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Upload Document</h3>
            <button class="modal-close" onclick="closeUploadModal()">×</button>
        </div>
        <div class="upload-form">
            <form id="documentUploadForm" enctype="multipart/form-data">
                <div class="file-input-container">
                    <input type="file" name="document_file" id="document_file" class="file-input" required accept=".pdf,.jpg,.jpeg,.png">
                    <input type="hidden" name="upload_type" id="upload_type" value="">
                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                </div>
                <div class="upload-status" id="uploadStatus"></div>
                <button type="submit" class="upload-button" id="uploadButton">Upload Document</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadModal = document.getElementById('uploadModal');
    const uploadForm = document.getElementById('documentUploadForm');
    const uploadStatus = document.getElementById('uploadStatus');
    const uploadButton = document.getElementById('uploadButton');
    const modalTitle = document.getElementById('modalTitle');
    const uploadTypeInput = document.getElementById('upload_type');
    const fileInput = document.getElementById('document_file');
    
    let currentUploadType = '';

    // Function to open upload modal
    window.openUploadModal = function(uploadType) {
        currentUploadType = uploadType;
        uploadTypeInput.value = uploadType;
        
        // Set modal title based on document type
        if (uploadType === 'lease_contract') {
            modalTitle.textContent = 'Upload Signed Lease Contract';
        } else if (uploadType === 'business_permit') {
            modalTitle.textContent = 'Upload Business Permit';
        }
        
        uploadModal.style.display = 'flex';
        uploadStatus.style.display = 'none';
        uploadForm.reset();
    }

    // Function to close upload modal
    window.closeUploadModal = function() {
        uploadModal.style.display = 'none';
        currentUploadType = '';
    }

    // Close modal when clicking outside
    uploadModal.addEventListener('click', function(e) {
        if (e.target === uploadModal) {
            closeUploadModal();
        }
    });

    // Handle form submission - submit to same page
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!fileInput.files.length) {
            uploadStatus.className = 'upload-status error';
            uploadStatus.textContent = 'Please select a file to upload.';
            uploadStatus.style.display = 'block';
            return;
        }
        
        let formData = new FormData();
        
        // Add the file with consistent field name
        formData.append('document_file', fileInput.files[0]);
        formData.append('upload_type', currentUploadType);
        formData.append('application_id', '<?php echo $application_id; ?>');
        
        uploadButton.disabled = true;
        uploadButton.textContent = 'Uploading...';
        uploadStatus.style.display = 'none';
        
        // Send to same page (self)
        fetch('view_application.php?application_id=<?php echo $application_id; ?>', {
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

    // Add file input change event to show file name
    fileInput.addEventListener('change', function(e) {
        if (this.files.length > 0) {
            console.log('Selected file:', this.files[0].name);
        }
    });
});
</script>

</body>
</html>