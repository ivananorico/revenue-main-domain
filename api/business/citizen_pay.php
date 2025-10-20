<?php
/**
 * Citizen Pay API
 * POST: Submit payment info and mark assessment as paid
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized', 401);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

try {
    $db = Database::getInstance();
    $userId = getCurrentUserId();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['assessment_id', 'amount_paid', 'payment_method', 'or_number'];
    if (!validateRequired($input, $required)) {
        sendError('Missing required fields', 400);
    }
    
    $assessmentId = intval($input['assessment_id']);
    $amountPaid = floatval($input['amount_paid']);
    $paymentMethod = sanitizeInput($input['payment_method']);
    $orNumber = sanitizeInput($input['or_number']);
    $paymentNotes = sanitizeInput($input['payment_notes'] ?? '');
    
    // Verify assessment exists and belongs to user
    $stmt = $db->prepare("
        SELECT a.id, a.total_due, a.status, b.owner_id, b.business_name
        FROM assessments a
        LEFT JOIN businesses b ON a.business_id = b.id
        WHERE a.id = ? AND b.owner_id = ?
    ");
    $stmt->execute([$assessmentId, $userId]);
    $assessment = $stmt->fetch();
    
    if (!$assessment) {
        sendError('Assessment not found or access denied', 404);
    }
    
    if ($assessment['status'] === 'paid') {
        sendError('Assessment is already paid', 400);
    }
    
    // Validate payment amount
    if ($amountPaid < $assessment['total_due']) {
        sendError('Payment amount is less than total due', 400);
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Insert payment record
        $stmt = $db->prepare("
            INSERT INTO payments (assessment_id, amount_paid, payment_method, or_number, paid_by, paid_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $assessmentId,
            $amountPaid,
            $paymentMethod,
            $orNumber,
            $userId
        ]);
        
        $paymentId = $db->lastInsertId();
        
        // Update assessment status
        $stmt = $db->prepare("UPDATE assessments SET status = 'paid' WHERE id = ?");
        $stmt->execute([$assessmentId]);
        
        // Create permit if payment is complete
        $stmt = $db->prepare("
            INSERT INTO permits (assessment_id, permit_number, issued_at, expires_at, status, generated_by) 
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'issued', ?)
        ");
        $permitNumber = 'PERMIT-' . str_pad($assessmentId, 6, '0', STR_PAD_LEFT);
        $stmt->execute([$assessmentId, $permitNumber, $userId]);
        
        // Create notification
        $stmt = $db->prepare("
            INSERT INTO notifications (business_id, type, message, is_read, created_at) 
            VALUES (?, 'payment_received', ?, 0, NOW())
        ");
        $businessId = $db->prepare("SELECT business_id FROM assessments WHERE id = ?");
        $businessId->execute([$assessmentId]);
        $businessId = $businessId->fetchColumn();
        
        $stmt->execute([
            $businessId,
            "Payment received for assessment. Your business permit is now ready for download."
        ]);
        
        // Log audit
        logAudit('payment_processed', 'payments', $paymentId, null, [
            'assessment_id' => $assessmentId,
            'amount_paid' => $amountPaid,
            'payment_method' => $paymentMethod,
            'or_number' => $orNumber
        ]);
        
        $db->commit();
        
        sendSuccess([
            'payment_id' => $paymentId,
            'or_number' => $orNumber,
            'permit_number' => $permitNumber,
            'business_name' => $assessment['business_name']
        ], 'Payment processed successfully');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendError('Payment processing failed: ' . $e->getMessage(), 500);
}
?>

