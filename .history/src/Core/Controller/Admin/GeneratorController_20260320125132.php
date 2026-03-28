<?php

namespace CMS\Controller\Admin;

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
        // 🔹 Nur POST erlauben
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin/generator");
            exit;
        }

        // 🔹 Input absichern
        $table = $_POST['table'] ?? null;

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
            header("Location: /admin/generator?success=1");
            exit;

        } catch (\Throwable $e) {
            // 🔹 Fehler sauber anzeigen (später Logging)
            echo "Fehler beim Erstellen: " . htmlspecialchars($e->getMessage());
        }
    }
}
