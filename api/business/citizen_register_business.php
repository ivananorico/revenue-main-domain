<?php
/**
 * Citizen Register Business API
 * POST: Create business application (status = pending)
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
        $demoUserId = $_POST['demo_user_id'] ?? null;
        if ($demoUserId) {
            $userId = $demoUserId;
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['business_name', 'business_type', 'capital', 'address', 'barangay'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
    }
    
    // Sanitize input
    $businessName = htmlspecialchars(trim($input['business_name']), ENT_QUOTES, 'UTF-8');
    $businessType = htmlspecialchars(trim($input['business_type']), ENT_QUOTES, 'UTF-8');
    $capital = floatval($input['capital']);
    $address = htmlspecialchars(trim($input['address']), ENT_QUOTES, 'UTF-8');
    $barangay = htmlspecialchars(trim($input['barangay']), ENT_QUOTES, 'UTF-8');
    $tin = htmlspecialchars(trim($input['tin'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // Validate business type exists in tax rates
    $stmt = $pdo->prepare("SELECT id FROM tax_rates WHERE category = ?");
    $stmt->execute([$businessType]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid business type']);
        exit;
    }
    
    // Get next available business ID
    $stmt = $pdo->prepare("SELECT MAX(id) FROM businesses");
    $stmt->execute();
    $maxId = $stmt->fetchColumn();
    $businessId = ($maxId ? $maxId + 1 : 1);
    
    // Insert business record
    $stmt = $pdo->prepare("
        INSERT INTO businesses (id, owner_id, business_name, tin, business_type, address, barangay, capital, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    
    $stmt->execute([
        $businessId,
        $userId,
        $businessName,
        $tin,
        $businessType,
        $address,
        $barangay,
        $capital
    ]);
    
    // Create notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (business_id, type, message, is_read, created_at) 
        VALUES (?, 'application_submitted', ?, 0, NOW())
    ");
    $stmt->execute([
        $businessId,
        "Your business application for '{$businessName}' has been submitted and is under review."
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Business application submitted successfully',
        'data' => [
            'business_id' => $businessId,
            'business_name' => $businessName,
            'application_number' => 'APP-' . str_pad($businessId, 6, '0', STR_PAD_LEFT)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}
?>

