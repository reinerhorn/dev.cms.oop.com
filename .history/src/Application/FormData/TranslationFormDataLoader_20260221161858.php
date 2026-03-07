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
       /* var_dump('TranslationFormDataLoader läuft');
        exit; */
        return $formAction === 'translation_editor';
    }

    public function hydrate(array $block, array $request): array
    {
        // ID sowohl aus POST als auch GET akzeptieren
        $id = $request['id']
            ?? $_POST['id']
            ?? $_GET['id']
            ?? null;

        // 🔹 Wenn Formular per POST kommt, ID in GET spiegeln
        // verhindert Zurückspringen nach Reload/Redirect
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $_GET['id'] = $id;
        }

        // 🔹 Select-Feld (id) IMMER aus dem Request setzen
        if (isset($block['fields']) && is_array($block['fields'])) {
            foreach ($block['fields'] as &$field) {
                if (($field['name'] ?? '') === 'id') {
                    $field['value'] = $id ?? '';
                }
            }
            unset($field);
        }

        if (!$id) {
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
        if (!isset($block['fields']) || !is_array($block['fields'])) {
            return $block;
        }

        foreach ($block['fields'] as &$field) {
            // 🔹 ID niemals überschreiben (kommt immer aus dem Request)
            if (($field['name'] ?? '') === 'id') {
                continue;
            }

            if (isset($data[$field['name']])) {
                $field['value'] = $data[$field['name']];
            }
        }
        unset($field);

        return $block;
    }
}
