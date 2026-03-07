<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminFormButtonForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE
        // ==========================================
        if ($action === 'delete_form_button') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM form_button WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Eintrag gelöscht'];
        }

        // ==========================================
        // SAVE (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_form_button') {

            $id        = (string)($data['id'] ?? '');
            $formId    = (string)($data['form_id'] ?? '');
            $buttonId  = (string)($data['button_id'] ?? '');
            $sortOrder = (int)($data['sort_order'] ?? 0);

            if ($id === '' || $formId === '' || $buttonId === '') {
                return ['status'=>'error','message'=>'Pflichtfelder fehlen'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM form_button WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE form_button
                     SET form_id = ?, button_id = ?, sort_order = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'ssis',
                    $formId,
                    $buttonId,
                    $sortOrder,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO form_button
                     (id, form_id, button_id, sort_order)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    'sssi',
                    $id,
                    $formId,
                    $buttonId,
                    $sortOrder
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
