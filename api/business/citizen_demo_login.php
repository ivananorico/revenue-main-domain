<?php
/**
 * Citizen Demo Login API
 * Creates a demo session for testing the citizen portal
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
    
    // Check if demo owner exists
    $stmt = $pdo->prepare("SELECT id FROM owners WHERE email = ?");
    $stmt->execute(['demo@example.com']);
    $owner = $stmt->fetch();
    
    if (!$owner) {
        // Get the next available ID
        $stmt = $pdo->prepare("SELECT MAX(id) FROM owners");
        $stmt->execute();
        $maxId = $stmt->fetchColumn();
        $ownerId = ($maxId ? $maxId + 1 : 3);
        
        // Create demo owner
        $stmt = $pdo->prepare("INSERT INTO owners (id, full_name, contact_number, email, barangay) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $ownerId,
            'Demo User',
            '09171234567',
            'demo@example.com',
            'Barangay 1'
        ]);
    } else {
        $ownerId = $owner['id'];
    }
    
    // Start session
    session_start();
    
    // Set session
    $_SESSION['user_id'] = $ownerId;
    $_SESSION['role'] = 'citizen';
    $_SESSION['username'] = 'demo_user';
    $_SESSION['full_name'] = 'Demo User';
    
    echo json_encode([
        'success' => true,
        'message' => 'Demo login successful',
        'data' => [
            'user_id' => $ownerId,
            'full_name' => 'Demo User'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Demo login failed: ' . $e->getMessage()
    ]);
}
?>
