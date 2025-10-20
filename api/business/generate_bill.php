<?php
/**
 * Generate Bill API Endpoint
 * GET /api/business/generate_bill.php
 * 
 * Parameters:
 * - assessment_id: ID of the assessment to generate bill for
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

// Validate required parameter
if (!isset($_GET['assessment_id']) || empty($_GET['assessment_id'])) {
    sendError('Assessment ID is required');
}

$assessmentId = intval($_GET['assessment_id']);

try {
    $db = Database::getInstance();
    
    // Get assessment details with business and owner information
    $query = "SELECT 
                a.id as assessment_id,
                a.year,
                a.gross_sales,
                a.tax_amount,
                a.fees_total,
                a.discounts,
                a.penalties,
                a.total_due,
                a.status,
                a.created_at,
                b.business_name,
                b.tin,
                b.barangay,
                b.address as business_address,
                b.business_type,
                o.full_name,
                o.email,
                o.contact_number
              FROM assessments a
              INNER JOIN businesses b ON a.business_id = b.id
              INNER JOIN owners o ON b.owner_id = o.id
              WHERE a.id = ?";
    
    $result = $db->prepare($query, [$assessmentId]);
    $assessment = $result->fetch();
    
    if (!$assessment) {
        sendError('Assessment not found', 404);
    }
    
    // Get assessment items
    $itemsQuery = "SELECT 
                     ai.id as item_id,
                     ai.fee_id,
                     ai.fee_name,
                     ai.amount,
                     rf.fee_code,
                     rf.department
                   FROM assessment_items ai
                   LEFT JOIN regulatory_fees rf ON ai.fee_id = rf.id
                   WHERE ai.assessment_id = ?
                   ORDER BY rf.department, ai.fee_name";
    
    $itemsResult = $db->prepare($itemsQuery, [$assessmentId]);
    $items = $itemsResult->fetchAll();
    
    // Get payment history
    $paymentsQuery = "SELECT 
                        p.id as payment_id,
                        p.amount_paid,
                        p.payment_method,
                        p.or_number,
                        p.paid_at,
                        u.full_name as paid_by_name
                      FROM payments p
                      LEFT JOIN users u ON p.paid_by = u.id
                      WHERE p.assessment_id = ?
                      ORDER BY p.paid_at DESC";
    
    $paymentsResult = $db->prepare($paymentsQuery, [$assessmentId]);
    $payments = $paymentsResult->fetchAll();
    
    // Calculate total paid
    $totalPaid = 0;
    foreach ($payments as $payment) {
        $totalPaid += floatval($payment['amount_paid']);
    }
    
    $balance = $assessment['total_due'] - $totalPaid;
    
    // Generate HTML bill
    $html = generateBillHTML($assessment, $items, $payments, $totalPaid, $balance);
    
    // Set headers for HTML output
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $html;
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}

/**
 * Generate HTML bill template
 */
function generateBillHTML($assessment, $items, $payments, $totalPaid, $balance) {
    $currentDate = date('F j, Y');
    $dueDate = isset($assessment['due_date']) && $assessment['due_date'] ? 
        date('F j, Y', strtotime($assessment['due_date'])) : 
        date('F j, Y', strtotime('+30 days'));
    $assessedDate = isset($assessment['assessed_at']) && $assessment['assessed_at'] ? 
        date('F j, Y', strtotime($assessment['assessed_at'])) : 
        date('F j, Y', strtotime($assessment['created_at']));
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Tax Assessment Bill</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .bill-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #0b3d91;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #0b3d91;
            margin: 0;
            font-size: 28px;
        }
        .header h2 {
            color: #1e66d0;
            margin: 10px 0 0 0;
            font-size: 18px;
            font-weight: normal;
        }
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .bill-details, .business-details {
            flex: 1;
        }
        .bill-details h3, .business-details h3 {
            color: #0b3d91;
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .bill-details p, .business-details p {
            margin: 5px 0;
            color: #333;
        }
        .assessment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .assessment-table th, .assessment-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .assessment-table th {
            background-color: #0b3d91;
            color: white;
            font-weight: bold;
        }
        .assessment-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .totals {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .totals h3 {
            color: #0b3d91;
            margin: 0 0 15px 0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-weight: bold;
        }
        .total-row.final {
            border-top: 2px solid #0b3d91;
            padding-top: 10px;
            font-size: 18px;
            color: #0b3d91;
        }
        .payments-section {
            margin-top: 30px;
        }
        .payments-section h3 {
            color: #0b3d91;
            margin-bottom: 15px;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payments-table th, .payments-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .payments-table th {
            background-color: #1e66d0;
            color: white;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-overdue { background-color: #f8d7da; color: #721c24; }
        .status-assessed { background-color: #cce5ff; color: #004085; }
        @media print {
            body { background-color: white; }
            .bill-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="bill-container">
        <div class="header">
            <h1>BUSINESS TAX ASSESSMENT</h1>
            <h2>Municipal Government</h2>
        </div>
        
        <div class="bill-info">
            <div class="bill-details">
                <h3>Bill Information</h3>
                <p><strong>Assessment ID:</strong> ' . $assessment['assessment_id'] . '</p>
                <p><strong>Assessment Year:</strong> ' . ($assessment['year'] ?? 'N/A') . '</p>
                <p><strong>Bill Date:</strong> ' . $currentDate . '</p>
                <p><strong>Due Date:</strong> ' . $dueDate . '</p>
                <p><strong>Assessed Date:</strong> ' . $assessedDate . '</p>
                <p><strong>Status:</strong> <span class="status-badge status-' . strtolower($assessment['status']) . '">' . $assessment['status'] . '</span></p>
            </div>
            
            <div class="business-details">
                <h3>Business Information</h3>
                <p><strong>Business Name:</strong> ' . htmlspecialchars($assessment['business_name']) . '</p>
                <p><strong>TIN Number:</strong> ' . htmlspecialchars($assessment['tin'] ?? 'N/A') . '</p>
                <p><strong>Business Type:</strong> ' . htmlspecialchars($assessment['business_type']) . '</p>
                <p><strong>Barangay:</strong> ' . htmlspecialchars($assessment['barangay']) . '</p>
                <p><strong>Address:</strong> ' . htmlspecialchars($assessment['business_address']) . '</p>
                <p><strong>Owner:</strong> ' . htmlspecialchars($assessment['full_name'] ?? 'N/A') . '</p>
            </div>
        </div>
        
        <table class="assessment-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gross Sales (' . ($assessment['year'] ?? 'N/A') . ')</td>
                    <td>₱' . number_format($assessment['gross_sales'], 2) . '</td>
                </tr>
                <tr>
                    <td>Business Tax (' . ($assessment['year'] ?? 'N/A') . ')</td>
                    <td>₱' . number_format($assessment['tax_amount'], 2) . '</td>
                </tr>';
    
    // Add regulatory fees
    foreach ($items as $item) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($item['fee_name'] ?? '') . ' (' . htmlspecialchars($item['department'] ?? '') . ')</td>
                    <td>₱' . number_format($item['amount'] ?? 0, 2) . '</td>
                  </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="totals">
            <h3>Assessment Summary</h3>
            <div class="total-row">
                <span>Business Tax:</span>
                <span>₱' . number_format($assessment['tax_amount'], 2) . '</span>
            </div>
            <div class="total-row">
                <span>Regulatory Fees:</span>
                <span>₱' . number_format($assessment['fees_total'] ?? 0, 2) . '</span>
            </div>
            <div class="total-row">
                <span>Penalties:</span>
                <span>₱' . number_format($assessment['penalties'], 2) . '</span>
            </div>
            <div class="total-row">
                <span>Discounts:</span>
                <span>-₱' . number_format($assessment['discounts'], 2) . '</span>
            </div>
            <div class="total-row final">
                <span>Total Due:</span>
                <span>₱' . number_format($assessment['total_due'], 2) . '</span>
            </div>';
    
    if ($totalPaid > 0) {
        $html .= '<div class="total-row">
                    <span>Total Paid:</span>
                    <span>₱' . number_format($totalPaid, 2) . '</span>
                  </div>
                  <div class="total-row final">
                    <span>Balance:</span>
                    <span>₱' . number_format($balance, 2) . '</span>
                  </div>';
    }
    
    $html .= '</div>';
    
    // Add payments section if there are payments
    if (!empty($payments)) {
        $html .= '<div class="payments-section">
                    <h3>Payment History</h3>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>OR Number</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($payments as $payment) {
            $html .= '<tr>
                        <td>' . date('M j, Y', strtotime($payment['paid_at'] ?? $payment['created_at'] ?? 'now')) . '</td>
                        <td>₱' . number_format($payment['amount_paid'], 2) . '</td>
                        <td>' . htmlspecialchars($payment['payment_method']) . '</td>
                        <td>' . htmlspecialchars($payment['or_number']) . '</td>
                        <td>' . htmlspecialchars($payment['notes'] ?? '') . '</td>
                      </tr>';
        }
        
        $html .= '</tbody>
                    </table>
                  </div>';
    }
    
    $html .= '<div class="footer">
                <p>This is an official assessment bill. Please pay on or before the due date to avoid penalties.</p>
                <p>For inquiries, please contact the Municipal Treasurer\'s Office.</p>
                <p>Generated on ' . $currentDate . '</p>
              </div>
    </div>
</body>
</html>';
    
    return $html;
}
?>
