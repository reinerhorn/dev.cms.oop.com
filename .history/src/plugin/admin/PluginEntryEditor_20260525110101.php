<?php

declare(strict_types=1);

namespace CMS\Plugin\Admin;

final class PluginEntryEditor
{
    public static function loadByUuid(
        \mysqli $db,
        string $uuid,
        string $language
    ): array {
        return (new self($db))->load($uuid);
    }

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

        $config = json_decode($row['config_json'] ?? '[]', true);

        if (!is_array($config)) {
            $config = [];
        }

        // -------------------------------------------------
        // ENTITY DATA LOAD (for select load_id)
        // -------------------------------------------------
        $entityRow = null;

        // -------------------------------------------------
        // LOAD ID AUTO DETECTION
        // unterstützt:
        // - load_id
        // - header_load_id
        // - header_image_load_id
        // usw.
        // -------------------------------------------------
        $requestData = array_merge($_GET, $_POST);

        $loadId = null;

        foreach ($requestData as $key => $value) {

            if (
                is_string($key)
                && str_ends_with($key, '_load_id')
            ) {
                $loadId = $value;
                break;
            }

            if ($key === 'load_id') {
                $loadId = $value;
                break;
            }
        }

        if ($loadId === '') {
            $loadId = null; // "-- neu --"
        }

        if ($loadId) {
            error_log('ENTRY EDITOR LOAD_ID: ' . $loadId);

            $entity = $config['entity'] ?? [];
            $table = $entity['table'] ?? null;
            $primaryKey = $entity['primary_key'] ?? 'id';

            if (!$table) {
                error_log('ENTRY EDITOR: entity table missing');
                $entityRow = null;
            } else {

                $sql = sprintf(
                    'SELECT * FROM %s WHERE %s = ? LIMIT 1',
                    $table,
                    $primaryKey
                );

                $entityStmt = $this->db->prepare($sql);
                $entityStmt->bind_param('s', $loadId);
                $entityStmt->execute();

                $entityRow = $entityStmt
                    ->get_result()
                    ->fetch_assoc();

                error_log(
                    'ENTITY TABLE: '
                    . $table
                    . ' | PRIMARY: '
                    . $primaryKey
                );

                $entityStmt->close();
            }

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
            ];
        }

        error_log('ENTRY EDITOR CONFIG: ' . print_r($config, true));

        // -------------------------------------------------
        // OPTIONS RESOLVER (select fields)
        // -------------------------------------------------
        $fields = $config['fields'] ?? [];

        // -------------------------------------------------
        // FIELD VALUE MAPPING (DB → Form)
        // -------------------------------------------------
        foreach ($fields as &$field) {
            $name = $field['name'] ?? null;

            // -------------------------------------------------
            // KEEP SELECTED LOAD ID
            // damit das Select nicht wieder auf "-- neu --" springt
            // -------------------------------------------------
            if (
                $loadId
                && $name
                && (
                    $name === 'load_id'
                    || str_ends_with($name, '_load_id')
                )
            ) {
                $field['value'] = $loadId;
                continue;
            }

            if (!isset($field['value'])) {
                $field['value'] = null;
            }

            if (
                $name
                && is_array($entityRow)
                && array_key_exists($name, $entityRow)
            ) {
                $field['value'] = $entityRow[$name];

                error_log(
                    'FIELD VALUE SET: '
                    . $name
                    . ' => '
                    . print_r($entityRow[$name], true)
                );
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
                    $field['options'] = $options;
                }
            }
        }
        // unset($field); // Removed because we already unset it after value mapping

        return [
            'plugin_key' => 'forms',

            'id' => $row['id'],
            'headline' => $row['headline'],
            'text' => $row['text'],
            'form_style' => $row['form_style'],
            'language' => $row['fk_language_id'],

            // ✔ FLAT (Frontend kompatibel)
            'fields' => $fields,
            'form_id' => $config['form_id'] ?? null,
            'method' => $config['method'] ?? 'POST',

            // ✔ OPTIONAL nested structure
            'form' => [
                'fields' => $fields,
                'form_id' => $config['form_id'] ?? null,
                'method' => $config['method'] ?? 'POST',
                'config' => $config
            ]
        ];
    }
}
