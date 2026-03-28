<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;
use mysqli;
/*var_dump($_POST);
exit;*/
final class EntityEditorHandler
{
    public function handle(array $postData, array $pageMeta): array
    {
        // Generic entity handler: only react if this form has an entity configuration
        if (!isset($pageMeta['form_config']['entity'])) {
            return [
                'success' => false,
                'message' => null
            ];
        }
        $db = CMSApp::getDb();
        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');

        if (!isset($pageMeta['form_config']['entity'])) {
            return [
                'success' => false,
                'message' => 'Entity-Konfiguration fehlt.'
            ];
        }

        $config = $pageMeta['form_config'];
        $entity = $config['entity'];

        $table = $entity['table'] ?? null;
        $primaryKey = $entity['primary_key'] ?? null;

        if (!$table || !$primaryKey) {
            return [
                'success' => false,
                'message' => 'Entity table oder primary_key fehlt.'
            ];
        }

        $action = $postData['action'] ?? 'save';

        /*
        ==========================
        DELETE
        ==========================
        */
        if ($action === 'delete') {

            $id = trim($postData[$primaryKey] ?? '');

            if ($id !== '') {
                $stmt = $db->prepare(
                    "DELETE FROM `$table` WHERE `$primaryKey` = ?"
                );

                $stmt->bind_param("s", $id);
                $stmt->execute();
                $stmt->close();
            }

            return [
                'success'  => true,
                'redirect' => $baseUrl
            ];
        }

        /*
        ==========================
        SAVE (INSERT / UPDATE)
        ==========================
        */

        $columns = [];
        $values  = [];
        $types   = '';

        foreach ($config['fields'] as $field) {

            if (($field['db']['type'] ?? '') !== 'column') {
                continue;
            }

            $name     = $field['name'];
            $required = $field['db']['required'] ?? false;
            $value    = trim($postData[$name] ?? '');

            if ($required && $value === '') {
                return [
                    'success' => false,
                    'message' => "Feld '$name' ist Pflicht."
                ];
            }

            $columns[] = "`$name`";
            $values[]  = $value;
            $types    .= 's';
        }

        if (empty($columns)) {
            return [
                'success' => false,
                'message' => 'Keine DB-Felder definiert.'
            ];
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $columnsList  = implode(',', $columns);

        $updateList = implode(',',
            array_map(
                fn($col) => "$col = VALUES($col)",
                $columns
            )
        );

        $sql = "
            INSERT INTO `$table` ($columnsList)
            VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updateList
        ";

        $stmt = $db->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'SQL-Fehler: ' . $db->error
            ];
        }

        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

        $id = $postData[$primaryKey] ?? '';

        return [
            'success'  => true,
            'redirect' => $baseUrl . '?load_id=' . urlencode($id)
        ];
    }
}
