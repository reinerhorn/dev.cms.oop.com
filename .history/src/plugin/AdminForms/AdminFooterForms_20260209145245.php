<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminFooterForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // FOOTER DELETE
        // ==========================================
        if ($action === 'delete_footer') {
            $id = (string)($data['id'] ?? '');
            if ($id === '') return ['status'=>'error','message'=>'ID fehlt'];

            $stmt = $db->prepare("DELETE FROM footer WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Footer gelöscht'];
        }

        // ==========================================
        // FOOTER SAVE
        // ==========================================
        if ($action === 'save_footer') {
            $id     = (string)($data['id'] ?? '');
            $values = [
                $data['headline'] ?? '',
                $data['link'] ?? '',
                $data['label'] ?? '',
                $data['version'] ?? '',
                $data['css'] ?? '',
                $data['context_id'] ?? 'frontend',
                $data['fk_translation_placeholder'] ?? ''
            ];

            if ($id === '') {
                $stmt = $db->prepare(
                    "INSERT INTO footer
                    (headline, link, label, version, css, context_id, fk_translation_placeholder)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssssss', ...$values);
            } else {
                $stmt = $db->prepare(
                    "UPDATE footer SET
                        headline = ?, link = ?, label = ?, version = ?, css = ?, context_id = ?, fk_translation_placeholder = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('ssssssss', ...$values, $id);
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Footer gespeichert'];
        }

        // ==========================================
        // FOOTER IMAGE DELETE
        // ==========================================
        if ($action === 'delete_image') {
            $id = (string)($data['id'] ?? '');
            if ($id === '') return ['status'=>'error','message'=>'Image-ID fehlt'];

            $stmt = $db->prepare("DELETE FROM footer_images WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Footer-Image gelöscht'];
        }

        // ==========================================
        // FOOTER IMAGE SAVE
        // ==========================================
        if ($action === 'save_image') {
            $id       = (string)($data['id'] ?? '');
            $footerId = (string)($data['footer_id'] ?? '');

            if ($footerId === '') {
                return ['status'=>'error','message'=>'Footer-ID fehlt'];
            }

            if ($id === '') {
                $stmt = $db->prepare(
                    "INSERT INTO footer_images
                    (footer_id, image_url, link_url, alt_text, sort_order)
                    VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    'ssssi',
                    $footerId,
                    $data['image_url'] ?? null,
                    $data['link_url'] ?? null,
                    $data['alt_text'] ?? null,
                    (int)($data['sort_order'] ?? 0)
                );
            } else {
                $stmt = $db->prepare(
                    "UPDATE footer_images SET
                        image_url = ?, link_url = ?, alt_text = ?, sort_order = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sssds',
                    $data['image_url'] ?? null,
                    $data['link_url'] ?? null,
                    $data['alt_text'] ?? null,
                    (int)($data['sort_order'] ?? 0),
                    $id
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Footer-Image gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
