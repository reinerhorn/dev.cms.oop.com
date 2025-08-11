


<?php
/**
 * Debug Mode Template
 * Wird geladen, wenn der Debug-Modus aktiviert ist.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sicherheitscheck – nur für eingeloggte Admins
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/UserSession.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/init.php';


echo "<div style='padding:10px; background:#222; color:#0f0; font-family:monospace;'>";
echo "<h2>🐞 Debug-Modus ist AKTIV</h2>";
echo "<p>Serverzeit: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Angemeldeter Benutzer: " . htmlspecialchars(UserSession::getUserName()) . "</p>";
echo "<hr>";
echo "<h3>Session-Daten:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
echo "<h3>GET-Daten:</h3>";
echo "<pre>" . print_r($_GET, true) . "</pre>";
echo "<h3>POST-Daten:</h3>";
echo "<pre>" . print_r($_POST, true) . "</pre>";
echo "</div>";