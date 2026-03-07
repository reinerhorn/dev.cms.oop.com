<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminPermissionsForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_permission') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Permission-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM permissions WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Permission gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_permission') {

            $id   = (string)($data['id'] ?? '');
            $name = (string)($data['name'] ?? '');

            if ($id === '' || $name === '') {
                return ['status'=>'error','message'=>'ID oder Name fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM permissions WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE permissions SET name = ? WHERE id = ?"
                );
                $stmt->bind_param('ss', $name, $id);
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO permissions (id, name) VALUES (?, ?)"
                );
                $stmt->bind_param('ss', $id, $name);
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Permission gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
