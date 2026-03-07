<?php
declare(strict_types=1);

/**
 * VERIFY ENDPOINT
 * -----------------------------
 * Wird ausschließlich über den E-Mail-Link aufgerufen:
 * /verify.php?token=...
 *
 * KEIN CMS-Frontend
 * KEIN Twig
 * KEIN Layout
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CMS\Core\CMSApp;
use CMS\Controller\Auth\VerifyController;

// -------------------------------------------------
// CMS initialisieren (Session, DB)
// -------------------------------------------------
CMSApp::init();

// -------------------------------------------------
// Token aus GET lesen (ohne deprecated Filter)
// -------------------------------------------------
$token = $_GET['token'] ?? null;

if (!is_string($token) || $token === '') {
    http_response_code(400);
    echo 'Ungültiger oder fehlender Token.';
    exit;
}

// -------------------------------------------------
// Verify-Controller ausführen
// -------------------------------------------------
$controller = new VerifyController(
    CMSApp::getDb()
);

$controller->verify($token);