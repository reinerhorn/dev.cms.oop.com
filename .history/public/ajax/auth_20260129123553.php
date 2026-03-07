<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CMS\Application\FormAction\FormActionRegistry;
use CMS\Application\FormAction\Auth\LoginAction;
use CMS\Application\FormAction\Auth\RegisterAction;
use CMS\Application\FormAction\Auth\LogoutAction;

header('Content-Type: application/json');

$intent = $_POST['intent'] ?? null;

if (!$intent) {
    echo json_encode([
        'success' => false,
        'message' => 'No intent given'
    ]);
    exit;
}

$registry = new FormActionRegistry();
$registry->register('login', new LoginAction());
$registry->register('register', new RegisterAction());
$registry->register('logout', new LogoutAction());

try {
    $action = $registry->resolve($intent);
    $result = $action->handle($_POST);

    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}