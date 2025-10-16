<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
include_once __DIR__ . '/../../../db/Market/market_db.php';

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $application_id = $input['application_id'] ?? null;
    $reviewer_notes = $input['reviewer_notes'] ?? '';

    if (!$application_id) {
        throw new Exception('Application ID is required');
    }

    // Validate application exists and is in a valid state for rejection
    $check_query = "SELECT status, stall_id FROM applications WHERE id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$application_id]);
    
    if ($check_stmt->rowCount() === 0) {
        throw new Exception('Application not found');
    }

    $application = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $current_status = $application['status'];
    $stall_id = $application['stall_id'];

    // Check if application can be rejected
    if (!in_array($current_status, ['pending', 'under_review'])) {
        throw new Exception("Application cannot be rejected from current status: {$current_status}");
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Update application status
        $update_application_query = "
            UPDATE applications 
            SET status = 'rejected', 
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $update_stmt = $pdo->prepare($update_application_query);
        $update_stmt->execute([$application_id]);

        // Update stall status back to available
        if ($stall_id) {
            $update_stall_query = "
                UPDATE stalls 
                SET status = 'available', 
                    updated_at = NOW()
                WHERE id = ?
            ";
            
            $stall_stmt = $pdo->prepare($update_stall_query);
            $stall_stmt->execute([$stall_id]);
        }

        // Check if application_logs table exists
        $logs_table_exists = $pdo->query("SHOW TABLES LIKE 'application_logs'")->rowCount() > 0;
        
        if ($logs_table_exists) {
            // Log the rejection
            $log_query = "
                INSERT INTO application_logs (
                    application_id,
                    action,
                    notes,
                    created_by,
                    created_at
                ) VALUES (?, 'rejected', ?, ?, NOW())
            ";
            
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([
                $application_id,
                $reviewer_notes ?: 'Application rejected',
                1 // Default reviewer ID
            ]);
        }

        // Commit transaction
        $pdo->commit();

        $response = [
            'success' => true,
            'message' => 'Application rejected successfully',
            'application_id' => $application_id
        ];

        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw new Exception('Transaction failed: ' . $e->getMessage());
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

exit();
?>