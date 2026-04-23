<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminPageImagesForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_page_image') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Image-ID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM page_images WHERE id = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Bild gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_page_image') {

            $id = (string)($data['id'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Image-ID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT id FROM page_images WHERE id = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $pageUuid  = (string)($data['fk_page_uuid'] ?? '');
            $imageUrl  = (string)($data['image_url'] ?? '');
            $altText   = (string)($data['alt_text'] ?? '');
            $linkUrl   = (string)($data['link_url'] ?? '');
            $sortOrder = (int)($data['sort_order'] ?? 0);
            $language  = (string)($data['language'] ?? 'de');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE page_images SET
                        fk_page_uuid = ?,
                        image_url = ?,
                        alt_text = ?,
                        link_url = ?,
                        sort_order = ?,
                        language = ?
                     WHERE id = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO page_images
                    (id, fk_page_uuid, image_url, alt_text, link_url, sort_order, language)
                    VALUES (?,?,?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'ssssiss',
                $pageUuid,
                $imageUrl,
                $altText,
                $linkUrl,
                $sortOrder,
                $language,
                $id
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Bild gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
