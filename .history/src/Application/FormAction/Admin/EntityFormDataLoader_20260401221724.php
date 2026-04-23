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
        BUTTONS LADEN (form_button + ui_button)
        ==========================
        */

        $formId = $block['form_id'] ?? null;

        if ($formId) {

            error_log('BUTTON LOADER FORM_ID: ' . $formId);

            $buttons = [];

            $stmt = $db->prepare(
                'SELECT button_action, permission_id 
                    FROM ui_button 
                    WHERE button_action = ? 
                   AND enabled = 1 
                LIMIT 1'
            );

            if ($stmt) {
                $stmt->bind_param("s", $formId);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $buttons[] = [
                        'button_type'      => $row['button_type'] ?? 'submit',
                        'variant'          => $row['variant'] ?? 'primary',
                        'action'           => $row['button_action'] ?? null,
                        'permission_id'    => $row['permission_id'],
                        'label_key'        => $row['label_key'] ?? 'SUBMIT',
                        'confirm_required' => (bool)($row['confirm_required'] ?? false),
                    ];
                }

                $stmt->close();
            } else {
                error_log('BUTTON PREPARE ERROR: ' . $db->error);
            }

            error_log('BUTTONS LOADED: ' . print_r($buttons, true));

            $block['buttons'] = $buttons;
        }
        error_log('FINAL BLOCK BUTTONS: ' . print_r($block['buttons'] ?? 'NO BUTTONS', true));
        return $block;
    }
}
