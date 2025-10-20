<?php
/**
 * Citizen Payment History API
 * GET: Returns payment history for an assessment
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized', 401);
}

try {
    $db = Database::getInstance();
    $userId = getCurrentUserId();
    $assessmentId = intval($_GET['assessment_id'] ?? 0);
    
    if (!$assessmentId) {
        sendError('Assessment ID is required', 400);
    }
    
    // Verify assessment belongs to user
    $stmt = $db->prepare("
        SELECT a.id FROM assessments a
        LEFT JOIN businesses b ON a.business_id = b.id
        WHERE a.id = ? AND b.owner_id = ?
    ");
    $stmt->execute([$assessmentId, $userId]);
    if (!$stmt->fetch()) {
        sendError('Assessment not found or access denied', 404);
    }
    
    // Get payment history
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.amount_paid,
            p.payment_method,
            p.or_number,
            p.paid_at,
            u.full_name as paid_by_name
        FROM payments p
        LEFT JOIN users u ON p.paid_by = u.id
        WHERE p.assessment_id = ?
        ORDER BY p.paid_at DESC
    ");
    
    $stmt->execute([$assessmentId]);
    $payments = $stmt->fetchAll();
    
    // Format payments
    $formattedPayments = array_map(function($payment) {
        return [
            'id' => $payment['id'],
            'amount_paid' => floatval($payment['amount_paid']),
            'payment_method' => $payment['payment_method'],
            'or_number' => $payment['or_number'],
            'paid_by_name' => $payment['paid_by_name'],
            'paid_at' => $payment['paid_at']
        ];
    }, $payments);
    
    sendSuccess([
        'payments' => $formattedPayments,
        'total' => count($formattedPayments)
    ]);
    
} catch (Exception $e) {
    sendError('Failed to load payment history: ' . $e->getMessage(), 500);
}
?>

