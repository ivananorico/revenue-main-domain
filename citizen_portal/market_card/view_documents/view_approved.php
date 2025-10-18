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
    header('Location: ../market-dashboard.php');
    exit;
}

// Fetch application details with all related information
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
            s.name AS stall_name, 
            s.price AS stall_price,
            s.height,
            s.length, 
            s.width,
            s.status as stall_status,
            m.name AS market_name,
            sr.class_name,
            sr.price as stall_rights_price,
            sr.description as stall_rights_description,
            sec.name AS section_name,
            af.total_amount,
            af.payment_date,
            af.reference_number as payment_reference,
            r.renter_id,
            r.first_name as renter_first_name,
            r.middle_name as renter_middle_name,
            r.last_name as renter_last_name,
            r.contact_number as renter_contact,
            r.email as renter_email,
            r.business_name as renter_business_name,
            r.status as renter_status,
            r.created_at as renter_since,
            lc.contract_number,
            lc.start_date as contract_start,
            lc.end_date as contract_end,
            lc.monthly_rent as contract_rent,
            sri.certificate_number,
            sri.issue_date as cert_issue_date,
            sri.expiry_date as cert_expiry_date
        FROM applications a
        LEFT JOIN stalls s ON a.stall_id = s.id
        LEFT JOIN maps m ON s.map_id = m.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN application_fee af ON a.id = af.application_id
        LEFT JOIN renters r ON a.id = r.application_id
        LEFT JOIN lease_contracts lc ON a.id = lc.application_id
        LEFT JOIN stall_rights_issued sri ON a.id = sri.application_id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['error'] = "Application not found or access denied.";
        header('Location: ../market-dashboard.php');
        exit;
    }

    // Get uploaded documents for this application
    $doc_stmt = $pdo->prepare("
        SELECT * FROM documents 
        WHERE application_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $doc_stmt->execute([$application_id]);
    $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header('Location: ../market-dashboard.php');
    exit;
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

// Function to get file URL
function getFileUrl($filePath) {
    if (!$filePath) {
        return false;
    }
    $baseUrl = '/revenue/market_portal/';
    return $baseUrl . ltrim($filePath, '/');
}

// Function to format date
function formatDate($date) {
    if (!$date || $date == '0000-00-00') return 'Not set';
    return date('M j, Y', strtotime($date));
}

// Calculate monthly rent (use contract rent if available, otherwise stall price)
$monthly_rent = $application['contract_rent'] ?: $application['stall_price'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approved - Municipal Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../navbar.css">
    <style>
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .status-approved {
            background-color: #dbeafe;
            color: #1e40af;
            border: 2px solid #93c5fd;
        }
        .info-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        .file-display {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            background: #f9fafb;
        }
        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            color: #1f2937;
        }
        .file-type {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-view {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-view:hover {
            background: #2563eb;
        }
        .btn-download {
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-download:hover {
            background: #059669;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
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
        }
        .modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .modal-header h3 {
            font-weight: 600;
            font-size: 1.25rem;
            color: #1f2937;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-close:hover {
            color: #374151;
        }
        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }
        .document-viewer {
            margin-bottom: 1.5rem;
        }
        .document-info {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        .stall-class-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .stall-class-A {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .stall-class-B {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .stall-class-C {
            background-color: #e0e7ff;
            color: #3730a3;
            border: 1px solid #6366f1;
        }
        .success-section {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .info-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        .renter-status-active {
            background: #d1fae5;
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .contract-details {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        .payment-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        .rent-notification {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 2px solid #fbbf24;
        }
        .btn-pay-rent {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.3);
        }
        .btn-pay-rent:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.4);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../../citizen_portal/navbar.php'; ?>

    <div class="container mx-auto px-6 py-8 max-w-6xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Application Approved</h1>
            <p class="text-gray-600">Application ID: #<?= $application['id'] ?></p>
        </div>

        <!-- Success Banner -->
        <div class="success-section">
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">✅</span>
            </div>
            <h2 class="text-2xl font-bold mb-2">Congratulations! Your Application is Approved</h2>
            <p class="text-white/90 text-lg">Your market stall application has been approved. You can now proceed with the payment process.</p>
        </div>

        <!-- Monthly Rent Payment Notification -->
        <div class="rent-notification">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex-1 text-center md:text-left">
                    <h3 class="text-xl font-bold mb-2">💰 Ready to Pay Monthly Rent</h3>
                    <p class="text-white/90 mb-2">Your stall is ready for operation! Start paying your monthly rent to begin your business.</p>
                    <div class="flex items-center justify-center md:justify-start gap-4 mt-3">
                        <div class="text-center">
                            <p class="text-sm text-white/80">Monthly Rent</p>
                            <p class="text-2xl font-bold">₱<?= number_format($monthly_rent, 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-white/80">Due Date</p>
                            <p class="text-lg font-semibold">5th of each month</p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <button onclick="location.href='pay_rent.php?application_id=<?= $application_id ?>'" 
                            class="btn-pay-rent whitespace-nowrap">
                        💳 Pay Monthly Rent
                    </button>
                    <p class="text-xs text-white/80 text-center">Secure payment • Instant confirmation</p>
                </div>
            </div>
        </div>

        <!-- Status Card -->
        <div class="info-card">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex-1">
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">
                        📋 Application Status: Approved
                    </h2>
                    <p class="text-gray-700">
                        Your application has been successfully approved! You are now eligible to proceed with the payment process to secure your market stall.
                    </p>
                </div>
                <div class="status-badge status-approved">
                    Approved
                </div>
            </div>
        </div>

        <!-- Renter Information -->
        <div class="info-card">
            <h3 class="text-xl font-bold text-gray-800 mb-4">👤 Renter Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <h4 class="font-semibold text-gray-700 mb-3">Personal Details</h4>
                    <div class="space-y-2">
                        <p><span class="text-gray-600">Name:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['first_name'] . ' ' . ($application['middle_name'] ? $application['middle_name'] . ' ' : '') . $application['last_name']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Contact:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['contact_number']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Email:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['email']) ?></span>
                        </p>
                        <?php if ($application['renter_id']): ?>
                        <p><span class="text-gray-600">Renter ID:</span> 
                           <span class="font-semibold text-blue-600"><?= htmlspecialchars($application['renter_id']) ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <h4 class="font-semibold text-gray-700 mb-3">Business Details</h4>
                    <div class="space-y-2">
                        <p><span class="text-gray-600">Business Name:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['business_name']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Application Type:</span> 
                           <span class="font-semibold"><?= ucfirst($application['application_type']) ?></span>
                        </p>
                        <?php if ($application['renter_status']): ?>
                        <p><span class="text-gray-600">Renter Status:</span> 
                           <span class="renter-status-active"><?= ucfirst($application['renter_status']) ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <h4 class="font-semibold text-gray-700 mb-3">Address Information</h4>
                    <div class="space-y-2">
                        <p><span class="text-gray-600">Address:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['house_number'] . ' ' . $application['street']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Barangay:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['barangay']) ?></span>
                        </p>
                        <p><span class="text-gray-600">City:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['city']) ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stall Information -->
        <div class="info-card">
            <h3 class="text-xl font-bold text-gray-800 mb-4">🏪 Stall Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <h4 class="font-semibold text-gray-700 mb-3">Stall Details</h4>
                    <div class="space-y-2">
                        <p><span class="text-gray-600">Stall Number:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['stall_name']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Market:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['market_name']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Section:</span> 
                           <span class="font-semibold"><?= htmlspecialchars($application['section_name']) ?></span>
                        </p>
                        <p><span class="text-gray-600">Stall Status:</span> 
                           <span class="font-semibold <?= $application['stall_status'] === 'occupied' ? 'text-green-600' : 'text-yellow-600' ?>">
                               <?= ucfirst($application['stall_status']) ?>
                           </span>
                        </p>
                    </div>
                </div>

                <div class="info-item">
                    <h4 class="font-semibold text-gray-700 mb-3">Dimensions & Pricing</h4>
                    <div class="space-y-2">
                        <p><span class="text-gray-600">Dimensions:</span> 
                           <span class="font-semibold">
                               <?= $application['length'] ?>m × <?= $application['width'] ?>m × <?= $application['height'] ?>m
                           </span>
                        </p>
                        <p><span class="text-gray-600">Area:</span> 
                           <span class="font-semibold">
                               <?= number_format($application['length'] * $application['width'], 2) ?> sqm
                           </span>
                        </p>
                        <p><span class="text-gray-600">Monthly Rent:</span> 
                           <span class="font-semibold text-green-600">₱<?= number_format($monthly_rent, 2) ?></span>
                        </p>
                    </div>
                </div>

                <div class="info-item">
                    <h4 class="font-semibold text-gray-700 mb-3">Stall Rights</h4>
                    <div class="space-y-2">
                        <p><span class="text-gray-600">Class:</span> 
                           <span class="stall-class-badge stall-class-<?= $application['class_name'] ?>">
                               Class <?= $application['class_name'] ?>
                           </span>
                        </p>
                        <p><span class="text-gray-600">Rights Fee:</span> 
                           <span class="font-semibold text-purple-600">₱<?= number_format($application['stall_rights_price'], 2) ?></span>
                        </p>
                        <?php if ($application['certificate_number']): ?>
                        <p><span class="text-gray-600">Certificate No:</span> 
                           <span class="font-semibold"><?= $application['certificate_number'] ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <?php if ($application['payment_date']): ?>
            <div class="payment-info">
                <h4 class="font-semibold text-green-800 mb-3">💰 Payment Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-green-600">Payment Reference</p>
                        <p class="font-semibold"><?= $application['payment_reference'] ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-green-600">Payment Date</p>
                        <p class="font-semibold"><?= formatDate($application['payment_date']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-green-600">Total Amount</p>
                        <p class="font-semibold text-green-600">₱<?= number_format($application['total_amount'], 2) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Contract Information -->
            <?php if ($application['contract_number']): ?>
            <div class="contract-details">
                <h4 class="font-semibold text-blue-800 mb-3">📝 Lease Contract Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-blue-600">Contract Number</p>
                        <p class="font-semibold"><?= $application['contract_number'] ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-600">Lease Period</p>
                        <p class="font-semibold"><?= formatDate($application['contract_start']) ?> to <?= formatDate($application['contract_end']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-600">Monthly Rent</p>
                        <p class="font-semibold text-green-600">₱<?= number_format($monthly_rent, 2) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
            <h3 class="text-lg font-bold text-blue-800 mb-3">Next Steps</h3>
            <ul class="list-disc list-inside text-blue-700 space-y-2">
                <li><strong>Pay Monthly Rent:</strong> Start paying your monthly rent of ₱<?= number_format($monthly_rent, 2) ?> to operate your stall</li>
                <li><strong>Payment Schedule:</strong> Rent is due on the 5th of each month</li>
                <li><strong>Stall Setup:</strong> Prepare your stall for business operation</li>
                <li><strong>Business Operations:</strong> Begin your market business activities</li>
                <li><strong>Market Orientation:</strong> Attend orientation session (if required)</li>
            </ul>
            
            <div class="mt-4 text-center">
                <button onclick="location.href='pay_rent.php?application_id=<?= $application_id ?>'" 
                        class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 px-8 rounded-lg transition-colors duration-200 text-lg">
                    💰 Pay Monthly Rent Now
                </button>
                <p class="text-sm text-gray-600 mt-2">Secure payment • Due on 5th of each month</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <button onclick="location.href='../market-dashboard.php'" 
                    class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 w-full sm:w-auto">
                ← Back to Dashboard
            </button>
            <div class="flex gap-3 w-full sm:w-auto">
                <button onclick="window.print()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 w-full sm:w-auto">
                    Print Summary
                </button>
                <button onclick="location.href='pay_rent.php?application_id=<?= $application_id ?>'" 
                        class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200 w-full sm:w-auto">
                    💰 Pay Rent
                </button>
            </div>
        </div>
    </div>

    <script>
        function openDocumentModal(fileUrl, fileExtension, fileName, documentType) {
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
            documentTypeSpan.textContent = documentType;
            
            // Hide all viewers first
            modalImage.style.display = 'none';
            modalPdf.style.display = 'none';
            unsupportedFile.style.display = 'none';
            
            // Check file type and show appropriate viewer
            const extension = fileExtension.toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(extension)) {
                // Image file
                modalImage.src = fileUrl;
                modalImage.style.display = 'block';
                modalImage.alt = fileName;
            } else if (extension === 'pdf') {
                // PDF file
                modalPdf.src = fileUrl;
                modalPdf.style.display = 'block';
            } else {
                // Unsupported file type
                downloadLink.href = fileUrl;
                downloadLink.download = fileName;
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
            
            // Clear modal content
            document.getElementById('modalImage').src = '';
            document.getElementById('modalPdf').src = '';
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