<?php
/**
 * Dashboard Data API Endpoint
 * GET /api/business/dashboard_data.php
 * 
 * Parameters:
 * - from: start date (YYYY-MM-DD) (optional)
 * - to: end date (YYYY-MM-DD) (optional)
 * - barangay: filter by barangay (optional)
 * - type: filter by business type (optional)
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

try {
    $db = Database::getInstance();
    
    // Get parameters
    $fromDate = $_GET['from'] ?? date('Y-01-01'); // Default to current year start
    $toDate = $_GET['to'] ?? date('Y-12-31'); // Default to current year end
    $barangay = $_GET['barangay'] ?? '';
    $type = $_GET['type'] ?? '';
    
    // Validate dates
    if (!strtotime($fromDate) || !strtotime($toDate)) {
        sendError('Invalid date format. Use YYYY-MM-DD');
    }
    
    $whereConditions = [];
    $params = [];
    
    // Add date filter
    $whereConditions[] = "DATE(a.created_at) BETWEEN ? AND ?";
    $params = array_merge($params, [$fromDate, $toDate]);
    
    // Add barangay filter
    if (!empty($barangay)) {
        $whereConditions[] = "b.barangay = ?";
        $params[] = $barangay;
    }
    
    // Add business type filter
    if (!empty($type)) {
        $whereConditions[] = "b.business_type = ?";
        $params[] = $type;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Summary Cards Data
    $summaryQuery = "SELECT 
                      COUNT(DISTINCT a.id) as total_assessments,
                      SUM(a.tax_amount) as total_tax_collected,
                      SUM(a.fees_total) as total_fees_collected,
                      SUM(a.total_due) as total_due,
                      COUNT(DISTINCT CASE WHEN a.status = 'paid' THEN a.id END) as paid_assessments,
                      COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_assessments,
                      COUNT(DISTINCT CASE WHEN a.status = 'overdue' THEN a.id END) as overdue_assessments,
                      COUNT(DISTINCT b.id) as active_businesses
                    FROM assessments a
                    INNER JOIN businesses b ON a.business_id = b.id
                    $whereClause";
    
    $summaryResult = $db->prepare($summaryQuery, $params);
    $summary = $summaryResult->fetch();
    
    // Get total payments in date range
    $paymentsQuery = "SELECT SUM(p.amount_paid) as total_payments
                      FROM payments p
                      INNER JOIN assessments a ON p.assessment_id = a.id
                      INNER JOIN businesses b ON a.business_id = b.id
                      WHERE DATE(p.paid_at) BETWEEN ? AND ?";
    
    $paymentsParams = [$fromDate, $toDate];
    if (!empty($barangay)) {
        $paymentsQuery .= " AND b.barangay = ?";
        $paymentsParams[] = $barangay;
    }
    if (!empty($type)) {
        $paymentsQuery .= " AND b.business_type = ?";
        $paymentsParams[] = $type;
    }
    
    $paymentsResult = $db->prepare($paymentsQuery, $paymentsParams);
    $payments = $paymentsResult->fetch();
    
    // Monthly Revenue Comparison (Taxes vs Fees)
    $monthlyQuery = "SELECT 
                       YEAR(a.created_at) as year,
                       MONTH(a.created_at) as month,
                       SUM(a.tax_amount) as tax_revenue,
                       SUM(a.fees_total) as fee_revenue,
                       SUM(a.total_due) as total_revenue
                     FROM assessments a
                     INNER JOIN businesses b ON a.business_id = b.id
                     $whereClause
                     GROUP BY YEAR(a.created_at), MONTH(a.created_at)
                     ORDER BY year, month";
    
    $monthlyResult = $db->prepare($monthlyQuery, $params);
    $monthlyData = $monthlyResult->fetchAll();
    
    // Revenue by Business Type
    $typeQuery = "SELECT 
                    b.business_type,
                    SUM(a.tax_amount) as tax_revenue,
                    SUM(a.fees_total) as fee_revenue,
                    SUM(a.total_due) as total_revenue,
                    COUNT(DISTINCT a.business_id) as business_count
                  FROM assessments a
                  INNER JOIN businesses b ON a.business_id = b.id
                  $whereClause
                  GROUP BY b.business_type
                  ORDER BY total_revenue DESC";
    
    $typeResult = $db->prepare($typeQuery, $params);
    $typeData = $typeResult->fetchAll();
    
    // Collections by Barangay
    $barangayQuery = "SELECT 
                        b.barangay,
                        SUM(a.tax_amount) as tax_revenue,
                        SUM(a.fees_total) as fee_revenue,
                        SUM(a.total_due) as total_revenue,
                        COUNT(DISTINCT a.business_id) as business_count,
                        COUNT(DISTINCT CASE WHEN a.status = 'paid' THEN a.id END) as paid_count
                      FROM assessments a
                      INNER JOIN businesses b ON a.business_id = b.id
                      $whereClause
                      GROUP BY b.barangay
                      ORDER BY total_revenue DESC";
    
    $barangayResult = $db->prepare($barangayQuery, $params);
    $barangayData = $barangayResult->fetchAll();
    
    // Compliance Overview
    $complianceQuery = "SELECT 
                          b.id as business_id,
                          b.business_name,
                          b.barangay,
                          b.business_type,
                          a.year,
                          a.status,
                          a.total_due,
                          DATEDIFF(CURDATE(), a.created_at) as days_overdue
                        FROM businesses b
                        LEFT JOIN assessments a ON b.id = a.business_id AND a.year = YEAR(CURDATE())
                        WHERE b.status = 'active'
                        ORDER BY 
                          CASE WHEN a.status = 'overdue' THEN 1 ELSE 2 END,
                          days_overdue ASC,
                          b.created_at ASC
                        LIMIT 20";
    
    $complianceResult = $db->prepare($complianceQuery);
    $complianceData = $complianceResult->fetchAll();
    
    // New registrations this quarter
    $quarterStart = date('Y-m-d', strtotime('first day of this quarter'));
    $quarterEnd = date('Y-m-d', strtotime('last day of this quarter'));
    
    $newRegistrationsQuery = "SELECT COUNT(*) as new_registrations
                              FROM businesses 
                              WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $newRegistrationsResult = $db->prepare($newRegistrationsQuery, [$quarterStart, $quarterEnd]);
    $newRegistrations = $newRegistrationsResult->fetch()['new_registrations'];
    
    $response = [
        'summary' => [
            'total_assessments' => intval($summary['total_assessments']),
            'total_tax_collected' => floatval($summary['total_tax_collected']),
            'total_fees_collected' => floatval($summary['total_fees_collected']),
            'total_due' => floatval($summary['total_due']),
            'total_payments' => floatval($payments['total_payments']),
            'paid_assessments' => intval($summary['paid_assessments']),
            'pending_assessments' => intval($summary['pending_assessments']),
            'overdue_assessments' => intval($summary['overdue_assessments']),
            'active_businesses' => intval($summary['active_businesses']),
            'new_registrations_quarter' => intval($newRegistrations)
        ],
        'monthly_revenue' => $monthlyData,
        'revenue_by_type' => $typeData,
        'collections_by_barangay' => $barangayData,
        'compliance_overview' => $complianceData,
        'filters_applied' => [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'barangay' => $barangay,
            'type' => $type
        ]
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
