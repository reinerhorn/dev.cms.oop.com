<?php
// plugin_debug_switch.php
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/security/UserSession.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/dev/DebugHelper.php";

if (!UserSession::isLoggedIn()) return;
if (empty($_SESSION['admin_a']) || $_SESSION['admin_a'] != 1) return;

$currentStatus = \DebugHelper::$enabled ? 'on' : 'off';
$toggle = $currentStatus === 'on' ? 'off' : 'on';

// Aktuelle Query-Parameter übernehmen und "debug" toggeln
$qs = $_GET;
$qs['debug'] = $toggle;
$toggleLink = $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);

// Anzeige des Debug-Schalters
echo '<div class="debug-toggle">';
echo "🛠️ Debug: <strong>$currentStatus</strong>";
echo " <a href=\"$toggleLink\">[umschalten]</a>";
echo '</div>';
?>
 <div style="position: absolute; top: 80px; right: 10px;">
    <a href="?page=1692888607&force=member" class="debug-button" style="padding:5px 10px; background:#444; color:#fff; text-decoration:none; border-radius:4px;">👥 Member-Bereich als Admin</a>
</div>"
 <div style="position: absolute; top: 100px; right: 10px;">
    <a href="?page=1692882220&force=startseite" class="debug-button" style="padding:5px 10px; background:#444; color:#fff; text-decoration:none; border-radius:4px;">👥 Gast als Admin</a>
</div>"
 <div style="position: absolute; top: 150px; right: 10px;">
    <a href="?page=1692888607&force=login" class="debug-button" style="padding:5px 10px; background:#444; color:#fff; text-decoration:none; border-radius:4px;">👥 Login als Admin</a>
</div>"
