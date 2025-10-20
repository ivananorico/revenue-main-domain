<?php
/**
 * Export CSV API Endpoint
 * GET /api/business/export_csv.php
 * 
 * Parameters:
 * - type: export type (assessments, dashboard, businesses)
 * - from: start date (optional)
 * - to: end date (optional)
 * - barangay: filter by barangay (optional)
 * - type_filter: filter by business type (optional)
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

// Get parameters
$exportType = $_GET['type'] ?? 'assessments';
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$barangay = $_GET['barangay'] ?? '';
$typeFilter = $_GET['type_filter'] ?? '';

// Validate export type
$validTypes = ['assessments', 'dashboard', 'businesses'];
if (!in_array($exportType, $validTypes)) {
    sendError('Invalid export type');
}

try {
    $db = Database::getInstance();
    
    // Set headers for CSV download
    $filename = $exportType . '_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    switch ($exportType) {
        case 'assessments':
            exportAssessments($db, $output, $fromDate, $toDate, $barangay, $typeFilter);
            break;
            
        case 'dashboard':
            exportDashboardData($db, $output, $fromDate, $toDate, $barangay, $typeFilter);
            break;
            
        case 'businesses':
            exportBusinesses($db, $output, $barangay, $typeFilter);
            break;
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    sendError('Export error: ' . $e->getMessage(), 500);
}

/**
 * Export assessments data
 */
function exportAssessments($db, $output, $fromDate, $toDate, $barangay, $typeFilter) {
    // Build query
    $whereConditions = [];
    $params = [];
    
    if (!empty($fromDate) && !empty($toDate)) {
        $whereConditions[] = "DATE(a.assessed_at) BETWEEN ? AND ?";
        $params = array_merge($params, [$fromDate, $toDate]);
    }
    
    if (!empty($barangay)) {
        $whereConditions[] = "b.barangay = ?";
        $params[] = $barangay;
    }
    
    if (!empty($typeFilter)) {
        $whereConditions[] = "b.business_type = ?";
        $params[] = $typeFilter;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $query = "SELECT 
                a.id as assessment_id,
                b.business_name,
                b.tin,
                b.barangay,
                b.business_type,
                o.full_name as owner_name,
                a.year,
                a.gross_sales,
                a.tax_amount,
                a.fees_total,
                a.discounts,
                a.penalties,
                a.total_due,
                a.status,
                a.created_at
              FROM assessments a
              INNER JOIN businesses b ON a.business_id = b.id
              INNER JOIN owners o ON b.owner_id = o.id
              $whereClause
              ORDER BY a.year DESC, a.created_at DESC";
    
    $result = $db->prepare($query, $params);
    $assessments = $result->fetchAll();
    
    // Write CSV header
    fputcsv($output, [
        'Assessment ID', 'Business Name', 'TIN', 'Barangay', 'Business Type',
        'Owner Name', 'Year', 'Gross Sales', 'Tax Amount', 'Total Fees',
        'Discounts', 'Penalties', 'Total Due', 'Status', 'Created At'
    ]);
    
    // Write data rows
    foreach ($assessments as $assessment) {
        fputcsv($output, [
            $assessment['assessment_id'],
            $assessment['business_name'],
            $assessment['tin'],
            $assessment['barangay'],
            $assessment['business_type'],
            $assessment['owner_name'],
            $assessment['year'],
            $assessment['gross_sales'],
            $assessment['tax_amount'],
            $assessment['fees_total'],
            $assessment['discounts'],
            $assessment['penalties'],
            $assessment['total_due'],
            $assessment['status'],
            $assessment['created_at']
        ]);
    }
}

/**
 * Export dashboard summary data
 */
function exportDashboardData($db, $output, $fromDate, $toDate, $barangay, $typeFilter) {
    // Use the same logic as dashboard_data.php but format for CSV
    $whereConditions = [];
    $params = [];
    
    if (!empty($fromDate) && !empty($toDate)) {
        $whereConditions[] = "DATE(a.assessed_at) BETWEEN ? AND ?";
        $params = array_merge($params, [$fromDate, $toDate]);
    }
    
    if (!empty($barangay)) {
        $whereConditions[] = "b.barangay = ?";
        $params[] = $barangay;
    }
    
    if (!empty($typeFilter)) {
        $whereConditions[] = "b.business_type = ?";
        $params[] = $typeFilter;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Summary by barangay
    $query = "SELECT 
                b.barangay,
                COUNT(DISTINCT a.business_id) as business_count,
                SUM(a.tax_amount) as tax_revenue,
                SUM(a.fees_total) as fee_revenue,
                SUM(a.total_due) as total_revenue,
                COUNT(DISTINCT CASE WHEN a.status = 'paid' THEN a.id END) as paid_count,
                COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_count,
                COUNT(DISTINCT CASE WHEN a.status = 'overdue' THEN a.id END) as overdue_count
              FROM assessments a
              INNER JOIN businesses b ON a.business_id = b.id
              $whereClause
              GROUP BY b.barangay
              ORDER BY total_revenue DESC";
    
    $result = $db->prepare($query, $params);
    $data = $result->fetchAll();
    
    // Write CSV header
    fputcsv($output, [
        'Barangay', 'Business Count', 'Tax Revenue', 'Fee Revenue', 'Total Revenue',
        'Paid Assessments', 'Pending Assessments', 'Overdue Assessments'
    ]);
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['barangay'],
            $row['business_count'],
            $row['tax_revenue'],
            $row['fee_revenue'],
            $row['total_revenue'],
            $row['paid_count'],
            $row['pending_count'],
            $row['overdue_count']
        ]);
    }
}

/**
 * Export businesses data
 */
function exportBusinesses($db, $output, $barangay, $typeFilter) {
    $whereConditions = [];
    $params = [];
    
    if (!empty($barangay)) {
        $whereConditions[] = "b.barangay = ?";
        $params[] = $barangay;
    }
    
    if (!empty($typeFilter)) {
        $whereConditions[] = "b.business_type = ?";
        $params[] = $typeFilter;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
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
                o.full_name as owner_name,
                o.email,
                o.contact_number
              FROM businesses b
              INNER JOIN owners o ON b.owner_id = o.id
              $whereClause
              ORDER BY b.business_name";
    
    $result = $db->prepare($query, $params);
    $businesses = $result->fetchAll();
    
    // Write CSV header
    fputcsv($output, [
        'Business ID', 'Business Name', 'TIN', 'Business Type', 'Barangay',
        'Address', 'Capital', 'Last Year Gross', 'Status', 'Owner Name',
        'Owner Email', 'Owner Phone', 'Created At'
    ]);
    
    // Write data rows
    foreach ($businesses as $business) {
        fputcsv($output, [
            $business['business_id'],
            $business['business_name'],
            $business['tin'],
            $business['business_type'],
            $business['barangay'],
            $business['address'],
            $business['capital'],
            $business['last_year_gross'],
            $business['status'],
            $business['owner_name'],
            $business['email'],
            $business['contact_number'],
            $business['created_at']
        ]);
    }
}
?>
