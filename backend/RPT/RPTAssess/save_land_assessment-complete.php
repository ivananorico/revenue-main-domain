<?php
// Enable CORS with specific origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../db/RPT/rpt_db.php';

try {
    // Get the raw input first
    $raw_input = file_get_contents('php://input');
    
    // Log for debugging
    error_log("=== ASSESSMENT START ===");
    error_log("Raw input received: " . $raw_input);
    
    // Check if we got any input at all
    if (empty($raw_input)) {
        throw new Exception('No data received from client');
    }
    
    // Try to decode JSON
    $input = json_decode($raw_input, true);
    $json_error = json_last_error();
    
    if ($json_error !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    if ($input === null) {
        throw new Exception('JSON decoded to null');
    }

    // Validate required fields exist
    if (!isset($input['application_id'])) {
        throw new Exception('application_id is missing in the input');
    }
    
    if (!isset($input['property_type'])) {
        throw new Exception('property_type is missing in the input');
    }
    
    if (!isset($input['property_data'])) {
        throw new Exception('property_data is missing in the input');
    }

    $application_id = $input['application_id'];
    $property_type = $input['property_type'];
    $property_data = $input['property_data'];
    $building_data = $input['building_data'] ?? null;

    // Validate required fields
    if (empty($application_id)) {
        throw new Exception('Application ID is required');
    }
    
    if (empty($property_data['lot_area'])) {
        throw new Exception('Lot area is required');
    }
    
    if (empty($property_data['land_use'])) {
        throw new Exception('Land use is required');
    }

    if (empty($property_data['location'])) {
        throw new Exception('Location is required');
    }

    if (empty($property_data['barangay'])) {
        throw new Exception('Barangay is required');
    }

    if (empty($property_data['municipality'])) {
        throw new Exception('Municipality is required');
    }

    if (empty($property_data['tdn_no'])) {
        throw new Exception('Land TDN number is required');
    }

    // Validate building data if property type includes building
    if ($property_type === 'land_with_house') {
        if (!$building_data) {
            throw new Exception('Building data is required for land with house');
        }
        if (empty($building_data['building_area'])) {
            throw new Exception('Building area is required');
        }
        if (empty($building_data['building_type'])) {
            throw new Exception('Building type is required');
        }
        if (empty($building_data['construction_type'])) {
            throw new Exception('Construction type is required');
        }
        if (empty($building_data['tdn_no'])) {
            throw new Exception('Building TDN number is required');
        }
    }

    $pdo->beginTransaction();

    // =========================================================================
    // STEP 1: GET CONFIGURATIONS FROM DATABASE
    // =========================================================================
    
    // Get land rate configuration
    $land_rate_sql = "SELECT market_value_per_sqm, land_assessed_lvl FROM land_rate_config WHERE land_use = ?";
    $land_rate_stmt = $pdo->prepare($land_rate_sql);
    $land_rate_stmt->execute([$property_data['land_use']]);
    $land_rate = $land_rate_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$land_rate) {
        throw new Exception('Land use configuration not found for: ' . $property_data['land_use']);
    }

    // Get tax rate configuration
    $tax_rate_sql = "SELECT tax_rate, sef_rate FROM tax_rate_config LIMIT 1";
    $tax_rate_stmt = $pdo->prepare($tax_rate_sql);
    $tax_rate_stmt->execute();
    $tax_rate = $tax_rate_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tax_rate) {
        throw new Exception('Tax rate configuration not found');
    }

    // =========================================================================
    // STEP 2: CALCULATE LAND ASSESSMENT VALUES
    // =========================================================================
    
    $lot_area = floatval($property_data['lot_area']);
    $land_market_value_per_sqm = floatval($land_rate['market_value_per_sqm']);
    $land_assessed_lvl = floatval($land_rate['land_assessed_lvl']);
    $tax_rate_val = floatval($tax_rate['tax_rate']) / 100; // Convert percentage to decimal
    $sef_rate_val = floatval($tax_rate['sef_rate']) / 100; // Convert percentage to decimal

    // Calculate land assessed value
    $land_assessed_value = $lot_area * $land_market_value_per_sqm * $land_assessed_lvl;
    
    // Calculate land taxes
    $land_basic_tax = $land_assessed_value * $tax_rate_val;
    $land_sef_tax = $land_assessed_value * $sef_rate_val;
    $land_total_tax = $land_basic_tax + $land_sef_tax;

    // =========================================================================
    // STEP 3: CREATE LAND RECORD AND TAXES
    // =========================================================================
    
    $land_sql = "INSERT INTO land (application_id, location, barangay, municipality, lot_area, land_use, tdn_no, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
    $land_stmt = $pdo->prepare($land_sql);
    $land_stmt->execute([
        $application_id,
        $property_data['location'],
        $property_data['barangay'],
        $property_data['municipality'],
        $lot_area,
        $property_data['land_use'],
        $property_data['tdn_no']
    ]);
    $land_id = $pdo->lastInsertId();

    // Create land assessment tax record
    $current_year = date('Y');
    $due_date = $current_year . '-12-31';
    
    $land_assessment_sql = "INSERT INTO land_assessment_tax 
                      (land_id, assessment_year, land_value_per_sqm, land_assessed_lvl, 
                       land_assessed_value, tax_rate, sef_rate, land_total_tax, status, due_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'current', ?)";
    $land_assessment_stmt = $pdo->prepare($land_assessment_sql);
    $land_assessment_stmt->execute([
        $land_id,
        $current_year,
        $land_market_value_per_sqm,
        $land_assessed_lvl,
        $land_assessed_value,
        $tax_rate_val * 100, // Store as percentage
        $sef_rate_val * 100, // Store as percentage
        $land_total_tax,
        $due_date
    ]);
    $land_tax_id = $pdo->lastInsertId();

    // =========================================================================
    // STEP 4: HANDLE BUILDING ASSESSMENT IF APPLICABLE
    // =========================================================================
    
    $building_id = null;
    $build_tax_id = null;
    $building_assessed_value = 0;
    $building_total_tax = 0;
    $building_market_value_per_sqm = 0;
    $building_assessed_lvl = 0;

    if ($property_type === 'land_with_house' && $building_data) {
        // Get building rate configuration
        $building_rate_sql = "SELECT market_value_per_sqm, building_assessed_lvl 
                             FROM building_rate_config 
                             WHERE building_type = ? AND construction_type = ?";
        $building_rate_stmt = $pdo->prepare($building_rate_sql);
        $building_rate_stmt->execute([$building_data['building_type'], $building_data['construction_type']]);
        $building_rate = $building_rate_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$building_rate) {
            throw new Exception('Building rate configuration not found for: ' . $building_data['building_type'] . ' - ' . $building_data['construction_type']);
        }

        // Calculate building assessment values
        $building_area = floatval($building_data['building_area']);
        $building_market_value_per_sqm = floatval($building_rate['market_value_per_sqm']);
        $building_assessed_lvl = floatval($building_rate['building_assessed_lvl']);

        // Calculate building assessed value
        $building_assessed_value = $building_area * $building_market_value_per_sqm * $building_assessed_lvl;
        
        // Calculate building taxes
        $building_basic_tax = $building_assessed_value * $tax_rate_val;
        $building_sef_tax = $building_assessed_value * $sef_rate_val;
        $building_total_tax = $building_basic_tax + $building_sef_tax;

        // Create building record
        $building_sql = "INSERT INTO building (application_id, land_id, location, barangay, municipality, 
                         building_type, building_area, construction_type, year_built, number_of_storeys, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
        $building_stmt = $pdo->prepare($building_sql);
        $building_stmt->execute([
            $application_id,
            $land_id,
            $property_data['location'],
            $property_data['barangay'],
            $property_data['municipality'],
            $building_data['building_type'],
            $building_area,
            $building_data['construction_type'],
            $building_data['year_built'],
            $building_data['number_of_storeys'] ?? 1
        ]);
        $building_id = $pdo->lastInsertId();

        // Create building assessment tax record
        $building_assessment_sql = "INSERT INTO building_assessment_tax 
                              (building_id, assessment_year, building_value_per_sqm, building_assessed_lvl, 
                               building_assessed_value, tax_rate, sef_rate, building_total_tax, status, due_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'current', ?)";
        $building_assessment_stmt = $pdo->prepare($building_assessment_sql);
        $building_assessment_stmt->execute([
            $building_id,
            $current_year,
            $building_market_value_per_sqm,
            $building_assessed_lvl,
            $building_assessed_value,
            $tax_rate_val * 100, // Store as percentage
            $sef_rate_val * 100, // Store as percentage
            $building_total_tax,
            $due_date
        ]);
        $build_tax_id = $pdo->lastInsertId();
    }

    // =========================================================================
    // STEP 5: CALCULATE TOTALS AND CREATE QUARTERLY PAYMENTS FOR REMAINING QUARTERS
    // =========================================================================
    
    // Calculate total assessed value (land + building)
    $total_assessed_value = $land_assessed_value + $building_assessed_value;
    
    // Calculate total annual tax (land tax + building tax)
    $total_annual_tax = $land_total_tax + $building_total_tax;
    
    // Determine current quarter and remaining quarters
    $current_month = (int)date('n');
    $current_quarter = ceil($current_month / 3);
    
    // Calculate remaining quarters (from current quarter to Q4)
    $remaining_quarters = 5 - $current_quarter; // 5 because quarters are 1-4
    
    // Calculate quarterly tax for remaining quarters
    $total_quarterly_tax = $total_annual_tax / 4;
    $remaining_annual_tax = $total_quarterly_tax * $remaining_quarters;

    // Define quarterly due dates
    $quarter_due_dates = [
        1 => $current_year . '-03-31',
        2 => $current_year . '-06-30', 
        3 => $current_year . '-09-30',
        4 => $current_year . '-12-31'
    ];

    // Create quarterly payments only for remaining quarters
    $quarterly_sql = "INSERT INTO quarterly (land_tax_id, quarter_no, tax_amount, penalty, total_tax_amount, status, due_date) 
                      VALUES (?, ?, ?, 0.00, ?, 'unpaid', ?)";
    $quarterly_stmt = $pdo->prepare($quarterly_sql);
    
    $quarters_created = 0;
    for ($quarter = $current_quarter; $quarter <= 4; $quarter++) {
        $quarterly_stmt->execute([
            $land_tax_id, 
            $quarter, 
            $total_quarterly_tax, 
            $total_quarterly_tax, 
            $quarter_due_dates[$quarter]
        ]);
        $quarters_created++;
    }

    // =========================================================================
    // STEP 6: CREATE TOTAL TAX RECORD
    // =========================================================================
    
    $total_tax_sql = "INSERT INTO total_tax (land_tax_id, build_tax_id, total_assessed_value, total_tax, payment_type) 
                      VALUES (?, ?, ?, ?, 'quarterly')";
    $total_tax_stmt = $pdo->prepare($total_tax_sql);
    $total_tax_stmt->execute([
        $land_tax_id,
        $build_tax_id,
        $total_assessed_value,
        $remaining_annual_tax // Store only the remaining tax for this year
    ]);
    $total_tax_id = $pdo->lastInsertId();

    // =========================================================================
    // STEP 7: UPDATE APPLICATION STATUS
    // =========================================================================
    
    $update_sql = "UPDATE rpt_applications SET status = 'assessed', updated_at = NOW() WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$application_id]);

    $pdo->commit();

    // =========================================================================
    // STEP 8: RETURN SUCCESS RESPONSE WITH CALCULATED VALUES
    // =========================================================================
    
    $response_data = [
        'land_id' => $land_id,
        'land_tax_id' => $land_tax_id,
        'total_tax_id' => $total_tax_id,
        'calculations' => [
            'land' => [
                'market_value_per_sqm' => $land_market_value_per_sqm,
                'land_assessed_lvl' => $land_assessed_lvl,
                'land_assessed_value' => $land_assessed_value,
                'tax_rate' => $tax_rate_val * 100, // Return as percentage
                'sef_rate' => $sef_rate_val * 100, // Return as percentage
                'land_basic_tax' => $land_basic_tax,
                'land_sef_tax' => $land_sef_tax,
                'land_total_tax' => $land_total_tax,
                'quarterly_tax' => $land_total_tax / 4 // Individual land quarterly
            ],
            'total' => [
                'total_assessed_value' => $total_assessed_value,
                'total_annual_tax' => $total_annual_tax,
                'remaining_annual_tax' => $remaining_annual_tax,
                'total_quarterly_tax' => $total_quarterly_tax,
                'current_quarter' => $current_quarter,
                'quarters_created' => $quarters_created
            ]
        ]
    ];

    // Add building data if applicable
    if ($property_type === 'land_with_house' && $building_data) {
        $response_data['building_id'] = $building_id;
        $response_data['build_tax_id'] = $build_tax_id;
        $response_data['calculations']['building'] = [
            'building_value_per_sqm' => $building_market_value_per_sqm,
            'building_assessed_lvl' => $building_assessed_lvl,
            'building_assessed_value' => $building_assessed_value,
            'tax_rate' => $tax_rate_val * 100, // Return as percentage
            'sef_rate' => $sef_rate_val * 100, // Return as percentage
            'building_basic_tax' => $building_basic_tax,
            'building_sef_tax' => $building_sef_tax,
            'building_total_tax' => $building_total_tax,
            'quarterly_tax' => $building_total_tax / 4 // Individual building quarterly
        ];
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Property assessment completed successfully!',
        'data' => $response_data
    ]);
    
    error_log("=== ASSESSMENT END - SUCCESS ===");

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("=== ASSESSMENT END - ERROR: " . $e->getMessage() . " ===");
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Assessment failed: ' . $e->getMessage()
    ]);
}
?>