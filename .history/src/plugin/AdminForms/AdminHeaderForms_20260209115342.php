<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminHeaderForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? 'save_header';

        // =================================================
        // HEADER DELETE
        // =================================================
        if ($action === 'delete_header') {
            $id = (string)($data['id'] ?? '');

            if ($id === '') {
                return ['status' => 'error', 'message' => 'ID fehlt'];
            }

            $stmt = $db->prepare("DELETE FROM header WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Header gelöscht'];
        }

        // =================================================
        // HEADER SAVE (INSERT / UPDATE)
        // =================================================
        if ($action === 'save_header') {
            $id        = (string)($data['id'] ?? '');
            $headline  = (string)($data['headline'] ?? '');
            $label     = (string)($data['label'] ?? '');
            $css       = (string)($data['css'] ?? 'header');
            $context   = (string)($data['context_id'] ?? 'frontend');
            $position  = (string)($data['position'] ?? 'main');
            $sortOrder = (int)($data['sort_order'] ?? 0);
            $enabled   = (int)($data['enabled'] ?? 1);
            $holder    = (string)($data['fk_translation_placeholder'] ?? '');

            if ($id === '') {
                $id = bin2hex(random_bytes(16));

                $stmt = $db->prepare(
                    "INSERT INTO header
                     (id, headline, label, css, context_id, position, sort_order, enabled, fk_translation_placeholder)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'ssssssiss',
                    $id,
                    $headline,
                    $label,
                    $css,
                    $context,
                    $position,
                    $sortOrder,
                    $enabled,
                    $holder
                );
            } else {
                $stmt = $db->prepare(
                    "UPDATE header SET
                        headline = ?,
                        label = ?,
                        css = ?,
                        context_id = ?,
                        position = ?,
                        sort_order = ?,
                        enabled = ?,
                        fk_translation_placeholder = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    'sssssisss',
                    $headline,
                    $label,
                    $css,
                    $context,
                    $position,
                    $sortOrder,
                    $enabled,
                    $holder,
                    $id
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Header gespeichert'];
        }

        // =================================================
        // HEADER_IMAGE DELETE
        // =================================================
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

        // =================================================
        // HEADER_IMAGE SAVE (INSERT / UPDATE)
        // =================================================
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
                    'sssds',
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

        return ['status' => 'error', 'message' => 'Unbekannte Aktion'];
    }
}
