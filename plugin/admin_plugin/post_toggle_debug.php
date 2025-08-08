<?php
// post_toggle_debug.php
session_start();

if (!isset($_SESSION['admin_a']) || $_SESSION['admin_a'] != 1) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newState = isset($_POST['debug_toggle']) && $_POST['debug_toggle'] === 'on';
    $_SESSION['debug_enabled'] = $newState;

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'debug_enabled' => $newState
    ]);
    exit;
} else {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt.']);
    exit;
}