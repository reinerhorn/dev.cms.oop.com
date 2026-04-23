<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Admin;

use CMS\Application\FormData\FormDataLoaderInterface;
use CMS\Core\CMSApp;
use mysqli;

final class EntityFormDataLoader implements FormDataLoaderInterface
{
   public function supports(string $formAction): bool
{
    return true;
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
        Hydration bei ?id=
        ==========================
        */

        // ==========================
        // ID sauber und konsistent ermitteln
        // ==========================
        $id = trim(
            (string) (
                $request['load_id']
                ?? $request[$primaryKey]
                ?? $request['id']
                ?? ''
            )
        );

        // DEBUG: prüfen, ob load_id korrekt ankommt
        error_log('ENTITY LOADER ID: ' . $id);
        error_log('REQUEST DATA: ' . print_r($request, true));

        if ($id === '') {
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

        // load_id Feld explizit setzen (für Select-Current-State)
        foreach ($block['config']['fields'] as &$field) {
            if (($field['name'] ?? '') === 'load_id') {
                $field['value'] = $id;
                break;
            }
        }
        unset($field);

        foreach ($block['config']['fields'] as &$field) {

            $name = $field['name'] ?? null;

            if (!$name) {
                continue;
            }

            if (array_key_exists($name, $data)) {
                $field['value'] = $data[$name];
            }
        }
        unset($field);

        /*
        ==========================
        OPTIONAL: Buttons laden
        ==========================
        */
        if (isset($entity['button_table'])) {

            $buttonTable = $entity['button_table'];
            $buttons = [];

            // DEBUG
            error_log('BUTTON TABLE: ' . $buttonTable);

            $sql = "SELECT * FROM `$buttonTable`";

            // sort_order nur verwenden wenn vorhanden
            $check = $db->query("SHOW COLUMNS FROM `$buttonTable` LIKE 'sort_order'");
            if ($check && $check->num_rows > 0) {
                $sql .= " ORDER BY sort_order ASC";
            }

            $result = $db->query($sql);

            if (!$result) {
                error_log('BUTTON QUERY ERROR: ' . $db->error);
            } else {
                while ($row = $result->fetch_assoc()) {
                    $buttons[] = $row;
                }
            }

            error_log('BUTTONS LOADED: ' . count($buttons));

            $block['buttons'] = $buttons;
        }

        return $block;
    }
}

       
 
