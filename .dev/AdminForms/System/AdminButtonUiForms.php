<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminButtonUiForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_button') {

            $id = (string)($data['button_id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Button-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM button_id WHERE button_id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Button gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_button') {

            $id       = (string)($data['button_id'] ?? '');
            $labelKey = (string)($data['label_key'] ?? '');
            $btnAction= (string)($data['action'] ?? '');
            $type     = (string)($data['button_type'] ?? 'submit');
            $variant  = (string)($data['variant'] ?? 'primary');
            $confirm  = isset($data['confirm_required']) ? 1 : 0;
            $enabled  = isset($data['enabled']) ? 1 : 0;

            if ($id === '' || $labelKey === '' || $btnAction === '') {
                return ['status'=>'error','message'=>'Pflichtfelder fehlen'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT button_id FROM button_id WHERE button_id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE button_id SET
                        label_key = ?,
                        action = ?,
                        button_type = ?,
                        variant = ?,
                        confirm_required = ?,
                        enabled = ?
                     WHERE button_id = ?"
                );
                $stmt->bind_param(
                    'ssssiss',
                    $labelKey,
                    $btnAction,
                    $type,
                    $variant,
                    $confirm,
                    $enabled,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO button_id
                        (button_id, label_key, action, button_type, variant, confirm_required, enabled)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->bind_param(
                    'sssssis',
                    $id,
                    $labelKey,
                    $btnAction,
                    $type,
                    $variant,
                    $confirm,
                    $enabled
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Button gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}