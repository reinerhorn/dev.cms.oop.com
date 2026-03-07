<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CMS\Core\CMSApp;

CMSApp::init();

// Token holen
$token = $_GET['token'] ?? '';

if ($token === '') {
    header('Location: /de/login');
    exit;
}

$db = CMSApp::getDb();

// Token prüfen
$stmt = $db->prepare("
    SELECT id, role_id
    FROM login_users
    WHERE verify_token = ?
      AND is_verified = 0
    LIMIT 1
");
$stmt->bind_param('s', $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: /de/login');
    exit;
}

// Benutzer verifizieren
$update = $db->prepare("
    UPDATE login_users
    SET is_verified = 1, verify_token = NULL
    WHERE id = ?
");
$update->bind_param('s', $user['id']);
$update->execute();
$update->close();

// 🔐 AUTO-LOGIN
$_SESSION['user_id'] = $user['id'];
$_SESSION['role_id'] = $user['role_id'];
session_regenerate_id(true);

// 👉 Redirect nach Rolle
if (str_starts_with($user['role_id'], 'admin-')) {
    header('Location: /de/adminbereich');
} else {
    header('Location: /de/memberbereich');
}
exit;