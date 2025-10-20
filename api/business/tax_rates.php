<?php
/**
 * Tax Rates API Endpoint
 * GET /api/business/tax_rates.php
 * 
 * Returns current tax rates for all business categories
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

try {
    $db = Database::getInstance();
    
    // Get current year tax rates
    $currentYear = date('Y');
    $query = "SELECT category, rate_percent, ordinance_ref, effective_from 
              FROM tax_rates 
              WHERE effective_from IS NULL OR effective_from <= CURDATE()
              ORDER BY category";
    
    $result = $db->prepare($query);
    $rates = $result->fetchAll();
    
    // Format rates as key-value pairs for easy lookup
    $rateMap = [];
    foreach ($rates as $rate) {
        $rateMap[$rate['category']] = [
            'percentage' => floatval($rate['rate_percent']) * 100, // Convert decimal to percentage
            'rate_percent' => floatval($rate['rate_percent']),
            'ordinance_ref' => $rate['ordinance_ref'],
            'effective_from' => $rate['effective_from']
        ];
    }
    
    sendSuccess($rateMap);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
