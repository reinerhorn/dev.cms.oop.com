<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Application\FormAction\Auth\LogoutHandler;

header('Content-Type: application/json');

try {
    $handler = new LogoutHandler();
    $result  = $handler->handle();

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Logout fehlgeschlagen'
    ]);
}