<?php

declare(strict_types=1);

namespace CMS\Application\FormData\JsonEditor;
use mysqli;

final class JsonFormBuilderHandler
{
    private const FIELD_REGISTRY = [
        'seo_slug' => [
            'ui' => 'select',
            'table' => 'slug_placeholder',
            'label_field' => 'id'
        ],
        'context_id' => [
            'ui' => 'select',
            'table' => 'context_placeholder',
            'label_field' => 'id'
        ],
        'meta_description' => [
            'ui' => 'textarea'
        ],
        'help_text' => [
            'ui' => 'textarea'
        ],
        'enabled' => [
            'ui' => 'checkbox'
        ],
        'is_active' => [
            'ui' => 'checkbox'
        ]
    ];

    private const LABEL_FIELDS = [
        'page' => 'slug',
        'navigation' => 'seo_slug',
        'plugin' => 'module',
        'permissions' => 'name',
        'trans_language' => 'label',
        'translation_placeholder' => 'id',
        'view_context_audience' => 'id',
        'page_permissions' => 'id',
        'translation' => 'label',
        'slug_placeholder' => 'id',
        'context_placeholder' => 'id',
    ];
    public function __construct(
        private mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta): array
    {
        $config = $this->build($postData);

        $json = json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new \RuntimeException('JSON konnte nicht erzeugt werden.');
        }

        $id = bin2hex(random_bytes(16));

        $tableValue = $postData['table'] ?? [];

        $tables = is_array($tableValue)
            ? array_filter(array_map('trim', $tableValue))
            : [trim((string)$tableValue)];

        $table = $tables[0] ?? '';

        $formType = trim((string)($postData['save_key'] ?? ''));

        if ($formType === '') {
            $formType = count($tables) === 1
                ? $table . '_form'
                : implode('_', $tables) . '_form';
        }
        $formStyle = 'default';

        $labels = [
            'addresses' => 'Adressen',
            'products' => 'Produkte',
            'categories' => 'Kategorien',
            'users' => 'Benutzer'
        ];

        if (count($tables) === 1) {

            $tableLabel = $labels[$table] ?? ucfirst(str_replace('_', ' ', $table));

            $headline = $tableLabel . ' Formular';

            $text = 'Automatisch generiertes Formular für Tabelle ' . $table;

        } else {

            $headline = 'Multi Tabellen Formular';

            $text = 'Automatisch generiertes Formular für Tabellen: ' . implode(', ', $tables);
        }

        $language = 'de';

        $checkStmt = $this->db->prepare(
            'SELECT id
             FROM p_content_formular
             WHERE form_type = ?
             LIMIT 1'
        );

        if (!$checkStmt) {
            throw new \RuntimeException(
                'Duplicate-Check Prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $checkStmt->bind_param('s', $formType);
        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {

            $existing = $checkResult->fetch_assoc();

            $checkStmt->close();

            throw new \RuntimeException(
                'Datensatz bereits vorhanden. Formular "' . $formType . '" existiert bereits (ID: ' . ($existing['id'] ?? '-') . ').'
            );
        }

        $checkStmt->close();

        $stmt = $this->db->prepare(
            'INSERT INTO p_content_formular (
                id,
                form_type,
                form_style,
                headline,
                text,
                config_json,
                fk_language_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new \RuntimeException(
                'Prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssssss',
            $id,
            $formType,
            $formStyle,
            $headline,
            $text,
            $json,
            $language
        );

        if (!$stmt->execute()) {
            throw new \RuntimeException(
                'Insert fehlgeschlagen: ' . $stmt->error
            );
        }

        $stmt->close();

        return [
            'success' => true,
            'message' => 'Formular erfolgreich generiert',
            'config' => $config,
            'saved_id' => $id,
        ];
    }

    public function build(array $post): array
    {
        $tableValue = $post['table'] ?? [];

        $tables = is_array($tableValue)
            ? array_filter(array_map('trim', $tableValue))
            : [trim((string)$tableValue)];

        $formType = trim($post['form_type'] ?? 'simple');
        $saveKey = trim($post['save_key'] ?? '');

        if (empty($tables)) {
            throw new \RuntimeException('Keine Tabelle gewählt.');
        }

        if ($saveKey === '') {
            throw new \RuntimeException('Kein Form Key gesetzt.');
        }

        // -----------------------------------
        // Tabellenstruktur laden
        // -----------------------------------

        $fields = [];
        $relations = [];

$skipFields = [
    'id',
    'created_at',
    'updated_at',
    'deleted_at'
];

        foreach ($tables as $table) {

            error_log('JSON BUILDER TABLE: [' . $table . ']');

            $sql = "SHOW COLUMNS FROM `{$table}`";

            error_log('JSON BUILDER SQL: ' . $sql);

            $result = $this->db->query($sql);

            if (!$result) {
                throw new \RuntimeException(
                    'SQL ERROR: ' . $this->db->error
                );
            }

        // -----------------------------------
        // ENTRY LOADER
        // -----------------------------------

        if ($formType === 'entry' && count($tables) === 1) {

            $fields[] = [

                'name' => $table . '_load_id',

                'db' => [
                    'type' => 'virtual'
                ],

                'ui' => [
                    'type' => 'select',

                    'options_source' => [
                        'table' => $table,
                        'value_field' => 'id',
                        'label_field' => $this->detectLabelField($table)
                    ]
                ],

                'label' => [
                    'text' => 'Eintrag auswählen'
                ]
            ];
        }

        // -----------------------------------
        // DB FELDER
        // -----------------------------------

        while ($column = $result->fetch_assoc()) {

            $name = $column['Field'];
            $type = strtolower($column['Type']);
            $null = $column['Null'] ?? 'YES';
            $key = $column['Key'] ?? '';
            $default = $column['Default'] ?? null;

            if (in_array($name, $skipFields, true)) {
                continue;
            }

            // -----------------------------------
            // UI TYPE ERKENNUNG
            // -----------------------------------

            $uiType = $this->detectUiType(
                $name,
                $type
            );

            $field = [

                'name' => $table . '__' . $name,

                'db' => [
                    'type' => 'column',
                    'required' => ($null === 'NO' && $default === null)
                ],

                'ui' => [
                    'type' => $uiType
                ],

                'label' => [
                    'text' => $this->beautifyLabel($name)
                ]
            ];

            // -----------------------------------
            // ID FELD
            // -----------------------------------

            if ($name === 'id') {

                $field['ui']['readonly'] = true;
            }

            if (
                str_ends_with($name, '_uuid')
                && !str_starts_with($name, 'fk_')
            ) {
                continue;
            }

            if (
                $name === 'slug'
                || $name === 'page_uuid'
                || $name === 'nav_uuid'
                || $name === 'plugin_uuid'
                || $name === 'translation_uuid'
            ) {
                $field['ui']['readonly'] = true;
            }

            // -----------------------------------
            // SELECT FK ERKENNUNG
            // -----------------------------------

            $foreignKey = $this->resolveRelation(
                $table,
                $name
            );

            $foreignKeyColumn = $this->detectReferencedColumn(
                $table,
                $name
            );

            if ($foreignKey !== null) {

                error_log(
                    'FK DETECTED: ' . $name . ' => ' . $foreignKey
                );

                $relations[] = [
                    'from' => $foreignKey,
                    'to' => $table . '.' . $name
                ];

                $field['ui'] = [
                    'type' => 'select',
                    'options_source' => [
                        'table' => $foreignKey,
                        'value_field' => $this->getPreferredValueField(
                            $foreignKey,
                            $foreignKeyColumn
                        ),
                        'label_field' => $this->resolveLabelField($foreignKey)
                    ]
                ];
            }

            $fields[] = $field;
        }

        // -----------------------------------
        // JSON CONFIG
        // -----------------------------------
        }

        error_log(
            'JSON BUILDER TABLES: ' . json_encode($tables)
        );

        return [

            'entity' => [
                'tables' => $tables,
                'table' => count($tables) === 1 ? $tables[0] : null,
                'primary_key' => 'id',
                'relations' => $relations
            ],

            'method' => 'POST',

            'form_id' => $saveKey,

            'fields' => $fields
        ];
    }

    private function getPreferredValueField(
        string $foreignTable,
        ?string $referencedColumn
    ): string {
        if ($referencedColumn !== null) {
            return $referencedColumn;
        }

        return match ($foreignTable) {
            'page' => 'page_uuid',
            'navigation' => 'nav_uuid',
            'plugin' => 'plugin_uuid',
            default => 'id'
        };
    }

    private function resolveRelation(
        string $table,
        string $column
    ): ?string {

        if (
            isset(self::FIELD_REGISTRY[$column]['table'])
        ) {
            return self::FIELD_REGISTRY[$column]['table'];
        }

        if (
            str_starts_with($column, 'fk_')
            && str_ends_with($column, '_uuid')
        ) {
            $guessedTable = substr($column, 3, -5);

            if ($guessedTable !== '' && $this->tableExists($guessedTable)) {
                return $guessedTable;
            }
        }

        return $this->detectForeignKey(
            $table,
            $column
        );
    }

    private function detectForeignKey(
        string $table,
        string $column
    ): ?string {

        $sql = "
            SELECT REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new \RuntimeException(
                'FK Detection Prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return $row['REFERENCED_TABLE_NAME'] ?? null;
    }

    private function detectReferencedColumn(
        string $table,
        string $column
    ): ?string {

        $sql = "
            SELECT REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();

        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return $row['REFERENCED_COLUMN_NAME'] ?? null;
    }

    private function resolveLabelField(string $table): string
    {
        if (isset(self::LABEL_FIELDS[$table])) {
            return self::LABEL_FIELDS[$table];
        }

        return $this->detectLabelField($table);
    }

    // =====================================================
    // TABLE EXISTS
    // =====================================================

    private function tableExists(string $table): bool
    {
        $table = $this->db->real_escape_string($table);

        $sql = "SHOW TABLES LIKE '{$table}'";

        error_log('TABLE EXISTS SQL: ' . $sql);

        $result = $this->db->query($sql);

        if (!$result) {
            throw new \RuntimeException(
                'TABLE EXISTS SQL ERROR: ' . $this->db->error
            );
        }

        return $result->num_rows > 0;
    }

    // =====================================================
    // LABEL FIELD DETECTOR
    // =====================================================

    private function detectLabelField(string $table): string
    {
        $sql = "SHOW COLUMNS FROM `{$table}`";

        error_log('LABEL DETECTOR SQL: ' . $sql);

        $result = $this->db->query($sql);

        if (!$result) {
            throw new \RuntimeException(
                'LABEL DETECTOR SQL ERROR: ' . $this->db->error
            );
        }

        $preferred = [
            'seo_slug',
            'slug',
            'page_uuid',
            'nav_uuid',
            'plugin_uuid',
            'name',
            'title',
            'headline',
            'label',
            'username',
            'email',
            'module',
            'plugin_key',
            'handler_class',
            'alt_text',
            'image_url',
            'path',
            'uuid'
        ];

        $columns = [];

        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        foreach ($preferred as $field) {

            if (in_array($field, $columns, true)) {
                return $field;
            }
        }

        return 'id';
    }

    private function detectUiType(
        string $name,
        string $type
    ): string {
        if (
            isset(self::FIELD_REGISTRY[$name]['ui'])
        ) {
            return self::FIELD_REGISTRY[$name]['ui'];
        }

        $name = strtolower($name);
        $type = strtolower($type);

        if (
            str_contains($name, 'description') ||
            str_contains($name, 'content') ||
            str_contains($name, 'text')
        ) {
            return 'textarea';
        }

        if (
            str_contains($type, 'tinyint(1)') ||
            str_starts_with($name, 'is_') ||
            str_starts_with($name, 'has_') ||
            $name === 'enabled'
        ) {
            return 'checkbox';
        }

        if (
            str_contains($type, 'int') ||
            str_contains($type, 'decimal') ||
            str_contains($type, 'float') ||
            str_contains($type, 'double')
        ) {
            return 'number';
        }

        if (
            str_ends_with($name, '_uuid')
        ) {
            return 'hidden';
        }

        return 'text';
    }

    private function beautifyLabel(string $field): string
    {
        return ucwords(
            str_replace('_', ' ', $field)
        );
    }
}