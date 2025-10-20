<?php
/**
 * Assessments API Endpoint
 * GET /api/business/assessments.php
 * 
 * Parameters:
 * - business_id: filter by business ID (optional)
 * - year: filter by assessment year (optional)
 * - status: filter by assessment status (optional)
 * - page: page number (default: 1)
 * - per_page: items per page (default: 10)
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

try {
    $db = Database::getInstance();
    
    // Get parameters
    $businessId = $_GET['business_id'] ?? '';
    $year = $_GET['year'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(100, intval($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;
    
    // Build base query
    $baseQuery = "FROM assessments a
                  INNER JOIN businesses b ON a.business_id = b.id
                  INNER JOIN owners o ON b.owner_id = o.id";
    
    $whereConditions = [];
    $params = [];
    
    // Add business ID filter
    if (!empty($businessId)) {
        $whereConditions[] = "a.business_id = ?";
        $params[] = intval($businessId);
    }
    
    // Add year filter
    if (!empty($year)) {
        $whereConditions[] = "a.assessment_year = ?";
        $params[] = intval($year);
    }
    
    // Add status filter
    if (!empty($status)) {
        $whereConditions[] = "a.status = ?";
        $params[] = $status;
    }
    
    // Build WHERE clause
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total $baseQuery $whereClause";
    $countResult = $db->prepare($countQuery, $params);
    $totalRecords = $countResult->fetch()['total'];
    
    // Get assessments with pagination
    $query = "SELECT 
                a.id as assessment_id,
                a.business_id,
                a.year,
                a.gross_sales,
                a.tax_amount,
                a.fees_total,
                a.discounts,
                a.penalties,
                a.total_due,
                a.status,
                a.created_at,
                b.business_name,
                b.barangay,
                b.business_type,
                o.full_name as owner_name
              $baseQuery 
              $whereClause 
              ORDER BY a.year DESC, a.created_at DESC 
              LIMIT $perPage OFFSET $offset";
    
    $result = $db->prepare($query, $params);
    $assessments = $result->fetchAll();
    
    // Get assessment items for each assessment
    foreach ($assessments as &$assessment) {
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
        
        $itemsResult = $db->prepare($itemsQuery, [$assessment['assessment_id']]);
        $assessment['items'] = $itemsResult->fetchAll();
        
        // Get payment information
        $paymentQuery = "SELECT 
                           SUM(amount_paid) as total_paid,
                           COUNT(*) as payment_count,
                           MAX(paid_at) as last_payment
                         FROM payments 
                         WHERE assessment_id = ?";
        
        $paymentResult = $db->prepare($paymentQuery, [$assessment['assessment_id']]);
        $paymentInfo = $paymentResult->fetch();
        
        $assessment['payment_info'] = [
            'total_paid' => floatval($paymentInfo['total_paid'] ?? 0),
            'payment_count' => intval($paymentInfo['payment_count'] ?? 0),
            'last_payment' => $paymentInfo['last_payment'],
            'balance' => $assessment['total_due'] - floatval($paymentInfo['total_paid'] ?? 0)
        ];
    }
    
    // Calculate pagination info
    $totalPages = ceil($totalRecords / $perPage);
    
    $response = [
        'assessments' => $assessments,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
