<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSLoginSession;

final class LogoutHandler
{
    public static function handle(): array
    {
        // Sprache sichern, bevor Session zerstört wird
        $language = $_SESSION['language'] ?? 'de';

        // Sauberer Logout (Session + Cookies)
        CMSLoginSession::logout();

        return [
            'success'  => true,
            'redirect' => '/' . $language . '/startseite',
        ];
    }
}