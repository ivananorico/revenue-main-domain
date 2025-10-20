<?php
/**
 * Citizen Notifications API
 * GET: Returns notifications for the logged-in user
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
    
    // Get notifications for user's businesses
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.type,
            n.message,
            n.is_read,
            n.created_at,
            b.business_name
        FROM notifications n
        LEFT JOIN businesses b ON n.business_id = b.id
        WHERE b.owner_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    // Format notifications
    $formattedNotifications = array_map(function($notification) {
        return [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'message' => $notification['message'],
            'is_read' => (bool)$notification['is_read'],
            'business_name' => $notification['business_name'],
            'created_at' => $notification['created_at']
        ];
    }, $notifications);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $formattedNotifications,
            'total' => count($formattedNotifications)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications: ' . $e->getMessage()
    ]);
}
?>

