<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;
use CMS\Core\CMSLoginSession;

final class LogoutAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        CMSLoginSession::logout();

        return [
            'success'  => true,
            'redirect' => '/',
        ];
    }
}
