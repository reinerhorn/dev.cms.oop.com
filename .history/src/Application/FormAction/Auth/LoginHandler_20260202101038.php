<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

final class LoginHandler
{
    public function handle(array $data): array
    {
        return [
            'success' => true,
            'redirect' => '/de/startseite'
        ];
    }
}