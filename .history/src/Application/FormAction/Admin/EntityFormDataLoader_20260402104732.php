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

        return $block;
    }
}

       
 
