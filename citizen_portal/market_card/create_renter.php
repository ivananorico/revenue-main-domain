<?php
session_start();
require_once '../../db/Market/market_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    header("Location: apply_stall.php?error=no_application");
    exit();
}

try {
    // Get application details
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = :application_id");
    $stmt->execute([':application_id' => $application_id]);
    $application = $stmt->fetch();

    if ($application) {
        // Create renter record
        $stmt = $pdo->prepare("
            INSERT INTO renters (application_id, user_id, first_name, middle_name, last_name, business_name, monthly_rent, created_at) 
            VALUES (:application_id, :user_id, :first_name, :middle_name, :last_name, :business_name, 1500.00, NOW())
        ");
        $stmt->execute([
            ':application_id' => $application_id,
            ':user_id' => $_SESSION['user_id'],
            ':first_name' => $application['first_name'],
            ':middle_name' => $application['middle_name'],
            ':last_name' => $application['last_name'],
            ':business_name' => $application['business_name']
        ]);

        $_SESSION['success'] = "Renter record created successfully!";
        header("Location: apply_stall.php?success=renter_created");
    } else {
        header("Location: apply_stall.php?error=application_not_found");
    }
} catch (PDOException $e) {
    header("Location: apply_stall.php?error=create_renter_failed");
}
?>