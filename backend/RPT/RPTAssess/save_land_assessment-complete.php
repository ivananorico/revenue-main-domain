<?php
// Enable CORS with specific origin
header('Access-Control-Allow-Origin: http://localhost:5174');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log for debugging
file_put_contents('assessment_debug.log', "\n" . date('Y-m-d H:i:s') . " - New request\n", FILE_APPEND);

require_once '../../../db/RPT/rpt_db.php';

try {
    // Get raw input first for debugging
    $raw_input = file_get_contents('php://input');
    file_put_contents('assessment_debug.log', "Raw input: " . $raw_input . "\n", FILE_APPEND);
    
    if (empty($raw_input)) {
        throw new Exception('No input data received');
    }

    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!$input) {
        throw new Exception('Invalid JSON input - could not decode');
    }

    file_put_contents('assessment_debug.log', "Parsed input: " . print_r($input, true) . "\n", FILE_APPEND);

    // Validate input structure
    if (!isset($input['application_id'])) {
        throw new Exception('application_id is required');
    }
    
    if (!isset($input['property_data'])) {
        throw new Exception('property_data is required');
    }

    $application_id = $input['application_id'];
    $property_data = $input['property_data'];

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
        throw new Exception('TDN number is required');
    }

    file_put_contents('assessment_debug.log', "Starting transaction for application: " . $application_id . "\n", FILE_APPEND);

    $pdo->beginTransaction();

    // =========================================================================
    // STEP 1: GET LAND RATE CONFIGURATION AND CALCULATE VALUES
    // =========================================================================
    
    // Get land rate configuration
    $land_rate_sql = "SELECT market_value_per_sqm, land_assessed_lvl FROM land_rate_config WHERE land_use = ?";
    $land_rate_stmt = $pdo->prepare($land_rate_sql);
    $land_rate_stmt->execute([$property_data['land_use']]);
    $land_rate = $land_rate_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$land_rate) {
        throw new Exception('Land use configuration not found for: ' . $property_data['land_use']);
    }

    file_put_contents('assessment_debug.log', "Land rate config: " . print_r($land_rate, true) . "\n", FILE_APPEND);

    // Get tax rate configuration
    $tax_rate_sql = "SELECT tax_rate, sef_rate FROM tax_rate_config ORDER BY effective_year DESC LIMIT 1";
    $tax_rate_stmt = $pdo->prepare($tax_rate_sql);
    $tax_rate_stmt->execute();
    $tax_rate = $tax_rate_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tax_rate) {
        throw new Exception('Tax rate configuration not found');
    }

    file_put_contents('assessment_debug.log', "Tax rate config: " . print_r($tax_rate, true) . "\n", FILE_APPEND);

    // Calculate assessment values
    $lot_area = floatval($property_data['lot_area']);
    $market_value_per_sqm = floatval($land_rate['market_value_per_sqm']);
    $land_assessed_lvl = floatval($land_rate['land_assessed_lvl']);
    $tax_rate_val = floatval($tax_rate['tax_rate']);
    $sef_rate_val = floatval($tax_rate['sef_rate']);

    // Calculate assessed value
    $land_assessed_value = $lot_area * $market_value_per_sqm * $land_assessed_lvl;
    
    // Calculate taxes
    $basic_tax = $land_assessed_value * $tax_rate_val;
    $sef_tax = $land_assessed_value * $sef_rate_val;
    $land_total_tax = $basic_tax + $sef_tax;
    $quarterly_tax = $land_total_tax / 4;

    file_put_contents('assessment_debug.log', "Calculations - Lot Area: $lot_area, Market Value: $market_value_per_sqm, Assessed Level: $land_assessed_lvl\n", FILE_APPEND);
    file_put_contents('assessment_debug.log', "Calculations - Assessed Value: $land_assessed_value, Total Tax: $land_total_tax, Quarterly: $quarterly_tax\n", FILE_APPEND);

    // =========================================================================
    // STEP 2: CREATE LAND RECORD
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

    file_put_contents('assessment_debug.log', "Land record created with ID: " . $land_id . "\n", FILE_APPEND);

    // =========================================================================
    // STEP 3: CREATE LAND ASSESSMENT TAX RECORD
    // =========================================================================
    
    $current_year = date('Y');
    $due_date = $current_year . '-12-31';
    
    $assessment_sql = "INSERT INTO land_assessment_tax 
                      (land_id, assessment_year, land_value_per_sqm, land_assessed_lvl, 
                       land_assessed_value, tax_rate, sef_rate, land_total_tax, status, due_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'current', ?)";
    $assessment_stmt = $pdo->prepare($assessment_sql);
    $assessment_stmt->execute([
        $land_id,
        $current_year,
        $market_value_per_sqm,
        $land_assessed_lvl,
        $land_assessed_value,
        $tax_rate_val,
        $sef_rate_val,
        $land_total_tax,
        $due_date
    ]);
    $land_tax_id = $pdo->lastInsertId();

    file_put_contents('assessment_debug.log', "Land tax record created with ID: " . $land_tax_id . "\n", FILE_APPEND);

    // =========================================================================
    // STEP 4: CREATE TOTAL TAX RECORD
    // =========================================================================
    
    $total_tax_sql = "INSERT INTO total_tax (land_tax_id, total_assessed_value, total_tax, payment_type) 
                      VALUES (?, ?, ?, 'quarterly')";
    $total_tax_stmt = $pdo->prepare($total_tax_sql);
    $total_tax_stmt->execute([
        $land_tax_id,
        $land_assessed_value,
        $land_total_tax
    ]);
    $total_tax_id = $pdo->lastInsertId();

    file_put_contents('assessment_debug.log', "Total tax record created with ID: " . $total_tax_id . "\n", FILE_APPEND);

    // =========================================================================
    // STEP 5: CREATE QUARTERLY PAYMENTS
    // =========================================================================
    
    $due_dates = [
        $current_year . '-03-31',
        $current_year . '-06-30',
        $current_year . '-09-30',
        $current_year . '-12-31'
    ];

    $quarterly_sql = "INSERT INTO quarterly (land_tax_id, quarter_no, tax_amount, penalty, total_tax_amount, status, due_date) 
                      VALUES (?, ?, ?, 0.00, ?, 'unpaid', ?)";
    $quarterly_stmt = $pdo->prepare($quarterly_sql);
    
    foreach ($due_dates as $index => $due_date) {
        $quarterly_stmt->execute([$land_tax_id, $index + 1, $quarterly_tax, $quarterly_tax, $due_date]);
        file_put_contents('assessment_debug.log', "Quarterly payment created for Q" . ($index + 1) . " due: $due_date\n", FILE_APPEND);
    }

    // =========================================================================
    // STEP 6: UPDATE APPLICATION STATUS
    // =========================================================================
    
    $update_sql = "UPDATE rpt_applications SET status = 'assessed', updated_at = NOW() WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$application_id]);

    $pdo->commit();

    file_put_contents('assessment_debug.log', "Transaction committed successfully\n", FILE_APPEND);

    // =========================================================================
    // STEP 7: RETURN SUCCESS RESPONSE WITH CALCULATED VALUES
    // =========================================================================
    
    $response = [
        'status' => 'success',
        'message' => 'Land assessment completed successfully!',
        'data' => [
            'land_id' => $land_id,
            'land_tax_id' => $land_tax_id,
            'total_tax_id' => $total_tax_id,
            'calculations' => [
                'market_value_per_sqm' => $market_value_per_sqm,
                'land_assessed_lvl' => $land_assessed_lvl,
                'land_assessed_value' => $land_assessed_value,
                'tax_rate' => $tax_rate_val,
                'sef_rate' => $sef_rate_val,
                'land_total_tax' => $land_total_tax,
                'quarterly_tax' => $quarterly_tax
            ],
            'quarterly_payments' => [
                'q1_due' => $due_dates[0],
                'q2_due' => $due_dates[1],
                'q3_due' => $due_dates[2],
                'q4_due' => $due_dates[3],
                'amount_per_quarter' => $quarterly_tax
            ]
        ]
    ];

    file_put_contents('assessment_debug.log', "Sending success response\n", FILE_APPEND);
    echo json_encode($response);
    
} catch (Exception $e) {
    file_put_contents('assessment_debug.log', "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        file_put_contents('assessment_debug.log', "Transaction rolled back\n", FILE_APPEND);
    }
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Assessment failed: ' . $e->getMessage()
    ]);
}
?>