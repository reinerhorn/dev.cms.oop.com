<?php
declare(strict_types=1);

namespace CMS\Application\FormAction;

use CMS\Core\CMSApp;

final class AdminTransLanguageHandler implements FormActionHandlerInterface
{
    public function supports(string $formAction): bool
    {
        return $formAction === 'translation_editor';
    }

    public function handle(array $postData, array $pageMeta): array
    {
        $db = CMSApp::getDb();
        $action = trim($postData['action'] ?? '');

        $id       = trim($postData['id'] ?? '');
        $label    = trim($postData['label'] ?? '');
        $flagPath = trim($postData['flag_path'] ?? '');

        switch ($action) {

            case 'save':

                if ($id === '' || $label === '') {
                    return [
                        'success' => false,
                        'message' => 'ID und Bezeichnung sind Pflichtfelder.'
                    ];
                }

                // prüfen ob existiert
                $stmt = $db->prepare(
                    "SELECT id FROM trans_language WHERE id = ? LIMIT 1"
                );
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $stmt = $db->prepare(
                        "UPDATE trans_language
                         SET label = ?, flag_path = ?
                         WHERE id = ?"
                    );
                    $stmt->bind_param("sss", $label, $flagPath, $id);
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO trans_language (id, label, flag_path)
                         VALUES (?, ?, ?)"
                    );
                    $stmt->bind_param("sss", $id, $label, $flagPath);
                }

                $stmt->execute();
                $stmt->close();

                return [
                    'success'  => true,
                    'redirect' => $_SERVER['REQUEST_URI'] . '?id=' . urlencode($id)
                ];


            case 'delete':

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
                    'redirect' => strtok($_SERVER['REQUEST_URI'], '?')
                ];


            case 'cancel':

                return [
                    'success'  => true,
                    'redirect' => strtok($_SERVER['REQUEST_URI'], '?')
                ];
        }

        return [
            'success' => false,
            'message' => 'Unbekannte Action'
        ];
    }
}
