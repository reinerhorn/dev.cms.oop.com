<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminFooterHandler
{
    public function handle(array $data): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE FOOTER
        // ==========================================
        if ($action === 'delete_footer') {

            $id = (string)($data['id'] ?? '');

            if ($id === '') {
                return ['status' => 'error', 'message' => 'ID fehlt'];
            }

            $stmt = $db->prepare("DELETE FROM footer WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Footer gelöscht'];
        }

        // ==========================================
        // SAVE FOOTER (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_footer') {

            $id       = (string)($data['id'] ?? '');
            $headline = (string)($data['headline'] ?? '');
            $link     = (string)($data['link'] ?? '');
            $label    = (string)($data['label'] ?? '');
            $version  = (string)($data['version'] ?? '');
            $css      = (string)($data['css'] ?? '');
            $context  = (string)($data['context_id'] ?? 'frontend');
            $tpl      = (string)($data['fk_translation_placeholder'] ?? '');

            if ($id === '') {

                $id = bin2hex(random_bytes(16));

                $stmt = $db->prepare(
                    "INSERT INTO footer
                     (id, headline, link, label, version, css, context_id, fk_translation_placeholder)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'ssssssss',
                    $id,
                    $headline,
                    $link,
                    $label,
                    $version,
                    $css,
                    $context,
                    $tpl
                );

            } else {

                $stmt = $db->prepare(
                    "UPDATE footer SET
                        headline = ?,
                        link = ?,
                        label = ?,
                        version = ?,
                        css = ?,
                        context_id = ?,
                        fk_translation_placeholder = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    'ssssssss',
                    $headline,
                    $link,
                    $label,
                    $version,
                    $css,
                    $context,
                    $tpl,
                    $id
                );
            }

            $stmt->execute();
            $stmt->close();

            return ['status' => 'ok', 'message' => 'Footer gespeichert'];
        }

        return ['status' => 'error', 'message' => 'Unbekannte Footer-Aktion'];
    }
}
