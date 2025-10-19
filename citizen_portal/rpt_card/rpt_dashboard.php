<?php
session_start();
require_once '../../db/RPT/rpt_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real Property Tax System</title>
    <link rel="stylesheet" href="../../citizen_portal/navbar.css">
    <link rel="stylesheet" href="rpt_dashboard.css">
</head>
<body>
    <?php include '../../citizen_portal/navbar.php'; ?>
    
    <div class="rpt-dashboard-container">
        <div class="rpt-header">
            <h1>Real Property Tax Collection System</h1>
            <p>Manage your property taxes and applications</p>
        </div>

        <div class="rpt-cards-container">
            <!-- Card 1: Register RPT -->
            <div class="rpt-card">
                <div class="card-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div class="card-content">
                    <h3>Register Property</h3>
                    <p>Register your property for tax assessment and obtain your Tax Declaration</p>
                    <a href="../rpt_card/register_rpt/register_rpt.php" class="card-button">
                        Register Now
                    </a>
                </div>
            </div>

            <!-- Card 2: Application -->
            <div class="rpt-card">
                <div class="card-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="card-content">
                    <h3>Applications</h3>
                    <p>View and manage your property tax applications and status</p>
                    <a href="../rpt_card/rpt_application/rpt_application.php" class="card-button">
                        View Applications
                    </a>
                </div>
            </div>

            <!-- Card 3: Pay Tax -->
            <div class="rpt-card">
                <div class="card-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="card-content">
                    <h3>Pay Tax</h3>
                    <p>Pay your real property tax online securely</p>
                    <a href="pay_tax.php" class="card-button">
                        Pay Tax Now
                    </a>
                </div>
            </div>
        </div>

        <div class="rpt-footer">
            <p>Need help? Contact the Municipal Assessor's Office</p>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>