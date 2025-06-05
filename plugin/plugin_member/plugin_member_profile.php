<?php
 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/member/MemberProfile.php';
 
if (!isset($_SESSION['user_id'])) {
    header('Location:/index.php');
    exit;
}

$db = getDbConnection();
$profile = new MemberProfile($db, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile->saveProfile($_POST);
    echo "<p>✅ Profil gespeichert!</p>";
}

echo $profile->renderForm();
?>
