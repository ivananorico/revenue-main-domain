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

    // Get land assessment data
    $land_stmt = $pdo->prepare("
        SELECT l.*, lat.* 
        FROM land l 
        LEFT JOIN land_assessment_tax lat ON l.land_id = lat.land_id 
        WHERE l.application_id = ?
    ");
    $land_stmt->execute([$application_id]);
    $land_data = $land_stmt->fetch();

    // Get building assessment data
    $building_stmt = $pdo->prepare("
        SELECT b.*, bat.* 
        FROM building b 
        LEFT JOIN building_assessment_tax bat ON b.building_id = bat.building_id 
        WHERE b.application_id = ?
    ");
    $building_stmt->execute([$application_id]);
    $building_data = $building_stmt->fetch();

    // Get quarterly payments
    $quarterly_stmt = $pdo->prepare("
        SELECT q.* 
        FROM quarterly q 
        JOIN land_assessment_tax lat ON q.land_tax_id = lat.land_tax_id 
        JOIN land l ON lat.land_id = l.land_id 
        WHERE l.application_id = ?
        ORDER BY q.quarter_no
    ");
    $quarterly_stmt->execute([$application_id]);
    $quarterly_data = $quarterly_stmt->fetchAll();

    // Get total tax data
    $total_tax_stmt = $pdo->prepare("
        SELECT tt.* 
        FROM total_tax tt 
        JOIN land_assessment_tax lat ON tt.land_tax_id = lat.land_tax_id 
        JOIN land l ON lat.land_id = l.land_id 
        WHERE l.application_id = ?
    ");
    $total_tax_stmt->execute([$application_id]);
    $total_tax_data = $total_tax_stmt->fetch();

    echo json_encode([
        "status" => "success",
        "data" => [
            "application" => $application,
            "documents" => $documents,
            "land_assessment" => $land_data,
            "building_assessment" => $building_data,
            "quarterly_payments" => $quarterly_data,
            "total_tax" => $total_tax_data
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>