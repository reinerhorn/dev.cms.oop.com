<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CMS\Application\FormAction\FormActionRegistry;
use CMS\Application\FormAction\Auth\LoginAction;
use CMS\Application\FormAction\Auth\RegisterAction;
use CMS\Application\FormAction\Auth\LogoutAction;


header('Content-Type: application/json');

// 🔴 JSON oder klassisches POST sauber einlesen
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Fallback: normales POST (z. B. ohne JS / Debug)
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$intent = $data['intent'] ?? null;

if (!$intent) {
    echo json_encode([
        'success' => false,
        'error'   => 'No intent given'
    ]);
    exit;
}

// Registry
$registry = new FormActionRegistry();
$registry->register('login', new LoginAction());
$registry->register('register', new RegisterAction());
$registry->register('logout', new LogoutAction());

try {
    $action = $registry->resolve($intent);
    $result = $action->handle($data);

    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}