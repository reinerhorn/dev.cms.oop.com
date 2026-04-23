<?php
declare(strict_types=1);


namespace CMS\Application\FormData;

use CMS\Application\Interface\DataFormLoaderInterface;
use CMS\Core\CMSApp;
use mysqli;



final class EntityFormDataLoader implements DataFormLoaderInterface
{
    public function supports(array $block): bool
    {
        return isset($block['config']['entity']);
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

        // =========================
        // Entity Load (load_id / primary key)
        // =========================

        // ==========================
        // ID sauber und konsistent ermitteln
        // ==========================
        $id = '';

        if (!empty($request['load_id'])) {
            $id = (string) $request['load_id'];
        } elseif (!empty($request[$primaryKey])) {
            $id = (string) $request[$primaryKey];
        } elseif (!empty($request['id'])) {
            $id = (string) $request['id'];
        }

        $id = trim($id);

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

            if (!$name || !array_key_exists($name, $data)) {
                continue;
            }

            $field['value'] = $data[$name];
        }
        unset($field);

        return $block;
    }
}

       
 
