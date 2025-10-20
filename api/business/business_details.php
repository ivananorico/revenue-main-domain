<?php
/**
 * Business Details API Endpoint
 * GET /api/business/business_details.php
 * 
 * Parameters:
 * - business_id: ID of the business to get details for
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

// Validate required parameter
if (!isset($_GET['business_id']) || empty($_GET['business_id'])) {
    sendError('Business ID is required');
}

$businessId = intval($_GET['business_id']);

try {
    $db = Database::getInstance();
    
    // Get business details with owner information
    $query = "SELECT 
                b.id as business_id,
                b.business_name,
                b.tin,
                b.business_type,
                b.barangay,
                b.address,
                b.capital,
                b.last_year_gross,
                b.status,
                b.created_at,
                o.id as owner_id,
                o.full_name,
                o.email,
                o.contact_number,
                o.barangay as owner_barangay
              FROM businesses b
              INNER JOIN owners o ON b.owner_id = o.id
              WHERE b.id = ?";
    
    $result = $db->prepare($query, [$businessId]);
    $business = $result->fetch();
    
    if (!$business) {
        sendError('Business not found', 404);
    }
    
    // Get assessment history for this business
    $assessmentsQuery = "SELECT 
                           a.id as assessment_id,
                           a.year,
                           a.gross_sales,
                           a.tax_amount,
                           a.fees_total,
                           a.discounts,
                           a.penalties,
                           a.total_due,
                           a.status,
                           a.created_at,
                           u.full_name as assessed_by_name
                         FROM assessments a
                         LEFT JOIN users u ON a.assessed_by = u.id
                         WHERE a.business_id = ?
                         ORDER BY a.year DESC";
    
    $assessmentsResult = $db->prepare($assessmentsQuery, [$businessId]);
    $assessments = $assessmentsResult->fetchAll();
    
    // Get current year assessment if exists
    $currentYear = date('Y');
    $currentAssessment = null;
    foreach ($assessments as $assessment) {
        if ($assessment['year'] == $currentYear) {
            $currentAssessment = $assessment;
            break;
        }
    }
    
    // Get assessment items for current assessment if exists
    $assessmentItems = [];
    if ($currentAssessment) {
        $itemsQuery = "SELECT 
                         ai.id as item_id,
                         ai.fee_id,
                         ai.fee_name,
                         ai.amount,
                         rf.fee_code,
                         rf.department
                       FROM assessment_items ai
                       LEFT JOIN regulatory_fees rf ON ai.fee_id = rf.id
                       WHERE ai.assessment_id = ?
                       ORDER BY rf.department, ai.fee_name";
        
        $itemsResult = $db->prepare($itemsQuery, [$currentAssessment['assessment_id']]);
        $assessmentItems = $itemsResult->fetchAll();
    }
    
    // Get payment history
    $paymentsQuery = "SELECT 
                        p.id as payment_id,
                        p.amount_paid,
                        p.payment_method,
                        p.or_number,
                        p.paid_at,
                        u.full_name as paid_by_name,
                        a.year
                      FROM payments p
                      LEFT JOIN users u ON p.paid_by = u.id
                      INNER JOIN assessments a ON p.assessment_id = a.id
                      WHERE a.business_id = ?
                      ORDER BY p.paid_at DESC";
    
    $paymentsResult = $db->prepare($paymentsQuery, [$businessId]);
    $payments = $paymentsResult->fetchAll();
    
    $response = [
        'business' => $business,
        'current_assessment' => $currentAssessment,
        'assessment_items' => $assessmentItems,
        'assessment_history' => $assessments,
        'payment_history' => $payments
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
