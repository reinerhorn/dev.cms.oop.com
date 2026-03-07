<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminNavigationForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================
        // DELETE
        // ==========================
        if ($action === 'delete_navigation') {

            $id = (string)($data['nav_uuid'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'nav_uuid fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM navigation WHERE nav_uuid = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Navigation gelöscht'];
        }

        // ==========================
        // SAVE (INSERT / UPDATE)
        // ==========================
        if ($action === 'save_navigation') {

            $id = (string)($data['nav_uuid'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'nav_uuid fehlt'];
            }

            // exists?
            $check = $db->prepare(
                "SELECT nav_uuid FROM navigation WHERE nav_uuid = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            // Felder (immer erst Variablen!)
            $parentId = (string)($data['parent_id'] ?? '');
            $position = (string)($data['position'] ?? '');
            $pageUuid = (string)($data['fk_page_uuid'] ?? '');
            $seoSlug  = (string)($data['seo_slug'] ?? '');
            $tpl      = (string)($data['fk_translation_placeholder'] ?? '');
            $sort     = (int)($data['sort_order'] ?? 0);
            $enabled  = isset($data['enabled']) ? 1 : 0;
            $align    = (string)($data['nav_align'] ?? 'left');
            $context  = (string)($data['context_id'] ?? '');
            $perm     = (string)($data['required_permission_id'] ?? '');
            $authVis  = (string)($data['auth_visibility'] ?? 'public');

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE navigation SET
                        parent_id = ?, position = ?, fk_page_uuid = ?, seo_slug = ?,
                        fk_translation_placeholder = ?, sort_order = ?, enabled = ?,
                        nav_align = ?, context_id = ?, required_permission_id = ?,
                        auth_visibility = ?
                     WHERE nav_uuid = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO navigation
                    (nav_uuid, parent_id, position, fk_page_uuid, seo_slug,
                     fk_translation_placeholder, sort_order, enabled,
                     nav_align, context_id, required_permission_id, auth_visibility)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'sssssiisssss',
                $id,
                $parentId,
                $position,
                $pageUuid,
                $seoSlug,
                $tpl,
                $sort,
                $enabled,
                $align,
                $context,
                $perm,
                $authVis
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Navigation gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
