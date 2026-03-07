<?php
namespace CMS\Plugin;

use mysqli;

class PluginFormular implements PluginInterface
{
    /**
     * Lädt ein Formular-Plugin-Inhalt per plugin_content_uuid.
     */
    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array
    {
        $stmt = $db->prepare("
            SELECT id, form_type, form_style, headline, text, config_json, fk_language_id
            FROM p_content_formular
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $pluginContentUuid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return [];
        }

        // --- POST Handling (Save / Delete) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $action = $_POST['action'] ?? null;
            $id     = $_POST['id'] ?? '';

            $rawConfig = json_decode($row['config_json'] ?? '{}', true);
            $entityTable = $rawConfig['entity_table'] ?? null;
            $primaryKey  = $rawConfig['primary_key'] ?? 'id';

            if ($entityTable && preg_match('/^[a-zA-Z0-9_]+$/', $entityTable)) {

                if ($action === 'save') {

                    // Collect field data dynamically
                    $data = [];
                    foreach ($_POST as $key => $value) {
                        if ($key === 'action' || $key === '_csrf') {
                            continue;
                        }
                        $data[$key] = $value;
                    }

                    if ($id === '') {
                        // INSERT
                        $columns = array_keys($data);
                        $placeholders = implode(',', array_fill(0, count($columns), '?'));
                        $types = str_repeat('s', count($columns));

                        $sql = "INSERT INTO {$entityTable} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
                        $stmtInsert = $db->prepare($sql);
                        if ($stmtInsert) {
                            $values = array_values($data);
                            $stmtInsert->bind_param($types, ...$values);
                            $stmtInsert->execute();
                            $stmtInsert->close();
                        }

                    } else {
                        // UPDATE
                        $setParts = [];
                        foreach ($data as $column => $value) {
                            if ($column === $primaryKey) {
                                continue;
                            }
                            $setParts[] = "{$column} = ?";
                        }

                        if (!empty($setParts)) {
                            $types = str_repeat('s', count($setParts)) . 's';
                            $values = [];

                            foreach ($data as $column => $value) {
                                if ($column === $primaryKey) {
                                    continue;
                                }
                                $values[] = $value;
                            }

                            $values[] = $id;

                            $sql = "UPDATE {$entityTable} SET " . implode(',', $setParts) . " WHERE {$primaryKey} = ?";
                            $stmtUpdate = $db->prepare($sql);
                            if ($stmtUpdate) {
                                $stmtUpdate->bind_param($types, ...$values);
                                $stmtUpdate->execute();
                                $stmtUpdate->close();
                            }
                        }
                    }
                }

                if ($action === 'delete' && $id !== '') {
                    $sql = "DELETE FROM {$entityTable} WHERE {$primaryKey} = ?";
                    $stmtDelete = $db->prepare($sql);
                    if ($stmtDelete) {
                        $stmtDelete->bind_param('s', $id);
                        $stmtDelete->execute();
                        $stmtDelete->close();
                    }
                }
            }
        }

        $raw = json_decode($row['config_json'] ?? '{}', true);
        if (!is_array($raw)) {
            $raw = [];
        }

        $fields = [];

        if (!empty($raw['fields']) && is_array($raw['fields'])) {
            foreach ($raw['fields'] as $definition) {

                if (!is_array($definition)) {
                    continue;
                }

                $ui    = $definition['ui'] ?? [];
                $label = $definition['label'] ?? [];

                $field = [
                    'name'     => $definition['name'] ?? '',
                    'type'     => $ui['type'] ?? 'text',
                    'label'    => is_array($label)
                        ? ($label['text'] ?? ucfirst($definition['name'] ?? ''))
                        : (string)$label,
                    'required' => !empty($ui['required']),
                    'options'  => []
                ];

                // Select-Optionen automatisch aus DB laden
                if (
                    ($ui['type'] ?? null) === 'select'
                    && !empty($ui['options_source'])
                    && is_array($ui['options_source'])
                ) {
                    $src        = $ui['options_source'];
                    $table      = $src['table'] ?? null;
                    $valueField = $src['value_field'] ?? null;
                    $labelField = $src['label_field'] ?? null;

                    if (
                        $table && $valueField && $labelField &&
                        preg_match('/^[a-zA-Z0-9_]+$/', $table) &&
                        preg_match('/^[a-zA-Z0-9_]+$/', $valueField) &&
                        preg_match('/^[a-zA-Z0-9_]+$/', $labelField)
                    ) {
                        $sql = "SELECT {$valueField}, {$labelField} FROM {$table}";
                        $result = $db->query($sql);

                        if ($result) {
                            while ($opt = $result->fetch_assoc()) {
                                $field['options'][] = [
                                    'value' => $opt[$valueField] ?? '',
                                    'label' => $opt[$labelField] ?? ''
                                ];
                            }
                        }
                    }
                }

                $fields[] = $field;
            }
        }

        error_log('FORM BLOCK: ' . print_r($fields, true));
        return [
            'type'     => $row['form_type'] ?? 'forms',
            'style'    => $row['form_style'] ?? 'default',
            'headline' => $row['headline'] ?? '',
            'text'     => $row['text'] ?? '',
            'config'   => [
                'fields' => $fields
            ],
            'lang'     => $row['fk_language_id'] ?? $language,
        ];
    }

    /**
     * Lädt alle Formular-Blöcke für eine Seite (page_uuid).
     * Ergebnis: [ idx => [ block1, block2 ] ]
     */
    public static function loadByPage(mysqli $db, string $pageUuid, string $language): array
    {
        $out = [];

        $stmt = $db->prepare("
            SELECT pc.idx, pc.plugin_content_uuid
            FROM page_config pc
            LEFT JOIN plugin p ON p.plugin_uuid = pc.fk_plugin_uuid
            WHERE pc.fk_page_uuid = ?
              AND LOWER(p.name) IN ('forms','register','login-form','register-form')
            ORDER BY pc.idx ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($res && ($r = $res->fetch_assoc())) {
            $idx  = (int)($r['idx'] ?? 0);
            $uuid = $r['plugin_content_uuid'] ?? null;

            if (!$uuid) {
                continue;
            }

            $block = self::loadByUuid($db, $uuid, $language);

            if (!empty($block)) {
                $out[$idx][] = $block;
            }
        }

        $stmt->close();

        return $out;
    }

    /**
     * Alias für ContentController
     */
    public static function loadContentByLanguage(mysqli $db, string $pluginContentUuid, string $language): array
    {
        return self::loadByUuid($db, $pluginContentUuid, $language);
    }
}