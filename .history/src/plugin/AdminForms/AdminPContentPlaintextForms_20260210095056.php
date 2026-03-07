<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminPContentPlaintextForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_p_content_plaintext') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM p_content_plaintext WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Plaintext gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_p_content_plaintext') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'ID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM p_content_plaintext WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $headline         = (string)($data['headline'] ?? '');
            $text             = (string)($data['text'] ?? '');
            $idx              = (int)($data['idx'] ?? 0);
            $label            = (string)($data['label'] ?? '');
            $languageId       = (string)($data['fk_language_id'] ?? '');
            $imagePath        = (string)($data['image_path'] ?? '');
            $link             = (string)($data['link'] ?? '');
            $imageDescription = (string)($data['image_description'] ?? '');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE p_content_plaintext SET
                        headline = ?,
                        text = ?,
                        idx = ?,
                        label = ?,
                        fk_language_id = ?,
                        image_path = ?,
                        link = ?,
                        image_description = ?
                     WHERE id = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO p_content_plaintext
                    (id, headline, text, idx, label, fk_language_id, image_path, link, image_description)
                    VALUES (?,?,?,?,?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'ssissssss',
                $id,
                $headline,
                $text,
                $idx,
                $label,
                $languageId,
                $imagePath,
                $link,
                $imageDescription
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Plaintext gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
