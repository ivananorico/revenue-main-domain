<?php
session_start();

// Store the parameters before destroying session if needed for redirect
$params = $_GET;
$user_id = $params['user_id'] ?? null;
$name = $params['name'] ?? null;
$email = $params['email'] ?? null;

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// If there are parameters, redirect back with them
if (!empty($params)) {
    $redirect_url = 'index.php?' . http_build_query($params);
} else {
    $redirect_url = 'index.php';
}

// Redirect to login page with parameters
header('Location: ' . $redirect_url);
exit;
?>