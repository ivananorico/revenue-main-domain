<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../../db/Market/market_db.php';

$response = array("success" => false, "message" => "", "data" => null);

try {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $renter_id = $_GET['renter_id'] ?? '';
        
        // If no renter_id provided, return all active renters - UPDATED FOR NEW STRUCTURE
        if (empty($renter_id)) {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    renter_id,
                    application_id,
                    user_id,
                    stall_id,
                    first_name,
                    middle_name,
                    last_name,
                    CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) as full_name,
                    contact_number,
                    email,
                    business_name,
                    market_name,
                    stall_number,
                    section_name,
                    class_name,
                    monthly_rent,
                    stall_rights_fee,
                    security_bond,
                    status,
                    created_at,
                    updated_at
                FROM renters 
                WHERE status = 'active'
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $renters = $stmt->fetchAll();
            
            $response["success"] = true;
            $response["data"] = array("renters" => $renters);
            $response["message"] = "Renters retrieved successfully";
            
        } else {
            // GET SPECIFIC RENTER - UPDATED FOR NEW STRUCTURE
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    renter_id,
                    application_id,
                    user_id,
                    stall_id,
                    first_name,
                    middle_name,
                    last_name,
                    CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) as full_name,
                    contact_number,
                    email,
                    business_name,
                    market_name,
                    stall_number,
                    section_name,
                    class_name,
                    monthly_rent,
                    stall_rights_fee,
                    security_bond,
                    status,
                    created_at,
                    updated_at
                FROM renters 
                WHERE renter_id = ?
            ");
            $stmt->execute([$renter_id]);
            $renter = $stmt->fetch();
            
            if ($renter) {
                // Get payment history for this renter
                $paymentStmt = $pdo->prepare("
                    SELECT * FROM monthly_payments 
                    WHERE renter_id = ? 
                    ORDER BY due_date ASC
                ");
                $paymentStmt->execute([$renter_id]);
                $payments = $paymentStmt->fetchAll();
                
                $response["success"] = true;
                $response["data"] = array(
                    "renter" => $renter,
                    "payments" => $payments
                );
                $response["message"] = "Renter details retrieved successfully";
                
                // Log the data for debugging
                error_log("Renter data retrieved: " . json_encode($renter));
                
            } else {
                $response["message"] = "Renter not found in database";
                error_log("Renter not found: " . $renter_id);
            }
        }
    } else {
        $response["message"] = "Invalid request method";
    }
    
} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("Database error in renter_rent_details.php: " . $e->getMessage());
}

echo json_encode($response);
?>