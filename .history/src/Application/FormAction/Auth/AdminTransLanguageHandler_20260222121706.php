<?php
namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;

final class AdminTransLanguageHandler
{
    public function handle(array $postData, array $pageMeta): array
    {
        $action = $postData['action'] ?? '';

        // Aktuelle URL sauber bestimmen (PRG-konform)
        $currentUrl = $_SERVER['HTTP_REFERER'] ?? '/de/translate-editor';

        switch ($action) {

            case 'save':

                $id = trim($postData['id'] ?? '');

                // TODO: Hier später echte Save-Logik einbauen (Insert / Update)

                // Wenn neue ID leer ist → auf Basis-Seite bleiben
                if ($id === '') {
                    return [
                        'success'  => true,
                        'redirect' => '/de/translate-editor'
                    ];
                }

                // Wenn ID vorhanden → mit ID zurück
                return [
                    'success'  => true,
                    'redirect' => '/de/translate-editor?id=' . urlencode($id)
                ];

            case 'delete':

                $id = trim($postData['id'] ?? '');

                // TODO: Hier später echte Delete-Logik einbauen

                return [
                    'success'  => true,
                    'redirect' => '/de/translate-editor'
                ];

            case 'cancel':
                return [
                    'success'  => true,
                    'redirect' => '/de/translate-editor'
                ];
        }

        return [
            'success' => false,
            'message' => 'Unbekannte Action'
        ];
    }
}
