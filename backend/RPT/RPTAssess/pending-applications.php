<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection - adjust path based on your structure
require_once '../../../db/RPT/rpt_db.php';

try {
    $stmt = $pdo->prepare("
        SELECT * FROM rpt_applications 
        WHERE status = 'pending'
        ORDER BY application_date DESC
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => $applications
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>