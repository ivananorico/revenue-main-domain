<?php
/**
 * Citizen My Businesses API
 * GET: Returns businesses owned by logged-in user
 */

// CORS Headers
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
$host = 'localhost:3307';
$dbname = 'gov_revenue';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start session to get user ID
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        // Check localStorage for demo user
        $demoUserId = $_GET['demo_user_id'] ?? null;
        if ($demoUserId) {
            $userId = $demoUserId;
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }
    
    // Get query parameters
    $search = htmlspecialchars(trim($_GET['search'] ?? ''), ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars(trim($_GET['status'] ?? ''), ENT_QUOTES, 'UTF-8');
    $filter = htmlspecialchars(trim($_GET['filter'] ?? 'all'), ENT_QUOTES, 'UTF-8');
    
    // Build query
    $sql = "
        SELECT 
            b.id,
            b.business_name,
            b.tin,
            b.business_type,
            b.address,
            b.barangay,
            b.capital,
            b.last_year_gross,
            b.status,
            b.created_at,
            (SELECT MAX(year) FROM assessments WHERE business_id = b.id) as last_assessment_year,
            (SELECT SUM(total_due) FROM assessments WHERE business_id = b.id AND status != 'paid') as outstanding_balance,
            (SELECT COUNT(*) FROM permits WHERE assessment_id IN (SELECT id FROM assessments WHERE business_id = b.id)) as has_permit
        FROM businesses b 
        WHERE b.owner_id = ?
    ";
    
    $params = [$userId];
    
    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (b.business_name LIKE ? OR b.tin LIKE ? OR b.address LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Add status filter
    if (!empty($status)) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    
    // Add specific filters
    switch ($filter) {
        case 'active':
            $sql .= " AND b.status = 'active'";
            break;
        case 'pending':
            $sql .= " AND b.status = 'active' AND b.id NOT IN (SELECT business_id FROM assessments)";
            break;
        case 'assessments':
            $sql .= " AND b.id IN (SELECT business_id FROM assessments WHERE status != 'paid')";
            break;
        case 'permits':
            $sql .= " AND b.id IN (SELECT business_id FROM assessments WHERE id IN (SELECT assessment_id FROM permits))";
            break;
    }
    
    $sql .= " ORDER BY b.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $businesses = $stmt->fetchAll();
    
    // Format the data
    $formattedBusinesses = array_map(function($business) {
        return [
            'id' => $business['id'],
            'business_name' => $business['business_name'],
            'tin' => $business['tin'],
            'business_type' => $business['business_type'],
            'address' => $business['address'],
            'barangay' => $business['barangay'],
            'capital' => floatval($business['capital']),
            'last_year_gross' => floatval($business['last_year_gross']),
            'status' => $business['status'],
            'last_assessment_year' => $business['last_assessment_year'],
            'outstanding_balance' => floatval($business['outstanding_balance'] ?? 0),
            'has_permit' => $business['has_permit'] > 0,
            'created_at' => $business['created_at']
        ];
    }, $businesses);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'businesses' => $formattedBusinesses,
            'total' => count($formattedBusinesses)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load businesses: ' . $e->getMessage()
    ]);
}
?>

