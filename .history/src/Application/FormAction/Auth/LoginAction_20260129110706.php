<?php
namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;

final class LoginAction implements FormActionInterface
{
    public function handle(array $data): array
    {
        // Login-Logik
        // prüfen, Session setzen, return JSON
    }
}
