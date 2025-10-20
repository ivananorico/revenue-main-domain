    <?php
    // revenue/backend/RPT/RPTConfig/RPTConfig.php
    header('Content-Type: application/json');
    require_once '../../../db/RPT/rpt_db.php';

    // Enable CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }

    // Get the request method and action from query parameter
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // Debug output
    error_log("RPT Config - Method: $method, Action: $action, Request URI: " . $_SERVER['REQUEST_URI']);

    try {
        // Route the request based on action parameter
        switch ($action) {
            case 'tax-rates':
                handleTaxRates($method);
                break;
            case 'land-rates':
                handleLandRates($method);
                break;
            case 'building-rates':
                handleBuildingRates($method);
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Action not found. Use ?action=tax-rates, land-rates, or building-rates', 'received_action' => $action]);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }

    // Tax Rates Handlers
    function handleTaxRates($method) {
        global $pdo;
        
        switch ($method) {
            case 'GET':
                $stmt = $pdo->query("SELECT * FROM tax_rate_config ORDER BY tax_rate_id DESC");
                $taxRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($taxRates);
                break;
                
            case 'PUT':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['tax_rate_id']) || !isset($input['tax_rate']) || !isset($input['sef_rate'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields: tax_rate_id, tax_rate, and sef_rate']);
                    return;
                }
                
                $tax_rate_id = intval($input['tax_rate_id']);
                $tax_rate = floatval($input['tax_rate']);
                $sef_rate = floatval($input['sef_rate']);
                
                if ($tax_rate < 0 || $sef_rate < 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Tax rates cannot be negative']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE tax_rate_config SET tax_rate = ?, sef_rate = ? WHERE tax_rate_id = ?");
                $stmt->execute([$tax_rate, $sef_rate, $tax_rate_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Return updated data
                    $stmt = $pdo->query("SELECT * FROM tax_rate_config ORDER BY tax_rate_id DESC");
                    $updatedRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['message' => 'Tax rates updated successfully', 'data' => $updatedRates]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Tax rate configuration not found']);
                }
                break;
                
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed for tax rates']);
                break;
        }
    }

    // Land Rates Handlers
    function handleLandRates($method) {
        global $pdo;
        
        switch ($method) {
            case 'GET':
                $stmt = $pdo->query("SELECT * FROM land_rate_config ORDER BY land_rate_id");
                $landRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($landRates);
                break;
                
            case 'POST':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['land_use']) || !isset($input['market_value_per_sqm']) || !isset($input['land_assessed_lvl'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields: land_use, market_value_per_sqm, land_assessed_lvl']);
                    return;
                }
                
                $land_use = trim($input['land_use']);
                $market_value_per_sqm = floatval($input['market_value_per_sqm']);
                $land_assessed_lvl = floatval($input['land_assessed_lvl']);
                
                // Check if land use already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM land_rate_config WHERE land_use = ?");
                $checkStmt->execute([$land_use]);
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Land use already exists']);
                    return;
                }
                
                $stmt = $pdo->prepare("INSERT INTO land_rate_config (land_use, market_value_per_sqm, land_assessed_lvl) VALUES (?, ?, ?)");
                $stmt->execute([$land_use, $market_value_per_sqm, $land_assessed_lvl]);
                
                $newId = $pdo->lastInsertId();
                
                // Return the created record
                $stmt = $pdo->prepare("SELECT * FROM land_rate_config WHERE land_rate_id = ?");
                $stmt->execute([$newId]);
                $newRate = $stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(201);
                echo json_encode($newRate);
                break;
                
            case 'PUT':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['land_rate_id']) || !isset($input['land_use']) || !isset($input['market_value_per_sqm']) || !isset($input['land_assessed_lvl'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    return;
                }
                
                $land_rate_id = intval($input['land_rate_id']);
                $land_use = trim($input['land_use']);
                $market_value_per_sqm = floatval($input['market_value_per_sqm']);
                $land_assessed_lvl = floatval($input['land_assessed_lvl']);
                
                // Check if land use already exists for other records
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM land_rate_config WHERE land_use = ? AND land_rate_id != ?");
                $checkStmt->execute([$land_use, $land_rate_id]);
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Land use already exists for another record']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE land_rate_config SET land_use = ?, market_value_per_sqm = ?, land_assessed_lvl = ? WHERE land_rate_id = ?");
                $stmt->execute([$land_use, $market_value_per_sqm, $land_assessed_lvl, $land_rate_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Land rate updated successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Land rate not found']);
                }
                break;
                
            case 'DELETE':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['land_rate_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing land_rate_id']);
                    return;
                }
                
                $land_rate_id = intval($input['land_rate_id']);
                
                $stmt = $pdo->prepare("DELETE FROM land_rate_config WHERE land_rate_id = ?");
                $stmt->execute([$land_rate_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Land rate deleted successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Land rate not found']);
                }
                break;
                
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed for land rates']);
                break;
        }
    }

    // Building Rates Handlers
    function handleBuildingRates($method) {
        global $pdo;
        
        switch ($method) {
            case 'GET':
                $stmt = $pdo->query("SELECT * FROM building_rate_config ORDER BY building_rate_id");
                $buildingRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($buildingRates);
                break;
                
            case 'POST':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['building_type']) || !isset($input['construction_type']) || !isset($input['market_value_per_sqm']) || !isset($input['building_assessed_lvl'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields: building_type, construction_type, market_value_per_sqm, building_assessed_lvl']);
                    return;
                }
                
                $building_type = trim($input['building_type']);
                $construction_type = trim($input['construction_type']);
                $market_value_per_sqm = floatval($input['market_value_per_sqm']);
                $building_assessed_lvl = floatval($input['building_assessed_lvl']);
                
                // Check if combination already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM building_rate_config WHERE building_type = ? AND construction_type = ?");
                $checkStmt->execute([$building_type, $construction_type]);
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Building type and construction type combination already exists']);
                    return;
                }
                
                $stmt = $pdo->prepare("INSERT INTO building_rate_config (building_type, construction_type, market_value_per_sqm, building_assessed_lvl) VALUES (?, ?, ?, ?)");
                $stmt->execute([$building_type, $construction_type, $market_value_per_sqm, $building_assessed_lvl]);
                
                $newId = $pdo->lastInsertId();
                
                // Return the created record
                $stmt = $pdo->prepare("SELECT * FROM building_rate_config WHERE building_rate_id = ?");
                $stmt->execute([$newId]);
                $newRate = $stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(201);
                echo json_encode($newRate);
                break;
                
            case 'PUT':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['building_rate_id']) || !isset($input['building_type']) || !isset($input['construction_type']) || !isset($input['market_value_per_sqm']) || !isset($input['building_assessed_lvl'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    return;
                }
                
                $building_rate_id = intval($input['building_rate_id']);
                $building_type = trim($input['building_type']);
                $construction_type = trim($input['construction_type']);
                $market_value_per_sqm = floatval($input['market_value_per_sqm']);
                $building_assessed_lvl = floatval($input['building_assessed_lvl']);
                
                // Check if combination already exists for other records
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM building_rate_config WHERE building_type = ? AND construction_type = ? AND building_rate_id != ?");
                $checkStmt->execute([$building_type, $construction_type, $building_rate_id]);
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Building type and construction type combination already exists for another record']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE building_rate_config SET building_type = ?, construction_type = ?, market_value_per_sqm = ?, building_assessed_lvl = ? WHERE building_rate_id = ?");
                $stmt->execute([$building_type, $construction_type, $market_value_per_sqm, $building_assessed_lvl, $building_rate_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Building rate updated successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Building rate not found']);
                }
                break;
                
            case 'DELETE':
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['building_rate_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing building_rate_id']);
                    return;
                }
                
                $building_rate_id = intval($input['building_rate_id']);
                
                $stmt = $pdo->prepare("DELETE FROM building_rate_config WHERE building_rate_id = ?");
                $stmt->execute([$building_rate_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Building rate deleted successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Building rate not found']);
                }
                break;
                
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed for building rates']);
                break;
        }
    }
    ?>