<script src="/function/js/debug-toggle.js" defer></script>
<link rel="stylesheet" href="/assets/css/ui-components.css">  
<?php   
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/UserSession.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/templates/component/ToggleSwitch.php";

use component\ToggleSwitch;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!UserSession::isLoggedIn()) {
    echo "Bitte erst einloggen";
    exit;
}

// Begrüßung anzeigen
$greeting = UserSession::showGreeting();
echo "<div class='admin-greeting'>$greeting</div>";

// Check debug toggle status from database and include debug_mode.tpl.php if enabled
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/repository/ToggleFlagRepository.php';
$repo = new ToggleFlagRepository(CMSApp::getDb());
$userId = UserSession::getUserId();
$debugEnabled = false;
if ($userId !== null) {
    $debugEnabled = $repo->getStatus($userId, 'debug_toggle');
}

echo ToggleSwitch::render(
    id: 'debug-toggle',
    name: 'debug_toggle',
    labelOn: 'An',
    labelOff: 'Aus',
    isChecked: $debugEnabled,
    extraClasses: ['debug-toggle'],
    attributes: ['data-toggle' => 'debug']
);

if ($debugEnabled) {
    require $_SERVER['DOCUMENT_ROOT'] . '/templates/debug_mode.tpl.php';
}

?>