<?php
declare(strict_types=1);

namespace CMS\Application\FormData\Admin;

use mysqli;

final class EntityFormDataLoader implements FormDataLoaderInterface
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function supports(string $formAction): bool
    {
        return $formAction === 'entity_editor';
    }

    public function hydrate(array $block, array $request): array
    {
        $entity = $block['config']['entity'] ?? '';
        if ($entity === '') {
            return $block;
        }

        $id = trim($request['id'] ?? $request['load_id'] ?? '');

        if (isset($block['config']['fields']) && is_array($block['config']['fields'])) {
            foreach ($block['config']['fields'] as &$field) {
                $fieldName = $field['name'] ?? '';

                if ($fieldName === 'load_id') {
                    $field['value'] = '';
                }

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
            "SELECT * FROM {$entity} WHERE id = ? LIMIT 1"
        );

        $stmt->bind_param('s', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            return $block;
        }

        if (!isset($block['config']['fields']) || !is_array($block['config']['fields'])) {
            return $block;
        }

        foreach ($block['config']['fields'] as &$field) {
            $fieldName = $field['name'] ?? '';

            if ($fieldName === 'load_id') {
                $field['value'] = $id;
                continue;
            }

            if (isset($data[$fieldName])) {
                $field['value'] = $data[$fieldName];
            }
        }
        unset($field);

        $block['config']['fields'] = $block['config']['fields'];

        return $block;
    }
}
