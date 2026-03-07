<?php
declare(strict_types=1);

session_start();

// JSON-POST oder GET unterstützen
$lang = null;

// 1) JSON-Body (POST)
$raw = file_get_contents('php://input');
if ($raw) {
    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['language'])) {
        $lang = $data['language'];
    }
}

// 2) Fallback: GET-Parameter
if ($lang === null && isset($_GET['lang'])) {
    $lang = $_GET['lang'];
}

// 3) Validierung (nur einfache Sprachcodes zulassen)
if (is_string($lang) && preg_match('/^[a-zA-Z-]{2,5}$/', $lang)) {
    $_SESSION['language'] = $lang;

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'language' => $lang]);
    exit;
}

// Fehlerfall
header('Content-Type: application/json', true, 400);
echo json_encode(['success' => false]);