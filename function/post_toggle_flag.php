<?php
// post_toggle_flag.php
// AJAX-Endpunkt zum Speichern von Toggle-Flags

require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/repository/ToggleFlagRepository.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/UserSession.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// DB-Verbindung holen
$db = CMSApp::getDb();
if (!($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Keine gültige DB-Verbindung.',
    ]);
    exit;
}

// Login-Prüfung
if (!UserSession::isLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Nicht eingeloggt.',
    ]);
    exit;
}

// Admin-Prüfung (rollenbasiert)
if (!UserSession::isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Keine Admin-Rechte.',
    ]);
    exit;
}

// Nur POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Nur POST erlaubt.',
    ]);
    exit;
}

$key   = $_POST['key']   ?? null;
$value = $_POST['value'] ?? null;

// Parameter prüfen
if (!$key || !in_array($value, ['on', 'off'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige Parameter.',
    ]);
    exit;
}

// Status in DB speichern
$repo   = new ToggleFlagRepository($db);
$userId = UserSession::getUserId();

if ($userId === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Keine User-ID gefunden.',
    ]);
    exit;
}

// Prüfen, ob ein benutzerspezifischer Datensatz existiert
$currentStatus = $repo->getStatus($userId, $key);
if ($currentStatus !== null && $currentStatus !== false) {
    // Benutzerspezifischer Datensatz existiert, wie bisher speichern
    $repo->setStatus($userId, $key, $value === 'on');
    $newState = $repo->getStatus($userId, $key);
} else {
    // Kein benutzerspezifischer Datensatz, globalen Fallback aktualisieren
    $repo->setStatus(0, $key, $value === 'on');
    $newState = $repo->getStatus(0, $key);
}

// Auch in Session setzen
$_SESSION[$key] = $newState;

echo json_encode([
    'success' => true,
    'message' => 'Zustand gespeichert.',
    'key'     => $key,
    'state'   => $newState,
]);