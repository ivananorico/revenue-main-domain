<?php
/**
 * Save Assessment API Endpoint
 * POST /api/business/save_assessment.php
 * 
 * Payload:
 * {
 *   "business_id": int,
 *   "year": int,
 *   "gross_sales": float,
 *   "tax_amount": float,
 *   "fees": [{"fee_id": int, "amount": float, "fee_name": string}],
 *   "discounts": float,
 *   "penalties": float,
 *   "assessor_id": int
 * }
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON input');
}

// Debug: Log the input data
error_log('Save Assessment Input: ' . json_encode($input));

// Validate required fields
$requiredFields = ['business_id', 'year', 'gross_sales', 'tax_amount', 'fees', 'discounts', 'penalties', 'assessor_id'];
if (!validateRequired($input, $requiredFields)) {
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    sendError('Missing required fields: ' . implode(', ', $missingFields));
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();
    
    // Test database connection
    $testQuery = "SELECT 1";
    $db->prepare($testQuery);
    
    $businessId = intval($input['business_id']);
    $year = intval($input['year']);
    $grossSales = floatval($input['gross_sales']);
    $taxAmount = floatval($input['tax_amount']);
    $fees = $input['fees'];
    $discounts = floatval($input['discounts']);
    $penalties = floatval($input['penalties']);
    $assessorId = intval($input['assessor_id']);
    
    // Debug logging
    error_log('Save Assessment Debug - Business ID: ' . $businessId);
    error_log('Save Assessment Debug - Year: ' . $year);
    error_log('Save Assessment Debug - Gross Sales: ' . $grossSales);
    error_log('Save Assessment Debug - Tax Amount: ' . $taxAmount);
    error_log('Save Assessment Debug - Fees Count: ' . count($fees));
    error_log('Save Assessment Debug - Fees Data: ' . json_encode($fees));
    error_log('Save Assessment Debug - Assessor ID: ' . $assessorId);
    error_log('Save Assessment Debug - Discounts: ' . $discounts);
    error_log('Save Assessment Debug - Penalties: ' . $penalties);
    
    // Calculate total fees
    $totalFees = 0;
    if (!is_array($fees)) {
        sendError('Fees must be an array');
    }
    foreach ($fees as $fee) {
        if (!isset($fee['amount']) || !is_numeric($fee['amount'])) {
            sendError('Invalid fee amount: ' . json_encode($fee));
        }
        $totalFees += floatval($fee['amount']);
    }
    
    // Calculate total due
    $totalDue = $taxAmount + $totalFees + $penalties - $discounts;
    
    // Check if assessment already exists for this business and year
    $existingQuery = "SELECT id FROM assessments WHERE business_id = ? AND year = ?";
    $existingResult = $db->prepare($existingQuery, [$businessId, $year]);
    $existingAssessment = $existingResult->fetch();
    
    if ($existingAssessment) {
        // Update existing assessment
        $assessmentId = $existingAssessment['id'];
        
        $updateQuery = "UPDATE assessments SET 
                        gross_sales = ?, 
                        tax_amount = ?, 
                        fees_total = ?, 
                        discounts = ?, 
                        penalties = ?, 
                        total_due = ?, 
                        status = 'assessed',
                        assessed_by = ?,
                        updated_at = NOW()
                        WHERE id = ?";
        
        $updateResult = $db->prepare($updateQuery, [
            $grossSales, $taxAmount, $totalFees, $discounts, $penalties, $totalDue, $assessorId, $assessmentId
        ]);
        
        if (!$updateResult) {
            throw new Exception('Failed to update assessment');
        }
        
        // Delete existing assessment items
        $deleteItemsQuery = "DELETE FROM assessment_items WHERE assessment_id = ?";
        $deleteResult = $db->prepare($deleteItemsQuery, [$assessmentId]);
        
        if (!$deleteResult) {
            throw new Exception('Failed to delete existing assessment items');
        }
        
        logAudit('UPDATE', 'assessments', $assessmentId, null, $input);
        
    } else {
        // Create new assessment
        $insertQuery = "INSERT INTO assessments 
                        (business_id, year, gross_sales, tax_amount, fees_total, 
                         discounts, penalties, total_due, status, assessed_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'assessed', ?)";
        
        $insertResult = $db->prepare($insertQuery, [
            $businessId, $year, $grossSales, $taxAmount, $totalFees, 
            $discounts, $penalties, $totalDue, $assessorId
        ]);
        
        if (!$insertResult) {
            throw new Exception('Failed to create assessment');
        }
        
        $assessmentId = $db->lastInsertId();
        
        if (!$assessmentId) {
            throw new Exception('Failed to get assessment ID');
        }
        
        logAudit('CREATE', 'assessments', $assessmentId, null, $input);
    }
    
    // Insert assessment items
    foreach ($fees as $fee) {
        $feeId = isset($fee['fee_id']) ? intval($fee['fee_id']) : null;
        $amount = floatval($fee['amount']);
        $feeName = $fee['fee_name'] ?? '';
        
        if ($amount > 0) {
            $itemQuery = "INSERT INTO assessment_items (assessment_id, fee_id, fee_name, amount) VALUES (?, ?, ?, ?)";
            $itemResult = $db->prepare($itemQuery, [$assessmentId, $feeId, $feeName, $amount]);
            
            if (!$itemResult) {
                throw new Exception('Failed to insert assessment item: ' . $feeName);
            }
        }
    }
    
    // Update business last year gross sales
    $updateBusinessQuery = "UPDATE businesses SET 
                            last_year_gross = ? 
                            WHERE id = ?";
    
    $businessUpdateResult = $db->prepare($updateBusinessQuery, [$grossSales, $businessId]);
    
    if (!$businessUpdateResult) {
        throw new Exception('Failed to update business gross sales');
    }
    
    $db->commit();
    
    sendSuccess([
        'assessment_id' => $assessmentId,
        'total_due' => $totalDue,
        'message' => 'Assessment saved successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
