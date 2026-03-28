<?php

namespace CMS\Core\Controller\Admin;

use CMS\Core\Generator\FormGenerator;
use Twig\Environment;
use mysqli;

class GeneratorController
{


    private mysqli $db;
    private Environment $twig;

    public function __construct(mysqli $db, Environment $twig)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function index(): void
    {
        $generator = new FormGenerator($this->db);

        $tables = $generator->getTables();

        echo $this->twig->render('admin/generator.twig', [
            'tables' => $tables
        ]);
    }

    public function create(): void
    {
        error_log('GENERATOR CREATE CONTROLLER HIT');
        error_log('REQUEST METHOD: ' . $_SERVER['REQUEST_METHOD']);
        error_log('POST DATA: ' . print_r($_POST, true));

        // 🔹 Nur POST erlauben
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /de/admin-generator");
            exit;
        }

        // 🔹 Input absichern
        $formId = $_POST['form_id'] ?? null;
        $table  = $_POST['table'] ?? null;

        error_log('FORM ID: ' . ($formId ?? 'NULL'));
        error_log('TABLE: ' . ($table ?? 'NULL'));

        if ($formId !== 'generator_create_form') {
            error_log('WRONG FORM ID -> EXIT');
            return;
        }

        if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            echo "Ungültiger Tabellenname";
            return;
        }

        $generator = new FormGenerator($this->db);

        try {
            // 🔹 Optional: prüfen ob Tabelle existiert
            $tables = $generator->getTables();

            if (!in_array($table, $tables, true)) {
                throw new \Exception("Tabelle existiert nicht");
            }

            // 🔹 Editor erzeugen
            $generator->createEditor($table);

            // 🔹 Erfolg → Redirect
            header("Location: /de/admin-generator?success=1");
            exit;
        } catch (\Throwable $e) {
            // 🔹 Fehler sauber anzeigen (später Logging)
            echo "Fehler beim Erstellen: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Wird vom ContentController verwendet (Plugin-Loader)
     */
    public static function loadContentByLanguage(mysqli $db, string $uuid, string $language): array
    {
        $generator = new FormGenerator($db);
        $tables = $generator->getTables();

        $fields = [];

        foreach ($tables as $table) {
            $fields[] = [
                'type'  => 'submit',
                'label' => $table,
                'name'  => 'table',
                'value' => $table
            ];
        }

        return [
            'type' => 'forms',
            'form_type' => 'generator_create_form',

            'headline' => 'Formular Generator',

            'config' => [
                'method' => 'POST',
                'fields' => $fields
            ]
        ];
    }
}
