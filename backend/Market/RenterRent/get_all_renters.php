<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../db/Market/market_db.php';

try {
    $sql = "
        SELECT 
            r.id,
            r.renter_id,
            r.application_id,
            r.user_id,
            r.stall_id,
            r.full_name,
            r.contact_number,
            r.email,
            r.business_name,
            r.market_name,
            r.stall_number,
            r.section_name,
            r.class_name,
            r.monthly_rent,
            r.stall_rights_fee,
            r.security_bond,
            r.status,
            r.created_at,
            r.updated_at,
            s.name AS stall_name,
            m.name AS map_name,
            sr.class_name AS stall_class,
            sr.price AS stall_rights_price
        FROM renters r
        LEFT JOIN stalls s ON r.stall_id = s.id
        LEFT JOIN maps m ON s.map_id = m.id
        LEFT JOIN stall_rights sr ON r.class_name = sr.class_name
        ORDER BY r.created_at DESC
    ";

    $stmt = $pdo->query($sql);
    $renters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "renters" => $renters,
        "count" => count($renters)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>