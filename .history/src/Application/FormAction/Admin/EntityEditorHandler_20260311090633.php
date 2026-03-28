<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Core\CMSApp;
use mysqli;
final class EntityEditorHandler
{
    public function handle(array $postData, array $pageMeta): array
    {
        $db = CMSApp::getDb();
        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');

        // Configuration may come either from form_config or directly from pageMeta
        $config = $pageMeta['form_config'] ?? [];

        if (empty($config) && isset($pageMeta['entity'])) {
            $config = [
                'entity' => $pageMeta['entity'],
                'fields' => $pageMeta['fields'] ?? []
            ];
        }

        if (!isset($config['entity'])) {
            return [
                'success' => false,
                'message' => 'Entity-Konfiguration fehlt.',
                'debug' => [
                    'pageMeta_keys' => array_keys($pageMeta),
                    'pageMeta' => $pageMeta,
                    'post_keys' => array_keys($postData)
                ]
            ];
        }

        $entity = $config['entity'];

        $table = $entity['table'] ?? null;
        $primaryKey = $entity['primary_key'] ?? null;

        if (!$table || !$primaryKey) {
            return [
                'success' => false,
                'message' => 'Entity table oder primary_key fehlt.'
            ];
        }

        $action = $postData['button_action'] ?? 'save';

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
        $primaryValue = $postData[$primaryKey] ?? null;

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

        // Ensure primary key is included if it exists in POST
        if ($primaryValue !== null && $primaryValue !== '' && !in_array("`$primaryKey`", $columns, true)) {
            array_unshift($columns, "`$primaryKey`");
            array_unshift($values, $primaryValue);
            $types = 's' . $types;
        }

        if (empty($columns)) {
            return [
                'success' => false,
                'message' => 'Keine DB-Felder definiert.'
            ];
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $columnsList  = implode(',', $columns);

        $updateColumns = array_filter(
            $columns,
            fn($col) => $col !== "`$primaryKey`"
        );

        $updateList = implode(',',
            array_map(
                fn($col) => "$col = VALUES($col)",
                $updateColumns
            )
        );

$sql = "
    INSERT INTO `$table` ($columnsList)
    VALUES ($placeholders)
    ON DUPLICATE KEY UPDATE $updateList
";
        // TEMP DEBUG: verify handler execution and SQL data
      /*  return [
            'success' => false,
            'message' => 'DEBUG: Handler reached before SQL execution',
            'debug' => [
                'table' => $table,
                'columns' => $columns,
                'values' => $values,
                'types' => $types,
                'sql' => $sql
            ]
        ];*/

        $stmt = $db->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'SQL-Fehler: ' . $db->error
            ];
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Execute-Fehler: ' . $stmt->error
            ];
        }

        $stmt->close();

        $id = $postData[$primaryKey] ?? '';

        return [
            'success'  => true,
            'redirect' => $baseUrl . '?load_id=' . urlencode($id)
        ];
    }
}
