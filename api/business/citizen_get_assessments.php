<?php
/**
 * Citizen Get Assessments API
 * GET: Returns assessments for a business
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized', 401);
}

try {
    $db = Database::getInstance();
    $userId = getCurrentUserId();
    $businessId = intval($_GET['business_id'] ?? 0);
    
    if (!$businessId) {
        sendError('Business ID is required', 400);
    }
    
    // Verify business ownership
    $stmt = $db->prepare("SELECT id FROM businesses WHERE id = ? AND owner_id = ?");
    $stmt->execute([$businessId, $userId]);
    if (!$stmt->fetch()) {
        sendError('Business not found or access denied', 404);
    }
    
    // Get assessments for the business
    $stmt = $db->prepare("
        SELECT 
            a.id as assessment_id,
            a.year,
            a.gross_sales,
            a.tax_amount,
            a.fees_total,
            a.discounts,
            a.penalties,
            a.total_due,
            a.status,
            a.assessed_by,
            a.created_at,
            u.full_name as assessed_by_name,
            b.business_name
        FROM assessments a
        LEFT JOIN users u ON a.assessed_by = u.id
        LEFT JOIN businesses b ON a.business_id = b.id
        WHERE a.business_id = ?
        ORDER BY a.year DESC, a.created_at DESC
    ");
    
    $stmt->execute([$businessId]);
    $assessments = $stmt->fetchAll();
    
    // Format the data
    $formattedAssessments = array_map(function($assessment) {
        return [
            'id' => $assessment['assessment_id'],
            'year' => $assessment['year'],
            'gross_sales' => floatval($assessment['gross_sales']),
            'tax_amount' => floatval($assessment['tax_amount']),
            'fees_total' => floatval($assessment['fees_total']),
            'discounts' => floatval($assessment['discounts']),
            'penalties' => floatval($assessment['penalties']),
            'total_due' => floatval($assessment['total_due']),
            'status' => $assessment['status'],
            'assessed_by' => $assessment['assessed_by'],
            'assessed_by_name' => $assessment['assessed_by_name'],
            'business_name' => $assessment['business_name'],
            'created_at' => $assessment['created_at']
        ];
    }, $assessments);
    
    sendSuccess([
        'assessments' => $formattedAssessments,
        'total' => count($formattedAssessments)
    ]);
    
} catch (Exception $e) {
    sendError('Failed to load assessments: ' . $e->getMessage(), 500);
}
?>

