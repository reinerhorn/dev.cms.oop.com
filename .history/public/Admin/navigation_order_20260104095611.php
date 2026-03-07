<?php
declare(strict_types=1);

use CMS\Core\Service\NavigationOrderService;
use CMS\Config\DatabaseConnection;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

/**
 * Erwartete POST-Parameter:
 * action        = move_up | move_down | move_to_position
 * nav_uuid      = UUID des Navigation-Eintrags
 * parent_id     = (optional) neuer Parent bei move_to_position
 * position      = (optional) Zielposition bei move_to_position
 */

$action    = $_POST['action']    ?? null;
$navUuid   = $_POST['nav_uuid']  ?? null;
$parentId  = $_POST['parent_id'] ?? null;
$position  = isset($_POST['position']) ? (int)$_POST['position'] : null;

if (!$action || !$navUuid) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
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

        case 'move_to_position':
            if ($position === null) {
                throw new InvalidArgumentException('position fehlt für move_to_position');
            }
            // parentId darf NULL sein (Root-Ebene)
            $service->moveToPosition($navUuid, $parentId, $position);
            break;

        default:
            throw new RuntimeException('Unbekannte Aktion: ' . $action);
    }

    echo json_encode([
        'status' => 'ok'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
