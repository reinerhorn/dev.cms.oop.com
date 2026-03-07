<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSLoginSession;

final class LogoutHandler
{
    public function handle(): array
    {
        CMSLoginSession::logout();

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }
}