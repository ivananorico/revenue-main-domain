<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log for debugging
error_log("=== Display applications API called ===");

// Include your existing DB file
require_once __DIR__ . '/../../../db/Market/market_db.php';

// Log database connection info
error_log("Database file included");

try {
    // Test database connection first
    error_log("Testing database connection...");
    $testStmt = $pdo->query("SELECT 1");
    error_log("Database connection successful");

    // First, let's check what tables exist in the database
    error_log("Checking available tables...");
    $tableStmt = $pdo->query("SHOW TABLES");
    $tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available tables: " . implode(", ", $tables));

    // Check if applications table exists and its structure
    if (in_array('applications', $tables)) {
        error_log("Applications table exists, checking structure...");
        $structureStmt = $pdo->query("DESCRIBE applications");
        $structure = $structureStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Applications table structure: " . json_encode($structure));
        
        // Check if there's any data
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM applications");
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Total applications in database: " . $countResult['count']);
    } else {
        error_log("ERROR: Applications table does not exist!");
    }

    // Now fetch the applications
    error_log("Fetching applications...");
    $stmt = $pdo->prepare("SELECT * FROM applications ORDER BY id DESC");
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Found " . count($applications) . " applications");
    
    if (count($applications) > 0) {
        error_log("First application ID: " . $applications[0]['id']);
        error_log("First application business_name: " . $applications[0]['business_name']);
        error_log("All columns in first app: " . implode(", ", array_keys($applications[0])));
        error_log("First application full data: " . json_encode($applications[0]));
    } else {
        error_log("No applications found in database");
    }

    $response = [
        "success" => true,
        "applications" => $applications,
        "count" => count($applications),
        "debug_info" => [
            "total_applications" => count($applications),
            "database_tables" => $tables,
            "applications_table_exists" => in_array('applications', $tables),
            "first_application" => count($applications) > 0 ? $applications[0] : null,
            "all_columns" => count($applications) > 0 ? array_keys($applications[0]) : []
        ]
    ];
    
    error_log("Sending response: " . json_encode($response));
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Error in file: " . $e->getFile());
    error_log("Error on line: " . $e->getLine());
    
    $errorResponse = [
        "success" => false,
        "message" => "Database error: " . $e->getMessage(),
        "error_details" => [
            "code" => $e->getCode(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]
    ];
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    
    $errorResponse = [
        "success" => false,
        "message" => "General error: " . $e->getMessage()
    ];
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}

error_log("=== Display applications API finished ===");
?>