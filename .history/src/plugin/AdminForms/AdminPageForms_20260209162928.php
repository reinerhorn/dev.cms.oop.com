<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;

final class AdminPageForms
{
    public function handle(array $data, array $pageMeta): array
    {
        $db     = CMSApp::getDb();
        $action = $data['_action'] ?? '';

        // ==========================================
        // DELETE
        // ==========================================
        if ($action === 'delete_page') {

            $id = (string)($data['page_uuid'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Page UUID fehlt'];
            }

            $stmt = $db->prepare(
                "DELETE FROM page WHERE page_uuid = ?"
            );
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Seite gelöscht'];
        }

        // ==========================================
        // SAVE (INSERT / UPDATE)
        // ==========================================
        if ($action === 'save_page') {

            $id = (string)($data['page_uuid'] ?? '');
            if ($id === '') {
                return ['status'=>'error','message'=>'Page UUID fehlt'];
            }

            // existiert?
            $check = $db->prepare(
                "SELECT page_uuid FROM page WHERE page_uuid = ? LIMIT 1"
            );
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            $enabled = isset($data['enabled']) ? 1 : 0;

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE page SET
                        slug = ?, name = ?, required_permission_id = ?, page_css_id = ?,
                        fk_translation_placeholder = ?, template = ?, meta_title = ?,
                        meta_description = ?, enabled = ?, sort_order = ?, context = ?,
                        nav_id = ?, area = ?, auth_id = ?, auth_visibility = ?, form_action = ?
                     WHERE page_uuid = ?"
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO page
                    (page_uuid, slug, name, required_permission_id, page_css_id,
                     fk_translation_placeholder, template, meta_title, meta_description,
                     enabled, sort_order, context, nav_id, area, auth_id,
                     auth_visibility, form_action)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
            }

            $stmt->bind_param(
                'ssssssssiiissssss',
                $id,
                $data['slug'],
                $data['name'],
                $data['required_permission_id'],
                $data['page_css_id'],
                $data['fk_translation_placeholder'],
                $data['template'],
                $data['meta_title'],
                $data['meta_description'],
                $enabled,
                (int)($data['sort_order'] ?? 0),
                $data['context'],
                $data['nav_id'],
                $data['area'],
                $data['auth_id'],
                $data['auth_visibility'],
                $data['form_action']
            );

            $stmt->execute();
            $stmt->close();

            return ['status'=>'ok','message'=>'Seite gespeichert'];
        }

        return ['status'=>'error','message'=>'Unbekannte Aktion'];
    }
}
