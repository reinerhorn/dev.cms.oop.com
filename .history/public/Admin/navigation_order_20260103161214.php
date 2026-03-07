<?php
declare(strict_types=1);

use CMS\Core\Service\NavigationOrderService;
use CMS\Config\DatabaseConnection;

require_once __DIR__ . '/../../vendor/autoload.php'; // oder dein Autoloader

header('Content-Type: application/json');

$action  = $_POST['action'] ?? null;
$navUuid = $_POST['nav_uuid'] ?? null;

if (!$action || !$navUuid) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'action oder nav_uuid fehlt'
    ]);
    exit;
}

try {
    $db = DatabaseConnection::getConnection();
    $service = new NavigationOrderService($db);

    switch ($action) {
        case 'move_up':
            $service->moveUp($navUuid);
            break;

        case 'move_down':
            $service->moveDown($navUuid);
            break;

        default:
            throw new RuntimeException('Unbekannte Aktion');
    }

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
