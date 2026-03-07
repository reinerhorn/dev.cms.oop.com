<?php
declare(strict_types=1);

namespace CMS\Application\FormData\Admin;

use CMS\Core\CMSApp;
use mysqli;

final class EntityFormDataLoader
{
    public function hydrate(array $block, array $request): array
    {
        $db = CMSApp::getDb();

        if (!isset($block['entity'])) {
            return $block;
        }

        $entity = $block['entity'];

        $table = $entity['table'] ?? null;
        $primaryKey = $entity['primary_key'] ?? null;

        if (!$table || !$primaryKey) {
            return $block;
        }

        /*
        ==========================
        SELECT-OPTIONS automatisch befüllen
        ==========================
        */
        foreach ($block['fields'] as &$field) {

            if (($field['ui']['type'] ?? '') !== 'select') {
                continue;
            }

            if (!isset($field['ui']['options_source'])) {
                continue;
            }

            $source = $field['ui']['options_source'];

            $srcTable = $source['table'];
            $valueField = $source['value_field'];
            $labelField = $source['label_field'];

            $result = $db->query(
                "SELECT `$valueField`, `$labelField`
                 FROM `$srcTable`
                 ORDER BY `$labelField` ASC"
            );

            $options = [];

            while ($row = $result->fetch_assoc()) {
                $options[] = [
                    'value' => $row[$valueField],
                    'label' => $row[$labelField]
                ];
            }

            $field['ui']['options'] = $options;
        }

        /*
        ==========================
        Hydration bei ?id=
        ==========================
        */

        $id = $request[$primaryKey] 
              ?? $request['load_id'] 
              ?? null;

        if (!$id) {
            return $block;
        }

        $stmt = $db->prepare(
            "SELECT * FROM `$table` WHERE `$primaryKey` = ? LIMIT 1"
        );

        $stmt->bind_param("s", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            return $block;
        }

        foreach ($block['fields'] as &$field) {

            if (($field['db']['type'] ?? '') !== 'column') {
                continue;
            }

            $name = $field['name'];

            if (isset($data[$name])) {
                $field['ui']['value'] = $data[$name];
            }
        }

        return $block;
    }
}
