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

// Database connection
require_once '../../../db/Market/market_db.php';

$application = null;
$documents = [];
$stall_rights_fee = 0;

if ($application_id) {
    try {
        // Get application details with stall and map information
        $stmt = $pdo->prepare("
            SELECT 
                a.*, 
                s.name as stall_name, 
                s.pos_x, 
                s.pos_y,
                s.height,
                s.length, 
                s.width,
                s.price as stall_price,
                s.status as stall_status,
                m.name as market_name, 
                m.file_path as map_file_path,
                m.id as map_id,
                sec.name as section_name,
                sr.class_name,
                sr.price as stall_rights_price
            FROM applications a 
            LEFT JOIN stalls s ON a.stall_id = s.id 
            LEFT JOIN maps m ON s.map_id = m.id 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
            WHERE a.id = ? AND a.user_id = ?
        ");
        $stmt->execute([$application_id, $user_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($application) {
            // Get uploaded documents for this application
            $doc_stmt = $pdo->prepare("
                SELECT * FROM documents 
                WHERE application_id = ? 
                ORDER BY uploaded_at DESC
            ");
            $doc_stmt->execute([$application_id]);
            $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get stall rights fee
            $stall_rights_fee = $application['stall_rights_price'] ?? 0;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Calculate fees
$application_fee = 100.00;
$security_bond = 10000.00;
$total_amount = ($application['stall_price'] ?? 0) + $stall_rights_fee + $application_fee + $security_bond;

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

// Function to get correct file URL - FIXED PATH
function getFileUrl($filePath) {
    if (!$filePath) {
        return false;
    }
    
    // Files are at: http://localhost/revenue/market_portal/uploads/applications/
    // So we need the full URL path
    $baseUrl = '/revenue/market_portal/';
    return $baseUrl . ltrim($filePath, '/');
}

// Function to display file
function displayFile($filePath, $fileType, $fileName, $fileExtension, $documentType) {
    $fileUrl = getFileUrl($filePath);

    if (!$fileUrl) {
        return '<span class="file-missing">File not found</span>';
    }

    $fileIcon = getFileIcon($fileExtension);

    return '
        <div class="file-display" data-file-url="' . htmlspecialchars($fileUrl) . '" data-file-type="' . htmlspecialchars($fileType) . '" data-file-name="' . htmlspecialchars($fileName) . '" data-document-type="' . htmlspecialchars($documentType) . '">
            <div class="file-info">
                <div class="file-icon">' . $fileIcon . '</div>
                <div class="file-details">
                    <div class="file-name" title="' . htmlspecialchars($fileName) . '">' . htmlspecialchars($fileName) . '</div>
                    <div class="file-type">' . strtoupper($fileExtension) . ' File</div>
                </div>
            </div>
            <div class="file-actions">
                <button class="btn-view" onclick="openDocumentModal(\'' . htmlspecialchars($fileUrl) . '\', \'' . htmlspecialchars($fileType) . '\', \'' . htmlspecialchars($fileName) . '\', \'' . htmlspecialchars($documentType) . '\')">View</button>
                <a href="' . htmlspecialchars($fileUrl) . '" download class="btn-download">Download</a>
            </div>
        </div>
    ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details - Market Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-paid { background-color: #dbeafe; color: #1e40af; }
        .status-payment_phase { background-color: #fce7f3; color: #be185d; }
        
        .file-display {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            background: white;
        }
        .file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }
        .file-icon {
            font-size: 1.5rem;
        }
        .file-details {
            flex: 1;
        }
        .file-name {
            font-weight: 600;
            color: #374151;
        }
        .file-type {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-view, .btn-download, .btn-pay {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-view {
            background-color: #3b82f6;
            color: white;
        }
        .btn-view:hover {
            background-color: #2563eb;
        }
        .btn-download {
            background-color: #10b981;
            color: white;
        }
        .btn-download:hover {
            background-color: #059669;
        }
        .btn-pay {
            background-color: #8b5cf6;
            color: white;
        }
        .btn-pay:hover {
            background-color: #7c3aed;
        }
        .file-missing {
            color: #ef4444;
            font-style: italic;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 0.75rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            padding: 0.25rem;
        }

        .modal-close:hover {
            color: #374151;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }

        .document-viewer {
            text-align: center;
        }

        .document-viewer img {
            max-width: 100%;
            max-height: 60vh;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }

        .document-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }

        .document-info h4 {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .payment-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .fee-breakdown {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .fee-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .fee-item:last-child {
            border-bottom: none;
        }

        .fee-label {
            color: #374151;
        }

        .fee-value {
            font-weight: 600;
            color: #059669;
        }

        .fee-total {
            border-top: 2px solid #059669;
            padding-top: 1rem;
            margin-top: 0.5rem;
            font-weight: 700;
            font-size: 1.1rem;
        }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Application Details</h1>
            <p class="text-xl text-gray-600">Welcome, <?= htmlspecialchars($full_name) ?>! View your market stall application.</p>
        </div>

        <?php if (isset($error_message)): ?>
            <!-- Database Error -->
            <div class="max-w-2xl mx-auto bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <h3 class="text-lg font-semibold text-red-800 mb-2">Database Error</h3>
                <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
            </div>

        <?php elseif (!$application_id): ?>
            <!-- No application ID provided -->
            <div class="max-w-2xl mx-auto bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <h3 class="text-lg font-semibold text-yellow-800 mb-2">No Application Selected</h3>
                <p class="text-yellow-700">Please select an application from the dashboard to view details.</p>
                <a href="market-dashboard.php" class="inline-block mt-4 px-6 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                    Back to Dashboard
                </a>
            </div>

        <?php elseif (!$application): ?>
            <!-- Application not found or doesn't belong to user -->
            <div class="max-w-2xl mx-auto bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <h3 class="text-lg font-semibold text-red-800 mb-2">Application Not Found</h3>
                <p class="text-red-700">The requested application could not be found or you don't have permission to view it.</p>
                <a href="market-dashboard.php" class="inline-block mt-4 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    Back to Dashboard
                </a>
            </div>

        <?php else: ?>
            <!-- Application Found - Show Details -->
            <div class="max-w-6xl mx-auto space-y-8">

                <!-- Application Status -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Application Status</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Application ID</p>
                            <p class="font-semibold">#<?= $application['id'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <span class="status-badge status-<?= strtolower($application['status']) ?>">
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
                        <div>
                            <p class="text-sm text-gray-600">Application Date</p>
                            <p class="font-semibold"><?= date('F j, Y g:i A', strtotime($application['application_date'])) ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($application['status'] === 'payment_phase'): ?>
                <!-- PAYMENT PHASE SECTION -->
                <div class="payment-section">
                    <div class="text-center mb-6">
                        <h2 class="text-3xl font-bold text-white mb-2">Ready for Payment</h2>
                        <p class="text-white/90 text-lg">Your application has been approved! Please complete the payment to proceed.</p>
                    </div>
                    
                    <div class="fee-breakdown">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Payment Summary</h3>
                        <div class="fee-item">
                            <span class="fee-label">Monthly Stall Rent:</span>
                            <span class="fee-value">₱<?= number_format($application['stall_price'], 2) ?></span>
                        </div>
                        <div class="fee-item">
                            <span class="fee-label">Stall Rights Fee (Class <?= $application['class_name'] ?>):</span>
                            <span class="fee-value">₱<?= number_format($stall_rights_fee, 2) ?></span>
                        </div>
                        <div class="fee-item">
                            <span class="fee-label">Application Fee:</span>
                            <span class="fee-value">₱<?= number_format($application_fee, 2) ?></span>
                        </div>
                        <div class="fee-item">
                            <span class="fee-label">Security Bond:</span>
                            <span class="fee-value">₱<?= number_format($security_bond, 2) ?></span>
                        </div>
                        <div class="fee-item fee-total">
                            <span class="fee-label">Total Amount Due:</span>
                            <span class="fee-value">₱<?= number_format($total_amount, 2) ?></span>
                        </div>
                        
                        <div class="mt-6 text-center">
                            <button onclick="location.href='../../digital_card/market_payment_details.php?application_id=<?= $application_id ?>'" 
                                    class="btn-pay px-8 py-3 text-lg font-semibold">
                                💳 Proceed to Payment
                            </button>
                            <p class="text-sm text-gray-600 mt-2">Secure payment gateway • Multiple payment options available</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Personal Information -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Personal Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Full Name</p>
                                <p class="font-semibold"><?= htmlspecialchars(formatFullName($application)) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Gender</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['gender']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date of Birth</p>
                                <p class="font-semibold"><?= date('F j, Y', strtotime($application['date_of_birth'])) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Civil Status</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['civil_status']) ?></p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Complete Address</p>
                                <p class="font-semibold"><?= htmlspecialchars(formatCompleteAddress($application)) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Contact Number</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['contact_number']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email Address</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['email']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stall Information -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Stall Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Business Name</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['business_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Application Type</p>
                                <p class="font-semibold"><?= ucfirst($application['application_type']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Stall Number</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['stall_number']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Market Name</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['market_name']) ?></p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Market Section</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['section_name'] ?? $application['market_section']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Stall Class</p>
                                <p class="font-semibold"><?= htmlspecialchars($application['class_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Monthly Rent</p>
                                <p class="font-semibold">₱<?= number_format($application['stall_price'], 2) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Stall Rights Fee</p>
                                <p class="font-semibold">₱<?= number_format($stall_rights_fee, 2) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Dimensions</p>
                                <p class="font-semibold">
                                    <?= $application['length'] ?>m × <?= $application['width'] ?>m × <?= $application['height'] ?>m
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uploaded Documents (View Only - No Upload) -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Uploaded Documents</h2>
                    
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">No documents uploaded for this application yet.</p>
                            <?php if ($application['status'] === 'pending'): ?>
                                <p class="text-sm text-gray-400 mt-2">Documents will be available for upload after payment approval.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($documents as $doc): ?>
                                <?php echo displayFile(
                                    $doc['file_path'], 
                                    getDocumentTypeDisplayName($doc['document_type']),
                                    $doc['file_name'],
                                    $doc['file_extension'],
                                    $doc['document_type']
                                ); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Application Timeline -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Application Timeline</h2>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-3 h-3 bg-green-500 rounded-full mt-2"></div>
                            <div>
                                <p class="font-semibold">Application Submitted</p>
                                <p class="text-sm text-gray-600"><?= date('F j, Y g:i A', strtotime($application['application_date'])) ?></p>
                                <p class="text-gray-700">Your application was successfully submitted for review.</p>
                            </div>
                        </div>
                        
                        <?php if ($application['status'] === 'payment_phase'): ?>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-3 h-3 bg-purple-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="font-semibold">Application Approved</p>
                                    <p class="text-gray-700">Your application has been approved! Please proceed with payment to secure your stall.</p>
                                </div>
                            </div>
                        <?php elseif ($application['status'] === 'pending'): ?>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-3 h-3 bg-yellow-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="font-semibold">Under Review</p>
                                    <p class="text-gray-700">Your application is currently being reviewed by our team.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        <?php endif; ?>

        <!-- Back Button -->
        <div class="text-center mt-12">
            <a href="../market-dashboard.php" class="inline-flex items-center px-6 py-3 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-colors duration-200 font-semibold">
                ← Back to Market Dashboard
            </a>
        </div>
    </div>

    <!-- Document Modal -->
    <div id="documentModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Document Viewer</h3>
                <button class="modal-close" onclick="closeDocumentModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="document-viewer">
                    <img id="modalImage" src="" alt="Document" style="display: none;">
                    <iframe id="modalPdf" src="" width="100%" height="500px" style="display: none; border: none;"></iframe>
                    <div id="unsupportedFile" style="display: none; text-align: center; padding: 2rem;">
                        <p>This file type cannot be previewed. Please download the file to view it.</p>
                        <a id="downloadLink" href="#" download class="btn-download inline-block mt-4">Download File</a>
                    </div>
                </div>
                <div class="document-info">
                    <h4>Document Information</h4>
                    <p><strong>File Name:</strong> <span id="fileName"></span></p>
                    <p><strong>Document Type:</strong> <span id="documentType"></span></p>
                    <p><strong>Uploaded:</strong> <span id="uploadDate"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openDocumentModal(fileUrl, fileType, fileName, documentType) {
            const modal = document.getElementById('documentModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalImage = document.getElementById('modalImage');
            const modalPdf = document.getElementById('modalPdf');
            const unsupportedFile = document.getElementById('unsupportedFile');
            const downloadLink = document.getElementById('downloadLink');
            const fileNameSpan = document.getElementById('fileName');
            const documentTypeSpan = document.getElementById('documentType');
            
            // Set modal title and file info
            modalTitle.textContent = fileName;
            fileNameSpan.textContent = fileName;
            documentTypeSpan.textContent = documentType.replace('_', ' ');
            
            // Hide all viewers first
            modalImage.style.display = 'none';
            modalPdf.style.display = 'none';
            unsupportedFile.style.display = 'none';
            
            // Check file type and show appropriate viewer
            const fileExtension = fileUrl.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExtension)) {
                // Image file
                modalImage.src = fileUrl;
                modalImage.style.display = 'block';
            } else if (fileExtension === 'pdf') {
                // PDF file
                modalPdf.src = fileUrl;
                modalPdf.style.display = 'block';
            } else {
                // Unsupported file type
                downloadLink.href = fileUrl;
                unsupportedFile.style.display = 'block';
            }
            
            // Show modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDocumentModal() {
            const modal = document.getElementById('documentModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('documentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDocumentModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDocumentModal();
            }
        });
    </script>

</body>
</html>