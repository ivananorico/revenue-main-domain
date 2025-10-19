<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

echo json_encode([
    'status' => 'success',
    'message' => 'Test endpoint is working',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>