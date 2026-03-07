<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CMS\Application\FormAction\Auth\LoginHandler;

header('Content-Type: application/json');

try {
    $data = $_POST;

    $handler = new LoginHandler();
    $result  = $handler->handle($data);

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Login fehlgeschlagen'
    ]);
}
