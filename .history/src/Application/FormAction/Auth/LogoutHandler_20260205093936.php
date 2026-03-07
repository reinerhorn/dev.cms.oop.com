<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\Auth\AuthService;

final class LogoutHandler
{
    public static function handle(): array
    {
        $auth = new AuthService();
        $auth->logout();

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }
}