<?php

declare(strict_types=1);

namespace CMS\Plugin\Admin;

final class PluginEntryEditor
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                config_json,
                form_style,
                headline,
                text,
                fk_language_id
            FROM p_content_formular
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // -------------------------------------------------
        // ENTITY DATA LOAD (for select load_id)
        // -------------------------------------------------
        $entityRow = null;

        // load_id robust lesen (GET + POST)
        $loadId = $_GET['load_id'] ?? $_POST['load_id'] ?? null;

        if ($loadId === '') {
            $loadId = null; // "-- neu --"
        }

        if ($loadId) {
            error_log('ENTRY EDITOR LOAD_ID: ' . $loadId);

            $entityStmt = $this->db->prepare("
                SELECT * FROM header WHERE id = ? LIMIT 1
            ");
            $entityStmt->bind_param("s", $loadId);
            $entityStmt->execute();

            $entityRow = $entityStmt->get_result()->fetch_assoc();
            $entityStmt->close();

            error_log('ENTRY EDITOR ENTITY ROW: ' . print_r($entityRow, true));
        }

        if (!$row) {
            // -------------------------------------------------
            // NEW MODE → config laden + Werte resetten
            // -------------------------------------------------
            $config = [];

            // Falls eine config_json existiert (z.B. initialer Entry)
            if (!empty($id)) {
                $stmt = $this->db->prepare("
                    SELECT config_json 
                    FROM p_content_formular
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $cfgRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $config = json_decode($cfgRow['config_json'] ?? '[]', true);
                if (!is_array($config)) {
                    $config = [];
                }
            }

            $fields = $config['fields'] ?? [];

            // 👉 WICHTIG: alle Werte resetten
            foreach ($fields as &$field) {
                $field['value'] = null;
            }
            unset($field);

            return [
                'plugin_key' => 'forms',
                'data' => [
                    'id' => '',
                    'headline' => '',
                    'text' => '',
                    'form_style' => '',
                    'language' => null,

                    'fields' => $fields,
                    'form_id' => $config['form_id'] ?? null,
                    'method' => $config['method'] ?? 'POST',

                    'form' => [
                        'fields' => $fields,
                        'form_id' => $config['form_id'] ?? null,
                        'method' => $config['method'] ?? 'POST',
                        'config' => $config
                    ]
                ]
            ];
        }

        $config = json_decode($row['config_json'] ?? '[]', true);
        if (!is_array($config)) {
            $config = [];
        }

        // -------------------------------------------------
        // OPTIONS RESOLVER (select fields)
        // -------------------------------------------------
        $fields = $config['fields'] ?? [];

        // -------------------------------------------------
        // FIELD VALUE MAPPING (DB → Form)
        // -------------------------------------------------
        foreach ($fields as &$field) {
            $name = $field['name'] ?? null;

            // Default: immer reset (wichtig für "neu")
            $field['value'] = null;

            if ($name && $entityRow && array_key_exists($name, $entityRow)) {
                $field['value'] = $entityRow[$name];
            }
        }
        unset($field);

        foreach ($fields as &$field) {

            if (
                ($field['ui']['type'] ?? '') === 'select' &&
                isset($field['ui']['options_source'])
            ) {
                $src = $field['ui']['options_source'];

                $table = $src['table'] ?? null;
                $valueField = $src['value_field'] ?? 'id';
                $labelField = $src['label_field'] ?? 'label';

                if ($table) {
                    $sql = "SELECT {$valueField} AS value, {$labelField} AS label FROM {$table}";
                    $result = $this->db->query($sql);

                    $options = [];

                    if ($result) {
                        while ($rowOpt = $result->fetch_assoc()) {
                            $options[] = [
                                'value' => $rowOpt['value'],
                                'label' => $rowOpt['label']
                            ];
                        }
                    }

                    $field['ui']['options'] = $options;
                }
            }
        }
        // unset($field); // Removed because we already unset it after value mapping

        return [
            'plugin_key' => 'forms',
            'data' => [
                'id' => $row['id'],
                'headline' => $row['headline'],
                'text' => $row['text'],
                'form_style' => $row['form_style'],
                'language' => $row['fk_language_id'],

                // ✔ FLAT (alt kompatibel)
                'fields' => $fields,
                'form_id' => $config['form_id'] ?? null,
                'method' => $config['method'] ?? 'POST',

                // ✔ NESTED (neu kompatibel)
                'form' => [
                    'fields' => $fields,
                    'form_id' => $config['form_id'] ?? null,
                    'method' => $config['method'] ?? 'POST',
                    'config' => $config
                ]
            ]
        ];
    }
}

