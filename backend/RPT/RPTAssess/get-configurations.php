<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../db/RPT/rpt_db.php';

if (!isset($_GET['type'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Configuration type is required"
    ]);
    exit;
}

$type = $_GET['type'];

try {
    switch ($type) {
        case 'land_use':
            // Get land use configurations
            $stmt = $pdo->prepare("SELECT land_use, market_value_per_sqm, land_assessed_lvl FROM land_rate_config");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'building_rates':
            // Get building rate configurations
            $stmt = $pdo->prepare("SELECT building_type, construction_type, market_value_per_sqm, building_assessed_lvl FROM building_rate_config");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'tax_rates':
            // Get tax rates (now only one record since we removed effective_year)
            $stmt = $pdo->prepare("SELECT tax_rate, sef_rate FROM tax_rate_config LIMIT 1");
            $stmt->execute();
            $configs = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        default:
            echo json_encode([
                "status" => "error",
                "message" => "Invalid configuration type"
            ]);
            exit;
    }

    echo json_encode([
        "status" => "success",
        "data" => $configs
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>