<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../../db/Market/market_db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON input');

    $application_id = $input['application_id'] ?? null;
    if (!$application_id) throw new Exception('Application ID is required');

    // Fetch application with stall details
    $stmt = $pdo->prepare("
        SELECT a.status, a.stall_id, s.class_id, s.price as monthly_rent
        FROM applications a 
        JOIN stalls s ON a.stall_id = s.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) throw new Exception('Application not found');

    // Validate current status - allow from 'pending' to move to 'payment_phase'
    if ($application['status'] !== 'pending') {
        throw new Exception("Cannot move application to Payment Phase from current status: {$application['status']}. Application must be in 'pending' status.");
    }

    // Start transaction
    $pdo->beginTransaction();

    // Update application status to payment_phase
    $update = $pdo->prepare("UPDATE applications SET status = 'payment_phase', updated_at = NOW() WHERE id = ?");
    $update->execute([$application_id]);

    // Get stall rights fee from stall_rights table
    $rights_stmt = $pdo->prepare("SELECT price FROM stall_rights WHERE class_id = ?");
    $rights_stmt->execute([$application['class_id']]);
    $stall_rights = $rights_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stall_rights) throw new Exception('Stall class pricing not found');

    // Calculate fees based on your business rules
    $application_fee = 100.00; // Fixed application fee
    $security_bond = 10000.00; // Fixed security bond
    $stall_rights_fee = $stall_rights['price'] ?? 0;
    $total_amount = $application_fee + $security_bond + $stall_rights_fee;

    // Check if fee record already exists
    $check_fee_stmt = $pdo->prepare("SELECT id FROM application_fee WHERE application_id = ?");
    $check_fee_stmt->execute([$application_id]);
    $existing_fee = $check_fee_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_fee) {
        // Update existing fee record
        $fee_stmt = $pdo->prepare("
            UPDATE application_fee 
            SET application_fee = ?, security_bond = ?, stall_rights_fee = ?, total_amount = ?, 
                status = 'pending', updated_at = NOW()
            WHERE application_id = ?
        ");
        $fee_stmt->execute([$application_fee, $security_bond, $stall_rights_fee, $total_amount, $application_id]);
    } else {
        // Insert new fee record
        $fee_stmt = $pdo->prepare("
            INSERT INTO application_fee 
            (application_id, application_fee, security_bond, stall_rights_fee, total_amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $fee_stmt->execute([$application_id, $application_fee, $security_bond, $stall_rights_fee, $total_amount]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Application moved to Payment Phase and fees generated successfully',
        'application_id' => $application_id,
        'fees' => [
            'application_fee' => number_format($application_fee, 2),
            'security_bond' => number_format($security_bond, 2),
            'stall_rights_fee' => number_format($stall_rights_fee, 2),
            'total_amount' => number_format($total_amount, 2),
            'monthly_rent' => number_format($application['monthly_rent'], 2)
        ],
        'currency' => 'PHP'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'payment_phase_error'
    ], JSON_PRETTY_PRINT);
}
?>