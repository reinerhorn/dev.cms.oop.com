<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminRolesForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_role') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Role-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM roles WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Rolle gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_role') {

            $id   = (string)($data['id'] ?? '');
            $name = (string)($data['name'] ?? '');

            $defaultPageId        = (string)($data['default_page_id'] ?? '');
            $memberFallbackPageId = (string)($data['member_fallback_page_id'] ?? '');

            if ($id === '' || $name === '') {
                return ['status'=>'error','message'=>'ID oder Name fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM roles WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE roles SET
                        name = ?,
                        default_page_id = ?,
                        member_fallback_page_id = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'ssss',
                    $name,
                    $defaultPageId,
                    $memberFallbackPageId,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO roles
                        (id, name, default_page_id, member_fallback_page_id)
                     VALUES (?,?,?,?)"
                );
                $stmt->bind_param(
                    'ssss',
                    $id,
                    $name,
                    $defaultPageId,
                    $memberFallbackPageId
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Rolle gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
