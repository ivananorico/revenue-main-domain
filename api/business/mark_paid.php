<?php
/**
 * Mark as Paid API Endpoint
 * POST /api/business/mark_paid.php
 * 
 * Payload:
 * {
 *   "assessment_id": int,
 *   "amount_paid": float,
 *   "or_number": string,
 *   "payment_method": string,
 *   "notes": string (optional)
 * }
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON input');
}

// Validate required fields
$requiredFields = ['assessment_id', 'amount_paid', 'or_number', 'payment_method'];
if (!validateRequired($input, $requiredFields)) {
    sendError('Missing required fields');
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();
    
    $assessmentId = intval($input['assessment_id']);
    $amountPaid = floatval($input['amount_paid']);
    $orNumber = sanitizeInput($input['or_number']);
    $paymentMethod = sanitizeInput($input['payment_method']);
    $notes = sanitizeInput($input['notes'] ?? '');
    $receivedBy = getCurrentUserId();
    
    // Validate payment method
    $validMethods = ['Cash', 'Check', 'Bank Transfer', 'Online'];
    if (!in_array($paymentMethod, $validMethods)) {
        sendError('Invalid payment method');
    }
    
    // Get assessment details
    $assessmentQuery = "SELECT * FROM assessments WHERE id = ?";
    $assessmentResult = $db->prepare($assessmentQuery, [$assessmentId]);
    $assessment = $assessmentResult->fetch();
    
    if (!$assessment) {
        sendError('Assessment not found', 404);
    }
    
    // Check if OR number already exists
    $orCheckQuery = "SELECT id FROM payments WHERE or_number = ?";
    $orCheckResult = $db->prepare($orCheckQuery, [$orNumber]);
    if ($orCheckResult->fetch()) {
        sendError('OR Number already exists');
    }
    
    // Insert payment record
    $paymentQuery = "INSERT INTO payments 
                     (assessment_id, amount_paid, payment_method, or_number, paid_by) 
                     VALUES (?, ?, ?, ?, ?)";
    
    $db->prepare($paymentQuery, [
        $assessmentId, $amountPaid, $paymentMethod, $orNumber, $receivedBy
    ]);
    
    $paymentId = $db->lastInsertId();
    
    // Calculate total paid for this assessment
    $totalPaidQuery = "SELECT SUM(amount_paid) as total_paid FROM payments WHERE assessment_id = ?";
    $totalPaidResult = $db->prepare($totalPaidQuery, [$assessmentId]);
    $totalPaid = floatval($totalPaidResult->fetch()['total_paid']);
    
    // Update assessment status based on payment
    $newStatus = 'paid';
    if ($totalPaid < $assessment['total_due']) {
        $newStatus = 'assessed'; // Partial payment
    }
    
    $updateAssessmentQuery = "UPDATE assessments SET status = ?, updated_at = NOW() WHERE id = ?";
    $db->prepare($updateAssessmentQuery, [$newStatus, $assessmentId]);
    
    // Log audit trail
    logAudit('CREATE', 'payments', $paymentId, null, $input);
    logAudit('UPDATE', 'assessments', $assessmentId, ['status' => $assessment['status']], ['status' => $newStatus]);
    
    $db->commit();
    
    sendSuccess([
        'payment_id' => $paymentId,
        'new_status' => $newStatus,
        'total_paid' => $totalPaid,
        'balance' => $assessment['total_due'] - $totalPaid,
        'message' => 'Payment recorded successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
