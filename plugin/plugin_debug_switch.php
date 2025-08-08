<?php
require_once __DIR__ . '/../init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/UserSession.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/repository/ToggleFlagRepository.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/templates/component/ToggleSwitch.php';
use component\ToggleSwitch;
use CMSAppFrontend;
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!UserSession::isLoggedIn()) {
    echo "Bitte erst einloggen";
    exit;
}

if (!CMSAppFrontend::isAdmin()) {
    echo "Zugriff verweigert – nur für Administratoren.";
    exit;
}

$repo         = new ToggleFlagRepository(CMSApp::getDb());
$userId       = UserSession::getUserId();
$debugEnabled = $userId !== null ? $repo->getStatus($userId, 'debug_toggle') : false;

// Begrüßung anzeigen
echo "<div class='admin-greeting'>" . UserSession::showGreeting() . "</div>";

// Toggle anzeigen
echo ToggleSwitch::render(
    id: 'debug-toggle',
    name: 'debug_toggle',
    labelOn: 'An',
    labelOff: 'Aus',
    isChecked: $debugEnabled,
    extraClasses: ['debug-toggle'],
    attributes: [
        'data-toggle-key' => 'debug_toggle',
        'data-endpoint'   => '/function/post_toggle_flag.php'
    ]
);
?>
<script src="/function/js/debug-toggle.js" defer></script>