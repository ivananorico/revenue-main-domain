<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../db/RPT/rpt_db.php';

if (!isset($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Application ID is required"
    ]);
    exit;
}

$application_id = $_GET['id'];

try {
    // Get application details
    $stmt = $pdo->prepare("SELECT * FROM rpt_applications WHERE id = ?");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch();

    if (!$application) {
        echo json_encode([
            "status" => "error",
            "message" => "Application not found"
        ]);
        exit;
    }

    // Get documents for this application
    $doc_stmt = $pdo->prepare("SELECT * FROM rpt_documents WHERE application_id = ?");
    $doc_stmt->execute([$application_id]);
    $documents = $doc_stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => [
            "application" => $application,
            "documents" => $documents
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>