<?php
/**
 * Citizen Get Bill API
 * GET: Returns single assessment/bill details for display/print
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
    $includeBreakdown = isset($_GET['breakdown']) && $_GET['breakdown'] === 'true';
    
    if (!$assessmentId) {
        sendError('Assessment ID is required', 400);
    }
    
    // Get assessment details with business info
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
            b.id as business_id,
            b.business_name,
            b.tin,
            b.business_type,
            b.address,
            b.barangay,
            o.full_name as owner_name,
            o.contact_number,
            o.email
        FROM assessments a
        LEFT JOIN users u ON a.assessed_by = u.id
        LEFT JOIN businesses b ON a.business_id = b.id
        LEFT JOIN owners o ON b.owner_id = o.id
        WHERE a.id = ? AND b.owner_id = ?
    ");
    
    $stmt->execute([$assessmentId, $userId]);
    $assessment = $stmt->fetch();
    
    if (!$assessment) {
        sendError('Assessment not found or access denied', 404);
    }
    
    // Format assessment data
    $assessmentData = [
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
        'business_id' => $assessment['business_id'],
        'business_name' => $assessment['business_name'],
        'tin' => $assessment['tin'],
        'business_type' => $assessment['business_type'],
        'address' => $assessment['address'],
        'barangay' => $assessment['barangay'],
        'owner_name' => $assessment['owner_name'],
        'contact_number' => $assessment['contact_number'],
        'email' => $assessment['email'],
        'created_at' => $assessment['created_at']
    ];
    
    $response = ['assessment' => $assessmentData];
    
    // Include breakdown if requested
    if ($includeBreakdown) {
        $stmt = $db->prepare("
            SELECT 
                ai.fee_name,
                ai.amount,
                rf.department
            FROM assessment_items ai
            LEFT JOIN regulatory_fees rf ON ai.fee_id = rf.id
            WHERE ai.assessment_id = ?
            ORDER BY ai.id
        ");
        
        $stmt->execute([$assessmentId]);
        $breakdown = $stmt->fetchAll();
        
        $response['breakdown'] = array_map(function($item) {
            return [
                'fee_name' => $item['fee_name'],
                'amount' => floatval($item['amount']),
                'department' => $item['department']
            ];
        }, $breakdown);
    }
    
    sendSuccess($response);
    
} catch (Exception $e) {
    sendError('Failed to load bill details: ' . $e->getMessage(), 500);
}
?>

