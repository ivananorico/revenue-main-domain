<?php
session_start();
require_once '../db/Market/market_db.php';

// Get user data from URL parameters or session
$user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
$name = $_GET['name'] ?? $_SESSION['full_name'] ?? 'Guest';
$email = $_GET['email'] ?? $_SESSION['email'] ?? 'Not set';

if (!$user_id) {
    header('Location: ../citizen_portal/index.php');
    exit;
}

// Fetch all available maps
try {
    $mapsStmt = $pdo->query("SELECT id, name FROM maps ORDER BY name");
    $maps = $mapsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $maps = [];
}

// Get selected map from filter
$selectedMapId = $_GET['map_id'] ?? ($maps[0]['id'] ?? null);

// Fetch stalls for selected map with class AND section information
$stalls = [];
$selectedMap = null;
if ($selectedMapId) {
    try {
        // Get map details
        $mapStmt = $pdo->prepare("SELECT * FROM maps WHERE id = ?");
        $mapStmt->execute([$selectedMapId]);
        $selectedMap = $mapStmt->fetch(PDO::FETCH_ASSOC);

        // Get stalls for this map with class AND section information
        $stallsStmt = $pdo->prepare("
            SELECT s.*, 
                   sr.class_name, 
                   sr.description, 
                   sr.price as class_price,
                   sec.name as section_name
            FROM stalls s 
            LEFT JOIN stall_rights sr ON s.class_id = sr.class_id 
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.map_id = ? 
            ORDER BY s.name
        ");
        $stallsStmt->execute([$selectedMapId]);
        $stalls = $stallsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Stalls data: " . print_r($stalls, true));
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

// Fetch all stall classes for display
try {
    $classesStmt = $pdo->query("SELECT * FROM stall_rights ORDER BY price DESC");
    $stallClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stallClasses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Stall Portal - Municipal Services</title>
    <link rel="stylesheet" href="market_portal.css">
    <link rel="stylesheet" href="../citizen_portal/navbar.css">
      
</head>
<body>
<?php include '../citizen_portal/navbar.php'; ?>

<div class="portal-container">
    <div class="portal-header">
        <h1>Market Stall Portal</h1>
        <p>Browse available market stalls and apply for rental</p>
    </div>

    <div class="user-info">
        <h3>Welcome, <?php echo htmlspecialchars($name); ?>!</h3>
        <p>User ID: <?php echo htmlspecialchars($user_id); ?> | Email: <?php echo htmlspecialchars($email); ?></p>
    </div>

    <div class="filter-section">
        <h3>Select Market Map</h3>
        <form method="GET" class="map-filter">
            <select name="map_id" class="map-select" onchange="this.form.submit()">
                <option value="">Select a map...</option>
                <?php foreach ($maps as $map): ?>
                    <option value="<?php echo $map['id']; ?>" <?php echo ($map['id'] == $selectedMapId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($map['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
            <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        </form>
    </div>

    <?php if ($selectedMap): ?>
    <div class="main-content-wrapper">
        <!-- Left Column - Map Section -->
        <div class="map-column">
            <div class="status-legend">
                <div class="legend-item"><div class="legend-color legend-available"></div><span>Available</span></div>
                <div class="legend-item"><div class="legend-color legend-occupied"></div><span>Occupied</span></div>
                <div class="legend-item"><div class="legend-color legend-reserved"></div><span>Reserved</span></div>
                <div class="legend-item"><div class="legend-color legend-maintenance"></div><span>Maintenance</span></div>
            </div>

            <div class="market-map-display" 
                 style="background-image: url('http://localhost/revenue/<?php echo $selectedMap['file_path']; ?>')">

                <?php foreach ($stalls as $stall): ?>
                    <div class="stall-marker <?php echo htmlspecialchars($stall['status']); ?>" 
                         style="left: <?php echo (float)($stall['pos_x'] ?? 0); ?>px; top: <?php echo (float)($stall['pos_y'] ?? 0); ?>px;"
                         onclick="openStallModal(
                            <?php echo (int)$stall['id']; ?>,
                            '<?php echo htmlspecialchars($stall['name'] ?? 'No Name', ENT_QUOTES); ?>',
                            <?php echo (float)($stall['price'] ?? 0); ?>,
                            '<?php echo htmlspecialchars($stall['status'] ?? 'unknown', ENT_QUOTES); ?>',
                            <?php echo (float)($stall['length'] ?? 0); ?>,
                            <?php echo (float)($stall['width'] ?? 0); ?>,
                            <?php echo (float)($stall['height'] ?? 0); ?>,
                            '<?php echo htmlspecialchars($stall['class_name'] ?? 'No Class', ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($stall['description'] ?? '', ENT_QUOTES); ?>',
                            <?php echo (float)($stall['class_price'] ?? 0); ?>,
                            '<?php echo htmlspecialchars($stall['section_name'] ?? '', ENT_QUOTES); ?>'
                         )">
                        <div class="stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                        <div class="stall-class">Class: <?php echo htmlspecialchars($stall['class_name'] ?? 'N/A'); ?></div>
                        <div class="stall-price">₱<?php echo number_format($stall['price'] ?? 0, 2); ?></div>
                        <?php if (!empty($stall['section_name'])): ?>
                            <div class="stall-section">Section: <?php echo htmlspecialchars($stall['section_name']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Column - Stall Classes -->
        <?php if (!empty($stallClasses)): ?>
        <div class="classes-column">
            <div class="class-info-section">
                <h3>Stall Classes & Pricing</h3>
                <div class="class-cards">
                    <?php foreach ($stallClasses as $class): ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h4>Class <?php echo htmlspecialchars($class['class_name']); ?></h4>
                                <span class="class-price">₱<?php echo number_format($class['price'] ?? 0, 2); ?></span>
                            </div>
                            <p class="class-description"><?php echo htmlspecialchars($class['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <div class="no-stalls"><p>Please select a market map to view available stalls.</p></div>
    <?php endif; ?>

    <div style="text-align: center;">
        <a href="../citizen_portal/dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</div>

<!-- Modal -->
<div class="modal-backdrop" id="stallModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalStallName">Stall Name</h3>
            <div class="market-section-badge" id="modalSectionBadge" style="display: none;">
                Section: <span id="modalStallSection"></span>
            </div>
        </div>
        <div class="stall-details">
            <div class="stall-details-grid">
                <div class="detail-row">
                    <span class="detail-label">Class:</span>
                    <span class="detail-value" id="modalStallClass"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Monthly Rent:</span>
                    <span class="detail-value" id="modalStallPrice"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dimensions:</span>
                    <span class="detail-value" id="modalStallDimensions"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" id="modalStallStatusText"></span>
                </div>
                <div class="detail-row" id="sectionDetailRow" style="display: none;">
                    <span class="detail-label">Market Section:</span>
                    <span class="detail-value" id="modalStallSectionDetail"></span>
                </div>
            </div>

            <div class="section-info" id="sectionInfo" style="display: none;">
                <strong>📍 Market Section Information:</strong> 
                <p id="modalSectionInfo" style="margin: 5px 0 0 0;"></p>
            </div>

            <div class="stall-rights-section" id="stallRightsSection" style="display: none;">
                <div class="stall-rights-title">📋 Stall Rights Information:</div>
                <p id="modalStallRights" style="margin: 5px 0 0 0;"></p>
                <div id="modalClassPriceInfo" style="margin-top: 8px; font-style: italic;"></div>
            </div>
        </div>

        <div class="modal-buttons">
            <form id="applicationForm" method="POST" action="application_new.php" style="display:none;">
                <input type="hidden" name="stall_id" id="formStallId">
                <input type="hidden" name="stall_name" id="formStallName">
                <input type="hidden" name="stall_price" id="formStallPrice">
                <input type="hidden" name="stall_dimensions" id="formStallDimensions">
                <input type="hidden" name="stall_class" id="formStallClass">
                <input type="hidden" name="stall_rights" id="formStallRights">
                <input type="hidden" name="market_section" id="formMarketSection">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            </form>
            <button class="btn-apply" id="applyButton" onclick="applyForStall()">Apply for this Stall</button>
            <button class="btn-cancel" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let currentStallId, currentStallName, currentStallPrice, currentStallDimensions, currentStallClass, currentStallRights, currentClassPrice, currentMarketSection;

function openStallModal(stallId, stallName, price, status, length, width, height, stallClass, stallRights, classPrice, marketSection) {
    console.log('Opening modal with data:', {stallId, stallName, price, status, length, width, height, stallClass, stallRights, classPrice, marketSection});

    currentStallId = stallId;
    currentStallName = stallName;
    currentStallPrice = price;
    currentStallDimensions = `${length}m × ${width}m × ${height}m`;
    currentStallClass = stallClass;
    currentStallRights = stallRights;
    currentClassPrice = classPrice;
    currentMarketSection = marketSection;

    document.getElementById('modalStallName').textContent = stallName || 'No Name';
    document.getElementById('modalStallClass').textContent = stallClass || 'No Class';
    document.getElementById('modalStallPrice').textContent = '₱' + (price || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('modalStallDimensions').textContent = currentStallDimensions;
    document.getElementById('modalStallStatusText').textContent = status.charAt(0).toUpperCase() + status.slice(1);

    // Handle Market Section Display
    const sectionBadge = document.getElementById('modalSectionBadge');
    const sectionElement = document.getElementById('modalStallSection');
    const sectionDetail = document.getElementById('modalStallSectionDetail');
    const sectionInfo = document.getElementById('sectionInfo');
    const sectionInfoText = document.getElementById('modalSectionInfo');
    const sectionDetailRow = document.getElementById('sectionDetailRow');
    
    if (marketSection && marketSection.trim() !== '' && marketSection !== 'null') {
        sectionElement.textContent = marketSection;
        sectionDetail.textContent = marketSection;
        sectionInfoText.textContent = `This stall is located in the ${marketSection} area of the market.`;
        sectionBadge.style.display = 'inline-block';
        sectionInfo.style.display = 'block';
        sectionDetailRow.style.display = 'flex';
    } else {
        sectionBadge.style.display = 'none';
        sectionInfo.style.display = 'none';
        sectionDetailRow.style.display = 'none';
    }

    // Handle Stall Rights Display
    const rightsSection = document.getElementById('stallRightsSection');
    const rightsContent = document.getElementById('modalStallRights');
    const classPriceInfo = document.getElementById('modalClassPriceInfo');

    if (stallRights && stallRights.trim() !== '' && stallRights !== 'null') {
        rightsContent.textContent = stallRights;
        classPriceInfo.textContent = classPrice > 0 ? 
            `Standard price for Class ${stallClass}: ₱${classPrice.toLocaleString('en-US', {minimumFractionDigits: 2})}` : '';
        rightsSection.style.display = 'block';
    } else {
        rightsSection.style.display = 'none';
    }

    // Set form values
    document.getElementById('formStallId').value = stallId;
    document.getElementById('formStallName').value = stallName;
    document.getElementById('formStallPrice').value = price;
    document.getElementById('formStallDimensions').value = currentStallDimensions;
    document.getElementById('formStallClass').value = stallClass;
    document.getElementById('formStallRights').value = stallRights;
    document.getElementById('formMarketSection').value = marketSection;

    // Handle apply button state
    const applyButton = document.getElementById('applyButton');
    if (status === 'available') {
        applyButton.disabled = false;
        applyButton.textContent = 'Apply for this Stall';
        applyButton.className = 'btn-apply';
    } else {
        applyButton.disabled = true;
        applyButton.textContent = 'Not Available';
        applyButton.className = 'btn-apply disabled';
    }

    document.getElementById('stallModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('stallModal').style.display = 'none';
}

function applyForStall() {
    const confirmMessage = `Are you sure you want to apply for "${currentStallName}" (${currentStallClass} - ₱${currentStallPrice.toLocaleString('en-US', {minimumFractionDigits: 2})})?`;
    if (confirm(confirmMessage)) {
        document.getElementById('applicationForm').submit();
    }
}

document.getElementById('stallModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});
</script>

</body>
</html>