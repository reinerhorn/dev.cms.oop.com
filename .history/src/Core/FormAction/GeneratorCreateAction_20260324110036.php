<?php
declare(strict_types=1);

namespace CMS\Core\FormAction;

use CMS\Core\Generator\FormGenerator;
use mysqli;

class GeneratorCreateAction
{
    public static function handle(mysqli $db, array $post): array
    {
        error_log('FORM ACTION: GeneratorCreateAction HIT');

        $table = $post['table'] ?? null;

        if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [
                'success' => false,
                'message' => 'Ungültiger Tabellenname'
            ];
        }

        try {
            $generator = new FormGenerator($db);

            $tables = $generator->getTables();

            if (!in_array($table, $tables, true)) {
                throw new \Exception('Tabelle existiert nicht');
            }

            $generator->createEditor($table);

            return [
                'success' => true,
                'message' => 'Editor erfolgreich erstellt'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
