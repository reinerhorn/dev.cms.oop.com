<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminHeaderImageHandlerForms
{
    public function handle(array $data): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==============================
        // DELETE IMAGE
        // ==============================
        if ($action === 'delete_image') {

            $id = (string)($data['id'] ?? '');

            if ($id === '') {
                return ['status' => 'error', 'message' => 'Image-ID fehlt'];
            }

            $stmt = $db->prepare("DELETE FROM header_images WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Header-Image gelöscht'];
        }

        // ==============================
        // SAVE IMAGE (INSERT / UPDATE)
        // ==============================
        if ($action === 'save_image') {

            $id       = (string)($data['id'] ?? '');
            $headerId = (string)($data['header_id'] ?? '');
            $imageUrl = (string)($data['image_url'] ?? '');
            $linkUrl  = $data['link_url'] ?? null;
            $altText  = $data['alt_text'] ?? null;
            $sort     = (int)($data['sort_order'] ?? 0);

            if ($headerId === '' || $imageUrl === '') {
                return ['status' => 'error', 'message' => 'Pflichtfelder fehlen'];
            }

            if ($id === '') {

                $id = bin2hex(random_bytes(16));

                $stmt = $db->prepare(
                    "INSERT INTO header_images
                     (id, header_id, image_url, link_url, alt_text, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'sssssi',
                    $id,
                    $headerId,
                    $imageUrl,
                    $linkUrl,
                    $altText,
                    $sort
                );

            } else {

                $stmt = $db->prepare(
                    "UPDATE header_images SET
                        image_url = ?,
                        link_url = ?,
                        alt_text = ?,
                        sort_order = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    'sssis',
                    $imageUrl,
                    $linkUrl,
                    $altText,
                    $sort,
                    $id
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Header-Image gespeichert'];
        }

        return ['status' => 'error', 'message' => 'Unbekannte Image-Aktion'];
    }
}
