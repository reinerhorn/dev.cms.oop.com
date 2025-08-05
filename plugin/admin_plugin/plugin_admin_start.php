<script src="/function/js/chart.js"></script>
<script src="/function/js/charts-loader.js"></script>
 
<?php  
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/security/UserSession.php'; 
 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prüfen ob eingeloggt
if (!UserSession::isLoggedIn()) {
    echo "Bitte erst einloggen";
    exit;
}

// Admin-Status setzen
if (!empty($_SESSION['admin']) && $_SESSION['admin'] == 1) {
    $_SESSION['admin_a'] = 1;
} else {
    $_SESSION['admin_a'] = 0;
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/plugin/admin_greeting.php';

?>

<?php
// Ausgabe bereits über admin_greeting.php eingebunden.
?>
