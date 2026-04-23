<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminOrdersForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_order') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Order-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM orders WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Bestellung gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_order') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Order-ID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM orders WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $userId = (string)($data['user_id'] ?? '');
            $total  = (float)($data['total'] ?? 0);
            $status = (string)($data['status'] ?? 'pending');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE orders SET
                        user_id = ?,
                        total = ?,
                        status = ?
                     WHERE id = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO orders
                    (id, user_id, total, status)
                    VALUES (?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'sdss',
                $userId,
                $total,
                $status,
                $id
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Bestellung gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
