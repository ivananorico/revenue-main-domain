<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'Backend is working!',
    'server_time' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION
]);
?>