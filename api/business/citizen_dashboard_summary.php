<?php
/**
 * Citizen Dashboard Summary API
 * GET: Returns summary data for citizen dashboard
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
    
    // Get business count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE owner_id = ?");
    $stmt->execute([$userId]);
    $businessCount = $stmt->fetchColumn();
    
    // Get pending applications (businesses without assessments)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM businesses b 
        WHERE b.owner_id = ? AND b.id NOT IN (SELECT business_id FROM assessments WHERE business_id IS NOT NULL)
    ");
    $stmt->execute([$userId]);
    $pendingCount = $stmt->fetchColumn();
    
    // Get outstanding amount
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(a.total_due), 0) FROM assessments a
        LEFT JOIN businesses b ON a.business_id = b.id
        WHERE b.owner_id = ? AND a.status != 'paid'
    ");
    $stmt->execute([$userId]);
    $outstandingAmount = $stmt->fetchColumn();
    
    // Get permits count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM permits p
        LEFT JOIN assessments a ON p.assessment_id = a.id
        LEFT JOIN businesses b ON a.business_id = b.id
        WHERE b.owner_id = ? AND p.status = 'issued'
    ");
    $stmt->execute([$userId]);
    $permitsCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'business_count' => intval($businessCount),
            'pending_count' => intval($pendingCount),
            'outstanding_amount' => floatval($outstandingAmount),
            'permits_count' => intval($permitsCount)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load dashboard summary: ' . $e->getMessage()
    ]);
}
?>
