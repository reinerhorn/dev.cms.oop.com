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
        return $formAction === 'translation_editor';
    }

    public function hydrate(array $block, array $request): array
    {
        $id = $request['id'] ?? null;

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
            if (isset($data[$field['name']])) {
                $field['value'] = $data[$field['name']];
            }
        }

        return $block;
    }
}
