<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../../db/Market/market_db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON input');

    $application_id = $input['application_id'] ?? null;
    if (!$application_id) throw new Exception('Application ID is required');

    // Fetch application with complete details
    $stmt = $pdo->prepare("
        SELECT a.*, s.class_id, s.price as monthly_rent, 
               sr.class_name, sr.price as stall_rights_price
        FROM applications a 
        JOIN stalls s ON a.stall_id = s.id 
        JOIN stall_rights sr ON s.class_id = sr.class_id
        WHERE a.id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) throw new Exception('Application not found');

    // Validate current status
    if ($application['status'] !== 'pending') {
        throw new Exception("Cannot move application to Payment Phase from current status: {$application['status']}. Application must be in 'pending' status.");
    }

    // Start transaction
    $pdo->beginTransaction();

    // 1. GENERATE UNIQUE NUMBERS
    $contract_number = generateContractNumber($pdo);
    $certificate_number = generateCertificateNumber($pdo);
    $renter_id = generateRenterId($pdo);

    // 2. UPDATE APPLICATION STATUS
    $update_app = $pdo->prepare("
        UPDATE applications 
        SET status = 'payment_phase', updated_at = NOW() 
        WHERE id = ?
    ");
    $update_app->execute([$application_id]);

    // 3. CREATE RENTER RECORD
    $renter_stmt = $pdo->prepare("
        INSERT INTO renters 
        (renter_id, application_id, user_id, stall_id, first_name, middle_name, last_name, 
         contact_number, email, business_name, market_name, stall_number, section_name, 
         class_name, monthly_rent, stall_rights_fee, security_bond, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $renter_stmt->execute([
        $renter_id,
        $application_id,
        $application['user_id'],
        $application['stall_id'],
        $application['first_name'],
        $application['middle_name'],
        $application['last_name'],
        $application['contact_number'],
        $application['email'],
        $application['business_name'],
        $application['market_name'],
        $application['stall_number'],
        $application['market_section'],
        $application['class_name'],
        $application['monthly_rent'],
        $application['stall_rights_price'],
        10000.00 // security_bond
    ]);

    // 4. CREATE LEASE CONTRACT RECORD
    $contract_stmt = $pdo->prepare("
        INSERT INTO lease_contracts 
        (application_id, renter_id, contract_number, start_date, end_date, monthly_rent, status)
        VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, 'active')
    ");
    $contract_stmt->execute([
        $application_id,
        $renter_id,
        $contract_number,
        $application['monthly_rent']
    ]);

    // 5. CREATE STALL RIGHTS ISSUED RECORD
    $rights_stmt = $pdo->prepare("
        INSERT INTO stall_rights_issued 
        (application_id, renter_id, certificate_number, class_id, issue_date, expiry_date, status)
        VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active')
    ");
    $rights_stmt->execute([
        $application_id,
        $renter_id,
        $certificate_number,
        $application['class_id']
    ]);

    // 6. UPDATE STALL STATUS TO OCCUPIED
    $update_stall = $pdo->prepare("
        UPDATE stalls SET status = 'occupied', updated_at = NOW() WHERE id = ?
    ");
    $update_stall->execute([$application['stall_id']]);

    // 7. CALCULATE AND CREATE/UPDATE APPLICATION FEE
    $application_fee = 100.00;
    $security_bond = 10000.00;
    $stall_rights_fee = $application['stall_rights_price'] ?? 0;
    $total_amount = $application_fee + $security_bond + $stall_rights_fee;

    // Check if fee record already exists
    $check_fee_stmt = $pdo->prepare("SELECT id FROM application_fee WHERE application_id = ?");
    $check_fee_stmt->execute([$application_id]);
    $existing_fee = $check_fee_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_fee) {
        // Update existing fee record
        $fee_stmt = $pdo->prepare("
            UPDATE application_fee 
            SET application_fee = ?, security_bond = ?, stall_rights_fee = ?, total_amount = ?, 
                status = 'pending', updated_at = NOW()
            WHERE application_id = ?
        ");
        $fee_stmt->execute([$application_fee, $security_bond, $stall_rights_fee, $total_amount, $application_id]);
    } else {
        // Insert new fee record
        $fee_stmt = $pdo->prepare("
            INSERT INTO application_fee 
            (application_id, application_fee, security_bond, stall_rights_fee, total_amount, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $fee_stmt->execute([$application_id, $application_fee, $security_bond, $stall_rights_fee, $total_amount]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Application moved to Payment Phase with lease contract and stall rights issued',
        'application_id' => $application_id,
        'renter_id' => $renter_id,
        'lease_contract' => [
            'contract_number' => $contract_number,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 year')),
            'monthly_rent' => number_format($application['monthly_rent'], 2)
        ],
        'stall_rights' => [
            'certificate_number' => $certificate_number,
            'class_name' => $application['class_name'],
            'issue_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+1 year'))
        ],
        'fees' => [
            'application_fee' => number_format($application_fee, 2),
            'security_bond' => number_format($security_bond, 2),
            'stall_rights_fee' => number_format($stall_rights_fee, 2),
            'total_amount' => number_format($total_amount, 2)
        ],
        'currency' => 'PHP'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'payment_phase_error'
    ], JSON_PRETTY_PRINT);
}

// Function to generate unique contract number
function generateContractNumber($pdo) {
    $prefix = "CNTR";
    $year = date('Y');
    
    $stmt = $pdo->prepare("SELECT contract_number FROM lease_contracts WHERE contract_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . $year . '%']);
    $last_contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_contract) {
        $last_number = intval(substr($last_contract['contract_number'], -4));
        $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_number = '0001';
    }
    
    return $prefix . $year . $new_number;
}

// Function to generate unique certificate number
function generateCertificateNumber($pdo) {
    $prefix = "SRC";
    $year = date('Y');
    
    $stmt = $pdo->prepare("SELECT certificate_number FROM stall_rights_issued WHERE certificate_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . $year . '%']);
    $last_cert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_cert) {
        $last_number = intval(substr($last_cert['certificate_number'], -4));
        $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_number = '0001';
    }
    
    return $prefix . $year . $new_number;
}

// Function to generate unique renter ID
function generateRenterId($pdo) {
    $prefix = "R";
    $year = date('y'); // Last 2 digits of year
    
    $stmt = $pdo->prepare("SELECT renter_id FROM renters WHERE renter_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . $year . '%']);
    $last_renter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_renter) {
        $last_number = intval(substr($last_renter['renter_id'], -4));
        $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_number = '0001';
    }
    
    return $prefix . $year . $new_number;
}
?>