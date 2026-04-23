<?php
if (!isset($_SESSION)) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/class/security/UserSession.php';

if (!UserSession::isLoggedIn()) {
    echo "Bitte erst einloggen";
    return;
}

echo UserSession::showGreeting();
echo "<br>🔐 Aktuelle Rolle: " . (!empty($_SESSION['admin_a']) && $_SESSION['admin_a'] == 1 ? "Admin" : "Mitglied");
