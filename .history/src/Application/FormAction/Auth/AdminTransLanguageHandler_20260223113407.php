<?php
namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;

final class AdminTransLanguageHandler
{
    public function handle(array $postData, array $pageMeta): array
    {
        $action = trim($postData['action'] ?? '');

        $db = CMSApp::getDb();

        $lang = $pageMeta['lang'] ?? 'de';
        $slug = $pageMeta['slug'] ?? 'translate-editor';
        $baseUrl = '/' . trim($lang) . '/' . trim($slug);

        switch ($action) {

            // =========================================
            // SAVE (Insert + Update)
            // =========================================
            case 'save':

                $id       = trim($postData['id'] ?? '');
                $label    = trim($postData['label'] ?? '');
                $flagPath = trim($postData['flag_path'] ?? '');

                if ($id === '' || $label === '') {
                    return [
                        'success' => false,
                        'message' => 'ID und Bezeichnung sind Pflichtfelder.'
                    ];
                }

                // Prüfen ob Datensatz existiert
                $stmt = $db->prepare(
                    "SELECT id FROM trans_language WHERE id = ? LIMIT 1"
                );
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    // UPDATE
                    $stmt = $db->prepare(
                        "UPDATE trans_language
                         SET label = ?, flag_path = ?
                         WHERE id = ?"
                    );
                    $stmt->bind_param("sss", $label, $flagPath, $id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // INSERT
                    $stmt = $db->prepare(
                        "INSERT INTO trans_language (id, label, flag_path)
                         VALUES (?, ?, ?)"
                    );
                    $stmt->bind_param("sss", $id, $label, $flagPath);
                    $stmt->execute();
                    $stmt->close();
                }

                return [
                    'success'  => true,
                    'redirect' => $baseUrl . '?id=' . urlencode($id)
                ];


            // =========================================
            // DELETE
            // =========================================
            case 'delete':

                $id = trim($postData['id'] ?? '');

                if ($id === '') {
                    return [
                        'success' => false,
                        'message' => 'Keine ID übergeben.'
                    ];
                }

                $stmt = $db->prepare(
                    "DELETE FROM trans_language WHERE id = ?"
                );
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $stmt->close();

                return [
                    'success'  => true,
                    'redirect' => $baseUrl
                ];


            // =========================================
            // CANCEL
            // =========================================
            case 'cancel':

                return [
                    'success'  => true,
                    'redirect' => $baseUrl
                ];
        }

        return [
            'success' => false,
            'message' => 'Unbekannte Action'
        ];
    }
}
