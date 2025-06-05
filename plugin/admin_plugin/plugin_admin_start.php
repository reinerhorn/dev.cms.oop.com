<script src="/function/js/chart.js"></script>
<script src="/function/js/charts-loader.js"></script>
 
<?php   
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/UserSession.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prüfen ob eingeloggt
if (!UserSession::isLoggedIn()) {
    echo "Bitte erst einloggen";
    exit;
}

// Begrüßung anzeigen
echo UserSession::showGreeting();
?>