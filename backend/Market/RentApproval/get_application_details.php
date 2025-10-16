<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include your database file
include_once __DIR__ . '/../../../db/Market/market_db.php';

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get application ID from query parameters
    $application_id = $_GET['id'] ?? null;

    if (!$application_id) {
        throw new Exception('Application ID is required');
    }

    // Validate application ID is numeric
    if (!is_numeric($application_id)) {
        throw new Exception('Invalid application ID');
    }

    // Updated query to include all necessary fields with combined full_name
    $query = "
        SELECT 
            a.*,
            CONCAT(a.first_name, ' ', IFNULL(CONCAT(a.middle_name, ' '), ''), a.last_name) as full_name,
            s.name AS stall_name,
            s.status AS stall_status,
            s.price AS stall_price,
            s.length, s.width, s.height,
            sr.class_name,
            sr.price AS stall_rights_price,
            sr.description AS class_description,
            sec.name AS section_name,
            m.name AS market_name
        FROM applications a
        LEFT JOIN stalls s ON a.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN maps m ON s.map_id = m.id
        WHERE a.id = ?
    ";

    $stmt = $pdo->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . implode(' ', $pdo->errorInfo()));
    }
    
    $stmt->execute([$application_id]);

    if ($stmt->rowCount() > 0) {
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        // Format complete address from components
        $address_components = [
            $application['house_number'] ?? '',
            $application['street'] ?? '',
            $application['barangay'] ?? '',
            $application['city'] ?? '',
            $application['zip_code'] ?? ''
        ];
        
        // Filter out empty components and create formatted address
        $address_components = array_filter($address_components, function($component) {
            return !empty($component) && trim($component) !== '';
        });
        
        $application['formatted_address'] = !empty($address_components) ? implode(', ', $address_components) : 'No address provided';

        // Get documents for this application
        $docQuery = "SELECT * FROM documents WHERE application_id = ?";
        $docStmt = $pdo->prepare($docQuery);
        $docStmt->execute([$application_id]);
        
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        $application['documents'] = $documents;

        // Ensure all expected fields exist
        $required_fields = [
            'business_name', 'full_name', 'status', 'application_date',
            'gender', 'date_of_birth', 'civil_status', 'contact_number', 'email',
            'application_type', 'stall_number', 'stall_status',
            'first_name', 'middle_name', 'last_name'
        ];
        
        foreach ($required_fields as $field) {
            if (!isset($application[$field])) {
                $application[$field] = null;
            }
        }

        $response = [
            'success' => true,
            'application' => $application
        ];

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('Application not found with ID: ' . $application_id);
    }

} catch (PDOException $e) {
    $error_response = [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => 'DB_ERROR'
    ];
    http_response_code(500);
    echo json_encode($error_response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $error_response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'VALIDATION_ERROR'
    ];
    http_response_code(400);
    echo json_encode($error_response, JSON_PRETTY_PRINT);
}

// Ensure no extra output
exit();
?>