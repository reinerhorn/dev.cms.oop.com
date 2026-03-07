<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Core\CMSApp;
use CMS\Core\CMSLoginSession;

// CMS initialisieren (Session, DB, Config)
CMSApp::init();

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'Nur POST erlaubt',
    ]);
    exit;
}

// ZENTRALER ENTRYPOINT
$result = CMSLoginSession::handleUserAction($_POST);

// JSON zurückgeben
header('Content-Type: application/json');
echo json_encode($result);
exit;
