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
        // 🔹 Nur POST erlauben
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /de/admin-generator");
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
        $stmt = $db->prepare("\n            SELECT form_type, form_style, headline, text, config_json, fk_language_id\n            FROM p_formular_gernerator\n            WHERE id = ?\n            LIMIT 1\n        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $uuid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return [];
        }

        $raw = json_decode($row['config_json'] ?? '{}', true);

        if (!is_array($raw)) {
            $raw = [];
        }

        // 🔥 AUTO-NORMALIZER
        if (!isset($raw['fields'])) {
            $raw = [
                'entity' => [
                    'table' => 'auto_table',
                    'primary_key' => 'id'
                ],
                'method' => 'POST',
                'fields' => [$raw]
            ];
        }

        return [
            'type'     => 'forms',
            'style'    => $row['form_style'] ?? 'default',
            'headline' => $row['headline'] ?? '',
            'text'     => $row['text'] ?? '',
            'config'   => $raw,
            'lang'     => $row['fk_language_id'] ?? $language,
        ];
    }
}
