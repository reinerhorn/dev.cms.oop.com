<?php
declare(strict_types=1);

namespace CMS\Application\FormData;

use mysqli;
final class TranslationFormDataLoader implements FormDataLoaderInterface
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

  public function supports(string $formAction): bool
    {
  
       /*var_dump('TranslationFormDataLoader läuft');
        exit; */
        return $formAction === 'translation_editor';
    } 
 
    public function hydrate(array $block, array $request): array
    {
        /*var_dump('HYDRATE START', $request, $_GET);
        exit;*/

        // ID ausschließlich aus dem übergebenen Request verwenden
        $id = trim(
            $request['id']
            ?? $request['load_id']
            ?? ''
        );

        // 🔹 Wenn keine ID gesetzt ist → Reset (Neu-Funktion)
        if (isset($block['config']['fields']) && is_array($block['config']['fields'])) {
            foreach ($block['config']['fields'] as &$field) {
                $fieldName = $field['name'] ?? '';

                // Select-Feld load_id zurücksetzen
                if ($fieldName === 'load_id') {
                    $field['value'] = '';
                }

                // DB-Spalten (column) zurücksetzen
                if (($field['db']['type'] ?? '') === 'column') {
                    $field['value'] = '';
                }
            }
            unset($field);
        }

        if ($id === '') {
            return $block;
        }

        $stmt = $this->db->prepare(
            "SELECT id, label, flag_path
             FROM trans_language
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->bind_param('s', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            return $block;
        }

        // Absicherung: fields muss existieren und ein Array sein
        if (!isset($block['config']['fields']) || !is_array($block['config']['fields'])) {
            return $block;
        }

        foreach ($block['config']['fields'] as &$field) {
            $fieldName = $field['name'] ?? '';

            // Select-Feld load_id setzen
            if ($fieldName === 'load_id') {
                $field['value'] = $id;
                continue;
            }

            if (isset($data[$fieldName])) {
                $field['value'] = $data[$fieldName];
            }
        }
        unset($field);

        return $block;
    }
}
