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

// Debug-Schalter anzeigen
echo ToggleSwitch::render(
    id: 'debug-toggle',
    name: 'debug_toggle',
    labelOn: 'An',
    labelOff: 'Aus',
    isChecked: $_SESSION['debug_enabled'] ?? false,
    extraClasses: ['debug-toggle'],
    attributes: ['data-toggle' => 'debug']
);

 
?>