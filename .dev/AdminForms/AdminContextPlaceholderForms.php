<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminContextPlaceholderForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE
        // ==========================================
        if ($action === 'delete_context_placeholder') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM context_placeholder WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Eintrag gelöscht'];
        }

        // ==========================================
        // SAVE (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_context_placeholder') {

            $id          = (string)($data['id'] ?? '');
            $description = $data['description'] ?? null;
            $type        = (string)($data['type'] ?? 'legacy');

            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM context_placeholder WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE context_placeholder
                     SET description = ?, type = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sss',
                    $description,
                    $type,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO context_placeholder
                     (id, description, type)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param(
                    'sss',
                    $id,
                    $description,
                    $type
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
