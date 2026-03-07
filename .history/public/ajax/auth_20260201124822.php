<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CMS\Application\FormAction\FormActionRegistry;
use CMS\Application\FormAction\Auth\LoginAction;
use CMS\Application\FormAction\Auth\RegisterAction;
use CMS\Application\FormAction\Auth\LogoutAction;

// --------------------------------------------------
// Session bootstrap (zentral, sauber)
// --------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// JSON Response
// --------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

try {
    // --------------------------------------------------
    // Request-Daten lesen (JSON oder klassisches POST)
    // --------------------------------------------------
    $data = [];

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }

    // --------------------------------------------------
    // Intent prüfen
    // --------------------------------------------------
    $intent = $data['intent'] ?? null;

    if (!is_string($intent) || $intent === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'No intent given',
        ]);
        exit;
    }

    // --------------------------------------------------
    // Action Registry
    // --------------------------------------------------
    $registry = new FormActionRegistry();
    //$registry->register('login', new LoginAction()); muss noch gemacht werden
    $registry->register('register', new RegisterAction());
    $registry->register('logout', new LogoutAction());

    $action = $registry->resolve($intent);
    $result = $action->handle($data);

    http_response_code(!empty($result['success']) ? 200 : 422);
    echo json_encode($result);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Auth endpoint error',
        'detail'  => $e->getMessage(),
    ]);
    exit;
}