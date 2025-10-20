<?php
/**
 * List Businesses API Endpoint
 * GET /api/business/list_businesses.php
 * 
 * Parameters:
 * - q: search query (optional)
 * - status: business status filter (optional)
 * - barangay: barangay filter (optional)
 * - type: business type filter (optional)
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
    $search = $_GET['q'] ?? $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $barangay = $_GET['barangay'] ?? '';
    $type = $_GET['type'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(100, intval($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;
    
    // Build base query - show businesses that need assessment or have pending/assessed/overdue assessments
    $baseQuery = "FROM businesses b 
                  INNER JOIN owners o ON b.owner_id = o.id
                  LEFT JOIN assessments a ON b.id = a.business_id AND a.year = YEAR(CURDATE())
                  LEFT JOIN (
                      SELECT business_id, 
                             MAX(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as has_paid_assessment,
                             MAX(CASE WHEN status IN ('pending', 'assessed', 'overdue') THEN 1 ELSE 0 END) as has_unpaid_assessment
                      FROM assessments 
                      WHERE year = YEAR(CURDATE())
                      GROUP BY business_id
                  ) assessment_check ON b.id = assessment_check.business_id";
    
    $whereConditions = [];
    $params = [];
    
    // Add search condition
    if (!empty($search)) {
        $whereConditions[] = "(b.business_name LIKE ? OR o.full_name LIKE ? OR b.tin LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Add status filter (assessment status: pending, assessed, overdue, or no assessment)
    if (!empty($status)) {
        if ($status === 'no_assessment') {
            $whereConditions[] = "assessment_check.has_unpaid_assessment IS NULL";
        } else {
            $whereConditions[] = "a.status = ?";
            $params[] = $status;
        }
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
    
    // Show businesses that need assessment or have unpaid assessments (pending, assessed, overdue)
    $whereConditions[] = "(assessment_check.has_paid_assessment IS NULL OR assessment_check.has_paid_assessment = 0)";
    
    // Build WHERE clause
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total $baseQuery $whereClause";
    $countResult = $db->prepare($countQuery, $params);
    $totalRecords = $countResult->fetch()['total'];
    
    // Get businesses with pagination
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
                a.status as assessment_status,
                a.total_due
              $baseQuery 
              $whereClause 
              ORDER BY b.business_name ASC 
              LIMIT $perPage OFFSET $offset";
    
    $result = $db->prepare($query, $params);
    $businesses = $result->fetchAll();
    
    // Calculate pagination info
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get unique barangays and business types for filters
    $barangaysQuery = "SELECT DISTINCT barangay FROM businesses WHERE barangay IS NOT NULL ORDER BY barangay";
    $barangaysResult = $db->prepare($barangaysQuery);
    $barangays = array_column($barangaysResult->fetchAll(), 'barangay');
    
    $typesQuery = "SELECT DISTINCT business_type FROM businesses WHERE business_type IS NOT NULL ORDER BY business_type";
    $typesResult = $db->prepare($typesQuery);
    $types = array_column($typesResult->fetchAll(), 'business_type');
    
    $response = [
        'businesses' => $businesses,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'filters' => [
            'barangays' => $barangays,
            'types' => $types,
            'statuses' => ['pending', 'assessed', 'overdue', 'no_assessment']
        ]
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
