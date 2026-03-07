<?php
namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;

final class RegisterAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        // 1. Pflichtfelder prüfen
        if (
            empty($data['email']) ||
            empty($data['password'])
        ) {
            return [
                'success' => false,
                'error'   => 'Pflichtfelder fehlen',
            ];
        }

        // 2. E-Mail validieren
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        // 3. Prüfen ob User existiert
        $exists = false; // z. B. UserRepository::emailExists()

        if ($exists) {
            return [
                'success' => false,
                'error'   => 'E-Mail bereits registriert',
            ];
        }

        // 4. User anlegen
        // UserRepository::create(...)
        // MailService::sendVerification(...)

        // 5. Erfolg
        return [
            'success'  => true,
            'message'  => 'Registrierung erfolgreich',
            'redirect' => '/login',
        ];
    }
}