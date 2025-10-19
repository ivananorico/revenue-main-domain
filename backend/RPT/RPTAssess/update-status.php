<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../db/RPT/rpt_db.php';

// If Database class doesn't exist, create a simple connection
if (!class_exists('Database')) {
    // Simple database connection
    $host = 'localhost:3307';
    $dbname = 'rpt_system';
    $username = 'root';
    $password = '';
    
    $db = new mysqli($host, $username, $password, $dbname);
    
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }
} else {
    $database = new Database();
    $db = $database->getConnection();
}

$response = ['status' => 'error', 'message' => 'Unknown error'];

try {
    // Get the raw POST data
    $json_input = file_get_contents('php://input');
    
    // Log the raw input for debugging
    error_log("Raw JSON input: " . $json_input);
    
    if (empty($json_input)) {
        throw new Exception('No JSON data received');
    }

    // Decode JSON input
    $input = json_decode($json_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . $json_input);
    }

    // Validate required fields
    if (!isset($input['application_id'])) {
        throw new Exception('Application ID is required');
    }

    if (!isset($input['status'])) {
        throw new Exception('Status is required');
    }

    $application_id = intval($input['application_id']);
    $status = trim($input['status']);
    
    // Validate status
    $valid_statuses = ['pending', 'for_assessment', 'assessed', 'approved', 'rejected', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status value. Must be one of: ' . implode(', ', $valid_statuses));
    }

    // Check if application exists
    $check_query = "SELECT id FROM rpt_applications WHERE id = ?";
    $check_stmt = $db->prepare($check_query);
    
    if (!$check_stmt) {
        throw new Exception('Database preparation failed: ' . $db->error);
    }

    $check_stmt->bind_param('i', $application_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        throw new Exception('No application found with ID: ' . $application_id);
    }
    $check_stmt->close();

    // Update application status
    $query = "UPDATE rpt_applications SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $db->error);
    }

    $stmt->bind_param('si', $status, $application_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response = [
                'status' => 'success',
                'message' => 'Application status updated successfully',
                'data' => [
                    'application_id' => $application_id,
                    'new_status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            error_log("Status updated successfully: Application $application_id -> $status");
        } else {
            throw new Exception('No changes made. Application might already have this status.');
        }
    } else {
        throw new Exception('Database execution failed: ' . $stmt->error);
    }

    $stmt->close();
    $db->close();

} catch (Exception $e) {
    error_log("Error in update-status.php: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>