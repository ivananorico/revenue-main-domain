<?php
session_start();
require_once '../../db/Market/market_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$application_id = $_POST['application_id'] ?? null;
$upload_type = $_POST['upload_type'] ?? null;

if (!$application_id || !$upload_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or an error occurred.']);
    exit;
}

$file = $_FILES['document_file'];
$allowedExtensions = ['jpg','jpeg','png','pdf','doc','docx'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit;
}

// Prepare upload folder
$uploadDir = __DIR__ . '/../../market_portal/uploads/applications/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Generate unique file name
$newName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/','_', $file['name']);
$destination = $uploadDir . $newName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    $dbFilePath = 'uploads/applications/' . $newName;

    // Insert or update document in DB
    try {
        // Check if document of this type already exists for this application
        $stmtCheck = $pdo->prepare("SELECT id FROM documents WHERE application_id = ? AND document_type = ?");
        $stmtCheck->execute([$application_id, $upload_type]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE documents SET file_name = ?, file_path = ?, file_size = ?, file_extension = ?, uploaded_at = NOW() WHERE id = ?");
            $stmt->execute([$file['name'], $dbFilePath, $file['size'], $ext, $existing['id']]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO documents (application_id, document_type, file_name, file_path, file_size, file_extension, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$application_id, $upload_type, $file['name'], $dbFilePath, $file['size'], $ext]);
        }

        // Check if both lease_contract and business_permit are uploaded
        $stmtDocs = $pdo->prepare("
            SELECT COUNT(DISTINCT document_type) as doc_count
            FROM documents
            WHERE application_id = ?
              AND document_type IN ('lease_contract', 'business_permit')
        ");
        $stmtDocs->execute([$application_id]);
        $row = $stmtDocs->fetch(PDO::FETCH_ASSOC);

        if ($row['doc_count'] == 2) {
            // Both documents exist, update application status
            $stmtStatus = $pdo->prepare("UPDATE applications SET status = 'documents_submitted' WHERE id = ?");
            $stmtStatus->execute([$application_id]);
        }

        echo json_encode([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $upload_type)) . ' uploaded successfully.'
        ]);

    } catch (PDOException $e) {
        error_log("DB error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
}
?>
