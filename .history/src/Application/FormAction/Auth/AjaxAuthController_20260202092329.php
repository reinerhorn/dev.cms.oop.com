<?php
declare(strict_types=1);

namespace CMS\Application\Auth;

final class AjaxAuthController
{
    public function login(array $data): array
    {
        return (new LoginHandler())->handle($data);
    }

    public function logout(): array
    {
        return (new LogoutHandler())->handle();
    }
}
