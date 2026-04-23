<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminFooterImageHandler
{
    public function handle(array $data): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE IMAGE
        // ==========================================
        if ($action === 'delete_footer_image') {

            $id = (string)($data['id'] ?? '');

            if ($id === '') {
                return ['status' => 'error', 'message' => 'Image-ID fehlt'];
            }

            $stmt = $db->prepare("DELETE FROM footer_images WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Footer-Image gelöscht'];
        }

        // ==========================================
        // SAVE IMAGE (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_footer_image') {

            $id       = (string)($data['id'] ?? '');
            $footerId = (string)($data['footer_id'] ?? '');

            $imageUrl = $data['image_url'] ?? null;
            $linkUrl  = $data['link_url'] ?? null;
            $altText  = $data['alt_text'] ?? null;
            $sort     = (int)($data['sort_order'] ?? 0);

            if ($footerId === '') {
                return ['status' => 'error', 'message' => 'Footer-ID fehlt'];
            }

            if ($id === '') {

                $id = bin2hex(random_bytes(16));

                $stmt = $db->prepare(
                    "INSERT INTO footer_images
                     (id, footer_id, image_url, link_url, alt_text, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'sssssi',
                    $id,
                    $footerId,
                    $imageUrl,
                    $linkUrl,
                    $altText,
                    $sort
                );

            } else {

                $stmt = $db->prepare(
                    "UPDATE footer_images SET
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

            return ['status' => 'ok', 'message' => 'Footer-Image gespeichert'];
        }

        return ['status' => 'error', 'message' => 'Unbekannte Footer-Image-Aktion'];
    }
}
