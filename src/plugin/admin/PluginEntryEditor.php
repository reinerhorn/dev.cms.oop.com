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
        error_log('PLUGIN ENTRY EDITOR UUID: ' . $id);

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

        $stmt->bind_param('s', $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            error_log(
                'PLUGIN ENTRY EDITOR: FORM NOT FOUND FOR UUID: ' . $id
            );

            return [
                'plugin_key' => 'forms',
                'id' => '',
                'headline' => '',
                'text' => '',
                'form_style' => '',
                'language' => null,
                'fields' => [],
                'form_id' => null,
                'method' => 'POST',
                'form' => [
                    'fields' => [],
                    'form_id' => null,
                    'method' => 'POST',
                    'config' => []
                ]
            ];
        }

        $config = json_decode(
            $row['config_json'] ?? '{}',
            true
        );

        if (!is_array($config)) {
            $config = [];
        }

        error_log(
            'ENTRY EDITOR CONFIG: '
            . print_r($config, true)
        );

        /*
         * ---------------------------------------------------------
         * ENTITY DATA LOAD
         * ---------------------------------------------------------
         *
         * Wird verwendet, wenn ein bestehender Datensatz
         * über load_id geladen werden soll.
         */

        $entityRow = null;

        $requestData = array_merge(
            $_GET,
            $_POST
        );

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
            $loadId = null;
        }

        if ($loadId !== null) {

            error_log(
                'ENTRY EDITOR LOAD_ID: ' . $loadId
            );

            $entity = $config['entity'] ?? [];

            $table = $entity['table'] ?? null;

            $primaryKey =
                $entity['primary_key']
                ?? 'id';

            if (!$table) {

                error_log(
                    'ENTRY EDITOR: entity table missing'
                );

            } else {

                /*
                 * Tabellenname und Primary-Key stammen
                 * aus der Generator-Konfiguration.
                 */
                $sql = sprintf(
                    'SELECT * FROM %s WHERE %s = ? LIMIT 1',
                    $table,
                    $primaryKey
                );

                $entityStmt = $this->db->prepare($sql);

                if (!$entityStmt) {

                    error_log(
                        'ENTRY EDITOR ENTITY QUERY ERROR: '
                        . $this->db->error
                    );

                } else {

                    $entityStmt->bind_param(
                        's',
                        $loadId
                    );

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

                    error_log(
                        'ENTRY EDITOR ENTITY ROW: '
                        . print_r(
                            $entityRow,
                            true
                        )
                    );

                    $entityStmt->close();
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * FIELDS
         * ---------------------------------------------------------
         */

        $fields = $config['fields'] ?? [];

        if (!is_array($fields)) {
            $fields = [];
        }

        /*
         * ---------------------------------------------------------
         * FIELD VALUE MAPPING
         * ---------------------------------------------------------
         *
         * Datenbankwerte werden in bestehende Form-Felder
         * eingesetzt.
         */

        foreach ($fields as &$field) {

            $name = $field['name'] ?? null;

            /*
             * load_id bzw. xxx_load_id beibehalten.
             */
            if (
                $loadId
                && $name
                && (
                    $name === 'load_id'
                    || str_ends_with(
                        $name,
                        '_load_id'
                    )
                )
            ) {
                $field['value'] = $loadId;

                continue;
            }

            if (!array_key_exists('value', $field)) {
                $field['value'] = null;
            }

            $dbFieldName = $name;

            /*
             * Unterstützt Felder wie:
             *
             * customer__email
             *
             * => email
             */
            if (
                is_string($name)
                && str_contains(
                    $name,
                    '__'
                )
            ) {
                [
                    ,
                    $dbFieldName
                ] = explode(
                    '__',
                    $name,
                    2
                );
            }

            if (
                $dbFieldName
                && is_array($entityRow)
                && array_key_exists(
                    $dbFieldName,
                    $entityRow
                )
            ) {

                $field['value'] =
                    $entityRow[$dbFieldName];

                error_log(
                    'FIELD VALUE SET: '
                    . $dbFieldName
                    . ' => '
                    . print_r(
                        $entityRow[$dbFieldName],
                        true
                    )
                );
            }
        }

        unset($field);

        /*
         * ---------------------------------------------------------
         * OPTIONS RESOLVER
         * ---------------------------------------------------------
         *
         * WICHTIG:
         *
         * Der Generator benutzt:
         *
         * "options_source": {
         *     "type": "tables"
         * }
         *
         * Deshalb darf hier NICHT nur nach
         * $src['table'] gesucht werden.
         */

        foreach ($fields as &$field) {

            if (
                ($field['ui']['type'] ?? null)
                !== 'select'
            ) {
                continue;
            }

            if (
                !isset(
                    $field['ui']['options_source']
                )
            ) {
                continue;
            }

            $src =
                $field['ui']['options_source'];

            if (!is_array($src)) {
                continue;
            }

            $sourceType =
                $src['type'] ?? null;

            /*
             * -----------------------------------------------------
             * MYSQL TABLE LIST
             * -----------------------------------------------------
             *
             * options_source:
             *
             * {
             *     "type": "tables"
             * }
             */

            if ($sourceType === 'tables') {

                $options = [];

                error_log(
                    'ENTRY EDITOR TABLE RESOLVER START'
                );

                $result = $this->db->query(
                    'SHOW TABLES'
                );

                if (!$result) {

                    error_log(
                        'ENTRY EDITOR SHOW TABLES ERROR: '
                        . $this->db->error
                    );

                } else {

                    while (
                        $tableRow =
                            $result->fetch_array(
                                MYSQLI_NUM
                            )
                    ) {

                        $tableName =
                            (string) (
                                $tableRow[0]
                                ?? ''
                            );

                        if ($tableName === '') {
                            continue;
                        }

                        $options[] = [
                            'value' => $tableName,
                            'label' => $tableName
                        ];
                    }

                    $result->free();
                }

                /*
                 * Beide Varianten setzen:
                 *
                 * ui.options
                 * options
                 *
                 * Damit sind sowohl forms.twig
                 * als auch ältere Renderer kompatibel.
                 */

                $field['ui']['options'] =
                    $options;

                $field['options'] =
                    $options;

                error_log(
                    'ENTRY EDITOR TABLE OPTIONS FOUND: '
                    . count($options)
                );

                continue;
            }

            /*
             * -----------------------------------------------------
             * PERMISSIONS
             * -----------------------------------------------------
             */

            if ($sourceType === 'permissions') {

                $options = [];

                $result = $this->db->query(
                    "SELECT id AS value, name AS label
                     FROM permissions
                     ORDER BY name"
                );

                if (!$result) {

                    error_log(
                        'ENTRY EDITOR PERMISSIONS QUERY ERROR: '
                        . $this->db->error
                    );

                } else {

                    while (
                        $rowOpt =
                            $result->fetch_assoc()
                    ) {

                        $options[] = [
                            'value' =>
                                $rowOpt['value'],

                            'label' =>
                                $rowOpt['label']
                        ];
                    }

                    $result->free();
                }

                $field['ui']['options'] =
                    $options;

                $field['options'] =
                    $options;

                continue;
            }

            /*
             * -----------------------------------------------------
             * PAGES
             * -----------------------------------------------------
             */

            if ($sourceType === 'pages') {

                $options = [];

                $result = $this->db->query(
                    "SELECT page_uuid AS value, name AS label
                     FROM page
                     ORDER BY slug"
                );

                if (!$result) {

                    error_log(
                        'ENTRY EDITOR PAGES QUERY ERROR: '
                        . $this->db->error
                    );

                } else {

                    while (
                        $rowOpt =
                            $result->fetch_assoc()
                    ) {

                        $options[] = [
                            'value' =>
                                $rowOpt['value'],

                            'label' =>
                                $rowOpt['label']
                        ];
                    }

                    $result->free();
                }

                $field['ui']['options'] =
                    $options;

                $field['options'] =
                    $options;

                continue;
            }

            /*
             * -----------------------------------------------------
             * SLUGS FROM PAGE
             * -----------------------------------------------------
             */

            if ($sourceType === 'slugs') {

                $options = [];

                $result = $this->db->query(
                    "SELECT slug AS value, slug AS label
                     FROM page
                     WHERE slug IS NOT NULL
                       AND slug <> ''
                     ORDER BY slug"
                );

                if (!$result) {

                    error_log(
                        'ENTRY EDITOR SLUG QUERY ERROR: '
                        . $this->db->error
                    );

                } else {

                    while (
                        $rowOpt =
                            $result->fetch_assoc()
                    ) {

                        $options[] = [
                            'value' =>
                                $rowOpt['value'],

                            'label' =>
                                $rowOpt['label']
                        ];
                    }

                    $result->free();
                }

                $field['ui']['options'] =
                    $options;

                $field['options'] =
                    $options;

                continue;
            }

            /*
             * -----------------------------------------------------
             * SLUG PLACEHOLDERS
             * -----------------------------------------------------
             */

            if ($sourceType === 'slug_placeholders') {

                $options = [];

                $result = $this->db->query(
                    "SELECT id AS value, id AS label
                     FROM slug_placeholder
                     ORDER BY id"
                );

                if (!$result) {

                    error_log(
                        'ENTRY EDITOR SLUG PLACEHOLDER QUERY ERROR: '
                        . $this->db->error
                    );

                } else {

                    while (
                        $rowOpt =
                            $result->fetch_assoc()
                    ) {

                        $options[] = [
                            'value' =>
                                $rowOpt['value'],

                            'label' =>
                                $rowOpt['label']
                        ];
                    }

                    $result->free();
                }

                $field['ui']['options'] =
                    $options;

                $field['options'] =
                    $options;

                continue;
            }

            /*
             * -----------------------------------------------------
             * NORMALER DB OPTIONS SOURCE
             * -----------------------------------------------------
             *
             * Beispiel:
             *
             * {
             *     "table": "customer",
             *     "value_field": "id",
             *     "label_field": "name"
             * }
             */

            $table =
                $src['table']
                ?? null;

            $valueField =
                $src['value_field']
                ?? 'id';

            $labelField =
                $src['label_field']
                ?? 'label';

            if (!$table) {
                continue;
            }

            $sql =
                "SELECT "
                . $valueField
                . " AS value, "
                . $labelField
                . " AS label "
                . "FROM "
                . $table;

            $result =
                $this->db->query($sql);

            $options = [];

            if (!$result) {

                error_log(
                    'ENTRY EDITOR OPTIONS QUERY ERROR: '
                    . $this->db->error
                );

            } else {

                while (
                    $rowOpt =
                        $result->fetch_assoc()
                ) {

                    $options[] = [
                        'value' =>
                            $rowOpt['value'],

                        'label' =>
                            $rowOpt['label']
                    ];
                }

                $result->free();
            }

            $field['ui']['options'] =
                $options;

            $field['options'] =
                $options;
        }

        unset($field);

        /*
         * ---------------------------------------------------------
         * RETURN
         * ---------------------------------------------------------
         */

        return [

            'plugin_key' => 'forms',

            'id' =>
                $row['id'],

            'headline' =>
                $row['headline'] ?? '',

            'text' =>
                $row['text'] ?? '',

            'form_style' =>
                $row['form_style'] ?? 'default',

            'language' =>
                $row['fk_language_id'] ?? null,

            /*
             * Flat structure
             */
            'fields' =>
                $fields,

            'form_id' =>
                $config['form_id'] ?? null,

            'method' =>
                $config['method'] ?? 'POST',

            /*
             * Nested structure
             */
            'form' => [

                'fields' =>
                    $fields,

                'form_id' =>
                    $config['form_id'] ?? null,

                'method' =>
                    $config['method'] ?? 'POST',

                'config' =>
                    $config
            ]
        ];
    }
}
