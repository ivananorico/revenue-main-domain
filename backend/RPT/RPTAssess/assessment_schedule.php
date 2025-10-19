<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database configuration
$host = 'localhost:3307';
$dbname = 'rpt_system';
$username = 'root';  // Change if needed
$password = '';      // Change if needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->application_id) && !empty($data->visit_date) && !empty($data->assessor_name)) {
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // 1. Insert into assessment_schedule table
        $query = "INSERT INTO rpt_assessment_schedule 
                  (application_id, visit_date, assessor_name, notes) 
                  VALUES (:application_id, :visit_date, :assessor_name, :notes)";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':application_id', $data->application_id);
        $stmt->bindParam(':visit_date', $data->visit_date);
        $stmt->bindParam(':assessor_name', $data->assessor_name);
        $stmt->bindParam(':notes', $data->notes);
        $stmt->execute();
        
        // 2. Update application status to 'for_assessment'
        $query = "UPDATE rpt_applications SET status = 'for_assessment' WHERE id = :application_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':application_id', $data->application_id);
        $stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            "status" => "success",
            "message" => "Assessment scheduled successfully"
        ]);
        
    } catch(PDOException $exception) {
        $pdo->rollBack();
        echo json_encode([
            "status" => "error",
            "message" => "Error scheduling assessment: " . $exception->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: application_id, visit_date, and assessor_name are required"
    ]);
}
?>