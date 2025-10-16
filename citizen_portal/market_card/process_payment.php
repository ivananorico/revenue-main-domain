<?php
session_start();
require_once '../../db/Market/market_db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle form data - NO SECURITY, NO USER CHECK
$application_id = $_POST['application_id'] ?? null;
$method = $_POST['payment_method'] ?? null;
$phone_number = $_POST['phone_number'] ?? null;
$payment_type = $_POST['payment_type'] ?? null;
$payment_id = $_POST['payment_id'] ?? null;
$payment_ids = $_POST['payment_ids'] ?? null;
$amount = $_POST['amount'] ?? null;

error_log("=== PAYMENT PROCESS STARTED ===");
error_log("Application ID: $application_id, Method: $method, Type: $payment_type, Amount: $amount");

// Basic parameter check
if (!$application_id || !$method || !$payment_type || !$amount) {
    error_log("ERROR: Missing parameters");
    echo "Missing parameters";
    exit;
}

// Simple phone validation
if (in_array($method, ['gcash', 'maya']) && (empty($phone_number) || strlen($phone_number) !== 11)) {
    error_log("ERROR: Invalid phone number");
    echo "Invalid phone number";
    exit;
}

try {
    // Generate reference number
    $ref_number = strtoupper($method) . '-' . time() . '-' . rand(1000, 9999);
    error_log("Generated reference number: $ref_number");

    if ($payment_type === 'application') {
        error_log("Processing APPLICATION FEE payment");
        
        // Get application data - NO USER VALIDATION
        $stmt = $pdo->prepare("
            SELECT af.*, a.*, s.id as stall_id, s.price as monthly_rent, s.class_id, 
                   m.name as market_name, sec.name as section_name, sr.class_name
            FROM application_fee af 
            JOIN applications a ON af.application_id = a.id
            JOIN stalls s ON a.stall_id = s.id
            JOIN maps m ON s.map_id = m.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            JOIN stall_rights sr ON s.class_id = sr.class_id
            WHERE af.application_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $application_id]);
        $application_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application_data) {
            error_log("ERROR: Application data not found");
            echo "Application not found";
            exit;
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update application_fee table
        $stmt = $pdo->prepare("
            UPDATE application_fee 
            SET status = 'paid', 
                payment_date = NOW(), 
                reference_number = :ref,
                payment_method = :method,
                updated_at = NOW()
            WHERE application_id = :id
        ");
        $stmt->execute([
            ':ref' => $ref_number, 
            ':method' => $method,
            ':id' => $application_id
        ]);

        // Update applications table
        $stmt = $pdo->prepare("
            UPDATE applications
            SET status = 'paid',
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $application_id]);

        // Update stall status
        $stmt = $pdo->prepare("
            UPDATE stalls 
            SET status = 'occupied',
                updated_at = NOW()
            WHERE id = :stall_id
        ");
        $stmt->execute([':stall_id' => $application_data['stall_id']]);

        // Generate renter ID
        $renter_id = 'R' . date('Ym') . str_pad($application_id, 4, '0', STR_PAD_LEFT);

        // Create renter record
        $stmt = $pdo->prepare("
            INSERT INTO renters 
            (renter_id, application_id, user_id, stall_id, full_name, contact_number, email, 
             business_name, market_name, stall_number, section_name, class_name, 
             monthly_rent, stall_rights_fee, security_bond, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $renter_id,
            $application_id,
            $application_data['user_id'],
            $application_data['stall_id'],
            $application_data['full_name'],
            $application_data['contact_number'],
            $application_data['email'],
            $application_data['business_name'],
            $application_data['market_name'],
            $application_data['stall_number'],
            $application_data['section_name'],
            $application_data['class_name'],
            $application_data['monthly_rent'],
            $application_data['stall_rights_fee'],
            $application_data['security_bond']
        ]);

        // Create lease contract
        $start_date = date('Y-m-d');
        $end_date = date('Y-12-31');
        $contract_number = 'LC-' . date('Ym') . '-' . str_pad($application_id, 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO lease_contracts 
            (application_id, renter_id, contract_number, start_date, end_date, monthly_rent, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $application_id,
            $renter_id,
            $contract_number,
            $start_date,
            $end_date,
            $application_data['monthly_rent']
        ]);

        // Create stall rights certificate
        $certificate_number = 'SRC-' . date('Ym') . '-' . str_pad($application_id, 5, '0', STR_PAD_LEFT);
        $issue_date = date('Y-m-d');
        $expiry_date = date('Y-12-31');

        $stmt = $pdo->prepare("
            INSERT INTO stall_rights_issued 
            (application_id, renter_id, certificate_number, class_id, issue_date, expiry_date, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $application_id,
            $renter_id,
            $certificate_number,
            $application_data['class_id'],
            $issue_date,
            $expiry_date
        ]);

        $pdo->commit();
        error_log("Application payment completed successfully");

    } else {
        error_log("Processing RENT payment");
        
        // Get renter data - NO USER VALIDATION
        $stmt = $pdo->prepare("
            SELECT r.renter_id
            FROM renters r 
            WHERE r.application_id = :application_id
            LIMIT 1
        ");
        $stmt->execute([':application_id' => $application_id]);
        $renter = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$renter) {
            error_log("ERROR: Renter not found");
            echo "Renter not found";
            exit;
        }

        $pdo->beginTransaction();

        if ($payment_type === 'rent' && $payment_id) {
            // Single month rent payment
            $stmt = $pdo->prepare("
                UPDATE monthly_payments 
                SET status = 'paid', 
                    paid_date = NOW(), 
                    payment_method = :method,
                    reference_number = :ref
                WHERE id = :payment_id 
                AND renter_id = :renter_id
                AND status = 'pending'
            ");
            $stmt->execute([
                ':payment_id' => $payment_id,
                ':renter_id' => $renter['renter_id'],
                ':method' => $method,
                ':ref' => $ref_number
            ]);

        } elseif ($payment_type === 'rent_all' && $payment_ids) {
            // Multiple months rent payment
            $payment_ids_array = explode(',', $payment_ids);
            $placeholders = str_repeat('?,', count($payment_ids_array) - 1) . '?';
            
            $stmt = $pdo->prepare("
                UPDATE monthly_payments 
                SET status = 'paid', 
                    paid_date = NOW(), 
                    payment_method = ?,
                    reference_number = ?
                WHERE id IN ($placeholders) 
                AND renter_id = ?
                AND status = 'pending'
            ");
            
            $params = array_merge([$method, $ref_number], $payment_ids_array, [$renter['renter_id']]);
            $stmt->execute($params);
        }

        $pdo->commit();
        error_log("Rent payment completed successfully");
    }

    // SUCCESS - Redirect to payment success page
    $success_url = 'payment_success.php?application_id=' . $application_id . '&method=' . $method . '&ref=' . $ref_number . '&payment_type=' . $payment_type . '&amount=' . $amount;
    header('Location: ' . $success_url);
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("DATABASE ERROR: " . $e->getMessage());
    echo "Database error: " . $e->getMessage();
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
    exit;
}
?>