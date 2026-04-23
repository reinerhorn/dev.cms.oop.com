<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin\Shop;

use CMS\Core\CMSApp;

final class AdminCategoryForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE
        // ==========================================
        if ($action === 'delete_category') {
            $id = (string)($data['id'] ?? '');

            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM categories WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Kategorie gelöscht'];
        }

        // ==========================================
        // SAVE (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_category') {

            $id          = (string)($data['id'] ?? '');
            $name        = (string)($data['name'] ?? '');
            $description = $data['description'] ?? null;

            if ($id === '' || $name === '') {
                return [
                    'status'  => 'error',
                    'message' => 'Pflichtfelder fehlen'
                ];
            }

            // Existiert Kategorie?
            $check = $db->prepare(
                "SELECT id FROM categories WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE categories
                     SET name = ?, description = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sss',
                    $name,
                    $description,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO categories
                     (id, name, description)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param(
                    'sss',
                    $id,
                    $name,
                    $description
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Kategorie gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
