<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Admin;

use CMS\Core\Service\NavigationOrderService;
use CMS\Config\DatabaseConnection;

class NavigationOrderController
{
    private NavigationOrderService $service;

    public function __construct()
    {
        $db = DatabaseConnection::getConnection();
        $this->service = new NavigationOrderService($db);
    }

    /**
     * POST /admin/navigation/move-up
     */
    public function moveUp(): void
    {
        $navUuid = $_POST['nav_uuid'] ?? null;

        if (!$navUuid) {
            $this->jsonError('nav_uuid fehlt');
            return;
        }

        try {
            $this->service->moveUp($navUuid);
            $this->jsonSuccess();
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * POST /admin/navigation/move-down
     */
    public function moveDown(): void
    {
        $navUuid = $_POST['nav_uuid'] ?? null;

        if (!$navUuid) {
            $this->jsonError('nav_uuid fehlt');
            return;
        }

        try {
            $this->service->moveDown($navUuid);
            $this->jsonSuccess();
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /* ========================= */

    private function jsonSuccess(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok'
        ]);
        exit;
    }

    private function jsonError(string $message): void
    {
        header('Content-Type: application/json', true, 400);
        echo json_encode([
            'status'  => 'error',
            'message' => $message
        ]);
        exit;
    }
}
