<?php
declare(strict_types=1);

namespace CMS\Application\FormData\Admin;
use CMS\Application\FormData\FormDataLoaderInterface;
use CMS\Core\CMSApp;
use mysqli;

final class EntityFormDataLoader implements FormDataLoaderInterface
{
    public function supports(string $formAction): bool
    {
        return $formAction === 'entity_editor';
    }

    public function hydrate(array $block, array $request): array
    {
        $db = CMSApp::getDb();

        if (!isset($block['config']['entity'])) {
            return $block;
        }

        $entity = $block['config']['entity'];

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
        foreach ($block['config']['fields'] as &$field) {

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

        // Primary-Key robust aus Request ermitteln
        $id = $request[$primaryKey]
              ?? $request['id']
              ?? $request['uuid']
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

        foreach ($block['config']['fields'] as &$field) {

            if (($field['db']['type'] ?? '') !== 'column') {
                continue;
            }

            $name = $field['name'];

            if (array_key_exists($name, $data)) {
                $value = $data[$name];

                // Standard-Wert setzen
                $field['ui']['value'] = $value;
                $field['value']       = $value;

                // Falls Select → current value explizit setzen
                if (($field['ui']['type'] ?? '') === 'select') {
                    $field['ui']['current'] = $value;
                }
            }
        }

        /*
        ==========================
        OPTIONAL: Buttons laden
        ==========================
        */
        if (isset($entity['button_table'])) {

            $buttonTable = $entity['button_table'];
            $buttons = [];

            $result = $db->query(
                "SELECT * FROM `$buttonTable` ORDER BY sort_order ASC"
            );

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $buttons[] = $row;
                }
            }

            $block['buttons'] = $buttons;
        }

        return $block;
    }
}
