<?php


declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class FormController
{
   public function handle():  void
    {
         die('FormController aktiv');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /');
            exit;
        }

        $action = $_POST['action'] ?? '';

        if ($action === '') {
            header('Location: /error');
            exit;
        }

        // Login & Register laufen ausschließlich über cmsloginsession.php
        if ($action === 'login' || $action === 'register') {
            require dirname(__DIR__, 4) . '/cmsloginsession.php';
            return;
        }

        // Alle anderen Formulare (CMS-Formulare)
        $this->handleCustomForm($action);
    }

    private function handleCustomForm(string $action): void
    {
        // Platzhalter für CMS-Formular-Handling
        // z.B. speichern, mailen, plugin-hook, etc.
        header('Location: /');
        exit;
    }
}