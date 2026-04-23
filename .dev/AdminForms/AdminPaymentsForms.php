<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminPaymentsForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_payment') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Payment-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM payments WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Zahlung gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_payment') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Payment-ID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM payments WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $orderId = (string)($data['order_id'] ?? '');
            $amount  = (float)($data['amount'] ?? 0);
            $method  = (string)($data['method'] ?? '');
            $status  = (string)($data['status'] ?? 'pending');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE payments SET
                        order_id = ?,
                        amount = ?,
                        method = ?,
                        status = ?
                     WHERE id = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO payments
                    (id, order_id, amount, method, status)
                    VALUES (?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'sdsss',
                $orderId,
                $amount,
                $method,
                $status,
                $id
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Zahlung gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
