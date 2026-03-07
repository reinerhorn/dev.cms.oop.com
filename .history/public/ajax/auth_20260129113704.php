<?php
declare(strict_types=1);

/**
 * AUTH ENDPOINT
 * -----------------------------
 * EINZIGER Einstiegspunkt für:
 * - login
 * - register
 * - logout
 *
 * KEINE Logik hier!
 * KEINE SQL!
 * KEIN HTML!
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Core\CMSApp;
use CMS\Core\CMSLoginSession;

// ----------------------------------
// CMS initialisieren (DB, Config)
// ----------------------------------
CMSApp::init();

// ----------------------------------
// Nur POST erlauben
// ----------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Nur POST erlaubt',
    ]);
    exit;
}

// ----------------------------------
// Daten lesen (FORM oder JSON)
// ----------------------------------
$data = $_POST;

if (empty($data)) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

// ----------------------------------
// Dispatch → FormActionRegistry
// ----------------------------------
use CMS\Application\FormAction\FormActionRegistry;
use CMS\Application\FormAction\Auth\LoginAction;
use CMS\Application\FormAction\Auth\RegisterAction;
use CMS\Application\FormAction\Auth\LogoutAction;

try {
    $intent = $data['intent'] ?? null;

    if (!$intent) {
        throw new RuntimeException('Missing intent');
    }

    $registry = new FormActionRegistry();
    $registry->register('login', new LoginAction());
    $registry->register('register', new RegisterAction());
    $registry->register('logout', new LogoutAction());

    $action = $registry->resolve($intent);
    $result = $action->handle($data);

    // 🔁 Redirect bei klassischen FORM-POSTs
    if (
        isset($result['redirect'])
        && is_string($result['redirect'])
        && $result['redirect'] !== ''
        && empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    ) {
        header('Location: ' . $result['redirect']);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
    exit;
}