<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../../db/Market/market_db.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit(0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$application_id = $input['application_id'] ?? null;
$start_date = $input['start_date'] ?? date('Y-m-d');

if (!$application_id) {
    echo json_encode(['success' => false, 'message' => 'Application ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get renter details - check for documents_submitted status since payment is already done
    $stmt = $pdo->prepare("
        SELECT 
            r.renter_id,
            r.monthly_rent,
            r.application_id,
            a.status,
            s.price as stall_price
        FROM renters r
        JOIN applications a ON r.application_id = a.id
        JOIN stalls s ON r.stall_id = s.id
        WHERE r.application_id = ? AND a.status = 'documents_submitted'
    ");
    $stmt->execute([$application_id]);
    $renter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$renter) {
        throw new Exception('Renter not found or application not ready for approval');
    }

    // Calculate lease period (until end of current year)
    $startDateObj = new DateTime($start_date);
    $endOfYear = new DateTime($startDateObj->format('Y') . '-12-31');
    
    $lease_start_date = $startDateObj->format('Y-m-d');
    $lease_end_date = $endOfYear->format('Y-m-d');

    // Calculate total months (including current month)
    $interval = $startDateObj->diff($endOfYear);
    $totalMonths = ($interval->y * 12) + $interval->m + 1; // +1 to include current month

    // Generate monthly payments with prorated first month
    $monthlyPayments = [];
    $monthlyRent = floatval($renter['monthly_rent']);
    
    // First, delete any existing monthly payments for this renter to avoid duplicates
    $deleteStmt = $pdo->prepare("DELETE FROM monthly_payments WHERE renter_id = ?");
    $deleteStmt->execute([$renter['renter_id']]);

    for ($i = 0; $i < $totalMonths; $i++) {
        $currentMonth = date('Y-m', strtotime("+$i months", strtotime($lease_start_date)));
        $dueDate = date('Y-m-05', strtotime($currentMonth));
        
        // Calculate amount for this month
        $amount = $monthlyRent;
        
        // If it's the first month (current month), calculate prorated amount
        if ($i === 0) {
            $daysInMonth = (int)date('t', strtotime($lease_start_date));
            $daysRemaining = $daysInMonth - (int)date('j', strtotime($lease_start_date)) + 1;
            
            // Calculate prorated amount: (monthly_rent / days_in_month) * days_remaining
            $dailyRate = $monthlyRent / $daysInMonth;
            $amount = round($dailyRate * $daysRemaining, 2);
        }
        
        // Insert monthly payment WITHOUT notes column
        $paymentStmt = $pdo->prepare("
            INSERT INTO monthly_payments (renter_id, month_year, due_date, amount, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");

        $paymentStmt->execute([
            $renter['renter_id'],
            $currentMonth,
            $dueDate,
            $amount
        ]);

        $monthlyPayments[] = [
            'month_year' => $currentMonth,
            'due_date' => $dueDate,
            'amount' => $amount,
            'days_remaining' => ($i === 0) ? $daysRemaining : null
        ];
    }

    // Update application status to approved
    $updateStmt = $pdo->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$application_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Application approved successfully! Monthly payments generated.',
        'renter_id' => $renter['renter_id'],
        'lease_details' => [
            'start_date' => $lease_start_date,
            'end_date' => $lease_end_date,
            'total_months' => $totalMonths,
            'monthly_rent' => $monthlyRent
        ],
        'first_month_prorated' => [
            'days_remaining' => $daysRemaining,
            'days_in_month' => $daysInMonth,
            'prorated_amount' => $monthlyPayments[0]['amount'],
            'full_month_amount' => $monthlyRent
        ],
        'payments_created' => count($monthlyPayments),
        'payments' => $monthlyPayments
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>