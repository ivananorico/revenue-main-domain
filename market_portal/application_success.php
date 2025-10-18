<?php
session_start();
if (!isset($_SESSION['success'])) {
    header('Location: market_portal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted - Market Stall Portal</title>
    <link rel="stylesheet" href="../citizen_portal/navbar.css">
    <link rel="stylesheet" href="application_success.css">
</head>
<body>
    <?php include '../citizen_portal/navbar.php'; ?>
    
    <div class="portal-container">
        <div class="success-container">
            <div class="success-icon">✓</div>
            <h1>Application Submitted Successfully!</h1>
            <p><?php echo htmlspecialchars($_SESSION['success']); ?></p>
            
            <?php if (isset($_SESSION['application_id'])): ?>
                <div class="application-id">
                    <strong>Application ID:</strong> #<?php echo $_SESSION['application_id']; ?>
                </div>
            <?php endif; ?>
            
            <div class="success-actions">
                <a href="../citizen_portal/market_card/view_documents/view_documents.php" class="btn-secondary">View Application Status</a>
            </div>
        </div>
    </div>
    
    <?php
    // Clear success message after displaying
    unset($_SESSION['success']);
    unset($_SESSION['application_id']);
    ?>
</body>
</html>