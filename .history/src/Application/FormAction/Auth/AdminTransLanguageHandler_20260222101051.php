<?php
namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;

final class AdminTransLanguageHandler
{
    public function handle(array $postData, array $pageMeta): array
    {
        $action = $postData['action'] ?? '';

        switch ($action) {

            case 'save':
                // TODO: Save Logic
                return [
                    'status'   => 'ok',
                    'redirect' => '/de/translate-editor?id=' . ($postData['id'] ?? '')
                ];

            case 'delete':
                // TODO: Delete Logic
                return [
                    'status'   => 'ok',
                    'redirect' => '/de/translate-editor'
                ];

            case 'cancel':
                return [
                    'status'   => 'ok',
                    'redirect' => '/de/translate-editor'
                ];
        }

        return [
            'status'  => 'error',
            'message' => 'Unbekannte Action'
        ];
    }
}
