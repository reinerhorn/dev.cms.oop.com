<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$connection = getDbConnection();

$user_id = $_SESSION['userid'] ?? null;

if ($user_id) {
    $stmt = $connection->prepare("
        UPDATE login_agb_log 
        SET logout_at = NOW() 
        WHERE user_id = ? 
        ORDER BY login_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Session beenden
$_SESSION = [];
session_destroy();

// Zurück zur Startseite
header("Location: /?page=1692888607"); // Login-Seite oder Startseite
exit;
?>
