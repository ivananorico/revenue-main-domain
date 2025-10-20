<?php
/**
 * Regulatory Fees API Endpoint
 * GET /api/business/fees.php
 * 
 * Returns list of all regulatory fees
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

try {
    $db = Database::getInstance();
    
    // Get all regulatory fees (no filtering by business type)
    $query = "SELECT id, fee_code, fee_name, amount, department, business_types 
              FROM regulatory_fees 
              ORDER BY department, fee_name";
    
    $result = $db->prepare($query);
    $fees = $result->fetchAll();
    
    // Group fees by department for better organization
    $groupedFees = [];
    foreach ($fees as $fee) {
        $department = $fee['department'];
        if (!isset($groupedFees[$department])) {
            $groupedFees[$department] = [];
        }
        
        $groupedFees[$department][] = [
            'fee_id' => intval($fee['id']),
            'fee_code' => $fee['fee_code'],
            'fee_name' => $fee['fee_name'],
            'fee_amount' => floatval($fee['amount']),
            'department' => $fee['department'],
            'business_types' => $fee['business_types'] ? explode(',', $fee['business_types']) : []
        ];
    }
    
    sendSuccess([
        'fees_by_department' => $groupedFees,
        'all_fees' => $fees
    ]);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
