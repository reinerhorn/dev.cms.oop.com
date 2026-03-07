<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\Auth\AuthService;

final class LogoutHandler
{
    public static function handle(): array
    {
        error_log('LOGOUT HANDLER HIT');

        error_log('SESSION BEFORE LOGOUT: ' . print_r($_SESSION, true));
        error_log('COOKIE BEFORE LOGOUT: ' . print_r($_COOKIE, true));

        $auth = new AuthService();
        $auth->logout();

        error_log('SESSION AFTER LOGOUT: ' . print_r($_SESSION, true));
        error_log('COOKIE AFTER LOGOUT: ' . print_r($_COOKIE, true));

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }
}