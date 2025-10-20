<?php
/**
 * Assessment History API Endpoint
 * GET /api/business/assessment_history.php
 * 
 * Returns all assessments including paid ones for history view
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

try {
    $db = Database::getInstance();
    
    // Get parameters
    $search = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';
    $barangay = $_GET['barangay'] ?? '';
    $type = $_GET['type'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(100, intval($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;
    
    // Build base query for all assessments
    $baseQuery = "FROM businesses b 
                  INNER JOIN owners o ON b.owner_id = o.id
                  INNER JOIN assessments a ON b.id = a.business_id
                  WHERE a.year = YEAR(CURDATE())";
    
    $whereConditions = [];
    $params = [];
    
    // Add search condition
    if (!empty($search)) {
        $whereConditions[] = "(b.business_name LIKE ? OR o.full_name LIKE ? OR b.tin LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Add status filter
    if (!empty($status)) {
        $whereConditions[] = "a.status = ?";
        $params[] = $status;
    }
    
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
    
    // Build WHERE clause
    $whereClause = !empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total $baseQuery $whereClause";
    $countResult = $db->prepare($countQuery, $params);
    $totalRecords = $countResult->fetch()['total'];
    
    // Get assessments with pagination
    $query = "SELECT 
                b.id as business_id,
                b.business_name,
                b.tin,
                b.business_type,
                b.barangay,
                b.address,
                b.last_year_gross,
                b.status,
                o.full_name as owner_name,
                o.email as owner_email,
                o.contact_number as owner_phone,
                a.id as assessment_id,
                a.year as assessment_year,
                a.gross_sales,
                a.tax_amount,
                a.fees_total,
                a.discounts,
                a.penalties,
                a.total_due,
                a.status as assessment_status,
                a.created_at as assessed_at
              $baseQuery 
              $whereClause 
              ORDER BY a.created_at DESC 
              LIMIT $perPage OFFSET $offset";
    
    $result = $db->prepare($query, $params);
    $assessments = $result->fetchAll();
    
    // Calculate pagination info
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get unique values for filters
    $filtersQuery = "SELECT DISTINCT b.barangay, b.business_type, a.status as assessment_status
                     FROM businesses b
                     INNER JOIN assessments a ON b.id = a.business_id
                     WHERE a.year = YEAR(CURDATE())";
    
    $filtersResult = $db->prepare($filtersQuery);
    $filtersData = $filtersResult->fetchAll();
    
    $barangays = array_unique(array_column($filtersData, 'barangay'));
    $types = array_unique(array_column($filtersData, 'business_type'));
    $statuses = array_unique(array_column($filtersData, 'assessment_status'));
    
    sort($barangays);
    sort($types);
    sort($statuses);
    
    sendSuccess([
        'assessments' => $assessments,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages
        ],
        'filters' => [
            'barangays' => $barangays,
            'types' => $types,
            'statuses' => $statuses
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>

