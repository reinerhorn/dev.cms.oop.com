<?php
require_once __DIR__ . '/../init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/UserSession.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/repository/ToggleFlagRepository.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/templates/component/ToggleSwitch.php';

use component\ToggleSwitch;

// Session starten, falls nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login prüfen
if (!UserSession::isLoggedIn()) {
    echo "Bitte erst einloggen.";
    exit;
}

// Admin prüfen (einheitlich wie in post_toggle_flag.php)
if (!UserSession::isAdmin()) {
    echo "Zugriff verweigert – nur für Administratoren.";
    exit;
}

$repo   = new ToggleFlagRepository(CMSApp::getDb());
$userId = UserSession::getUserId();

// Standardwert
$debugEnabled = false;

// Erst benutzerspezifischen Status prüfen
if ($userId !== null) {
    $userStatus = $repo->getStatus($userId, 'debug_toggle');
    if ($userStatus !== null) {
        $debugEnabled = (bool) $userStatus;
        error_log("🔍 User-spezifischer Debug-Status geladen (UserID {$userId}): " . ($debugEnabled ? 'AN' : 'AUS'));
    }
}

// Falls kein Benutzerwert gefunden → globalen Status laden
if ($debugEnabled === false && $repo->getStatus(0, 'debug_toggle') !== null) {
    $debugEnabled = (bool) $repo->getStatus(0, 'debug_toggle');
    error_log("🔍 Globaler Debug-Status geladen: " . ($debugEnabled ? 'AN' : 'AUS'));
}

echo UserSession::showGreeting();

// Switch rendern
echo ToggleSwitch::render(
    id: 'debug-toggle',
    name: 'debug_toggle',
    labelOn: 'An',
    labelOff: 'Aus',
    isChecked: $debugEnabled === true,
    extraClasses: ['debug-toggle'],
    attributes: [
        'data-toggle-key' => 'debug_toggle',
        'data-endpoint'   => '/function/post_toggle_flag.php'
    ]
);

if ($debugEnabled === true) {
    $templatePath = $_SERVER['DOCUMENT_ROOT'] . '/templates/debug_mode.tpl.php';
    if (file_exists($templatePath)) {
        include $templatePath;
    } else {
        echo "<div class='debug-template-warning'>⚠ Debug-Template nicht gefunden.</div>";
    }
}
?>
<script src="/function/js/debug-toggle.js" defer></script>