<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminTransLanguageForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_trans_language') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Language-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM trans_language WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Sprache gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_trans_language') {

            $id       = (string)($data['id'] ?? '');
            $label    = (string)($data['label'] ?? '');
            $flagPath = (string)($data['flag_path'] ?? '');

            if ($id === '' || $label === '') {
                return ['status'=>'error','message'=>'ID oder Label fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM trans_language WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE trans_language
                     SET label = ?, flag_path = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sss',
                    $label,
                    $flagPath,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO trans_language
                        (id, label, flag_path)
                     VALUES (?,?,?)"
                );
                $stmt->bind_param(
                    'sss',
                    $id,
                    $label,
                    $flagPath
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Sprache gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
