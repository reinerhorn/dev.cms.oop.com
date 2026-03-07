<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminPageConfigForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_page_config') {

            $id = (string)($data['page_config_uuid'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'page_config_uuid fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM page_config WHERE page_config_uuid = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Page-Config gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_page_config') {

            $id = (string)($data['page_config_uuid'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'page_config_uuid fehlt'];
            }

            // exists?
            $check = $db->prepare(
                "SELECT page_config_uuid FROM page_config WHERE page_config_uuid = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            // Felder → immer zuerst Variablen
            $idx                = (int)($data['idx'] ?? 0);
            $pageUuid           = (string)($data['fk_page_uuid'] ?? '');
            $pageSlug           = (string)($data['fk_page_slug'] ?? '');
            $pluginUuid         = (string)($data['fk_plugin_uuid'] ?? '');
            $pluginContentUuid  = (string)($data['plugin_content_uuid'] ?? '');
            $contentLabel       = (string)($data['content_label'] ?? '');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE page_config SET
                        idx = ?,
                        fk_page_uuid = ?,
                        fk_page_slug = ?,
                        fk_plugin_uuid = ?,
                        plugin_content_uuid = ?,
                        content_label = ?
                     WHERE page_config_uuid = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO page_config
                    (page_config_uuid, idx, fk_page_uuid, fk_page_slug,
                     fk_plugin_uuid, plugin_content_uuid, content_label)
                    VALUES (?,?,?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'issssss',
                $id,
                $idx,
                $pageUuid,
                $pageSlug,
                $pluginUuid,
                $pluginContentUuid,
                $contentLabel
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Page-Config gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
