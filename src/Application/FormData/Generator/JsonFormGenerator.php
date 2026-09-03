<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use InvalidArgumentException;
use mysqli;
use RuntimeException;

final class JsonFormGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Generate a JSON form definition from a database table.
     *
     * The generated form is stored in:
     *
     *     p_content_formular
     *
     * The returned content UUID can later be used by:
     *
     *     page_config.plugin_content_uuid
     */
    public function generate(array $config): array
    {
        error_log(
            '========== JSON FORM GENERATOR START =========='
        );

        error_log(
            'JSON FORM GENERATOR CONFIG: ' .
            print_r($config, true)
        );

        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         */
        $table = trim(
            (string) ($config['table'] ?? '')
        );

        if ($table === '') {
            throw new InvalidArgumentException(
                'JsonFormGenerator: Keine Tabelle angegeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * FORM TYPE
         * ---------------------------------------------------------
         */
        $formType = trim(
            (string) (
                $config['form_type']
                ?? 'entry'
            )
        );

        if (
            !in_array(
                $formType,
                ['entry', 'simple'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'JsonFormGenerator: Ungültiger Formulartyp: ' .
                $formType
            );
        }

        /*
         * ---------------------------------------------------------
         * SAVE KEY
         * ---------------------------------------------------------
         */
        $saveKey = trim(
            (string) (
                $config['save_key']
                ?? ''
            )
        );

        if ($saveKey === '') {
            throw new InvalidArgumentException(
                'JsonFormGenerator: Kein Save Key angegeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * FORM ID
         * ---------------------------------------------------------
         *
         * The form_id is the logical identifier used by the
         * frontend and by ui_button.
         *
         * Example:
         *
         *     adressen_form
         *
         * It is NOT the database UUID.
         */
        $formId = $saveKey;

        /*
         * ---------------------------------------------------------
         * LOAD TABLE COLUMNS
         * ---------------------------------------------------------
         */
        $columns = $this->loadTableColumns(
            $table
        );

        if ($columns === []) {
            throw new RuntimeException(
                'JsonFormGenerator: Die Tabelle "' .
                $table .
                '" besitzt keine verwendbaren Spalten.'
            );
        }

        error_log(
            'JSON FORM GENERATOR COLUMNS: ' .
            print_r($columns, true)
        );

        /*
         * ---------------------------------------------------------
         * BUILD FORM FIELDS
         * ---------------------------------------------------------
         */
        $fields = [];

        foreach ($columns as $column) {
            $field = $this->buildField(
                $column,
                $formType
            );

            if ($field === null) {
                continue;
            }

            $fields[] = $field;
        }

        if ($fields === []) {
            throw new RuntimeException(
                'JsonFormGenerator: Es konnten keine Formularfelder erzeugt werden.'
            );
        }

        /*
         * ---------------------------------------------------------
         * FORM CONFIGURATION
         * ---------------------------------------------------------
         *
         * Keep the structure compatible with PluginFormular.
         *
         * There is intentionally NO button here.
         */
        $formConfig = [
            'form_id' => $formId,
            'method' => 'POST',
            'fields' => $fields,
        ];

        /*
         * JSON encoding.
         */
        $configJson = json_encode(
            $formConfig,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        if ($configJson === false) {
            throw new RuntimeException(
                'JsonFormGenerator: JSON konnte nicht erzeugt werden: ' .
                json_last_error_msg()
            );
        }

        /*
         * Validate the generated JSON before sending it
         * to MariaDB.
         */
        json_decode(
            $configJson,
            true
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'JsonFormGenerator: Erzeugtes JSON ist ungültig: ' .
                json_last_error_msg()
            );
        }

        error_log(
            'JSON FORM GENERATOR JSON: ' .
            $configJson
        );

        /*
         * ---------------------------------------------------------
         * CONTENT UUID
         * ---------------------------------------------------------
         */
        $contentUuid = $this->generateUuid();

        /*
         * ---------------------------------------------------------
         * HEADLINE
         * ---------------------------------------------------------
         */
        $headline = $this->buildHeadline(
            $config
        );

        /*
         * ---------------------------------------------------------
         * STORE FORM
         * ---------------------------------------------------------
         */
        $this->storeForm(
            $contentUuid,
            $formType,
            $headline,
            $configJson
        );

        error_log(
            'JSON FORM GENERATOR CONTENT UUID: ' .
            $contentUuid
        );

        error_log(
            '========== JSON FORM GENERATOR COMPLETE =========='
        );

        return [
            'success' => true,

            /*
             * Database content UUID.
             */
            'content_uuid' => $contentUuid,

            /*
             * Logical form ID.
             */
            'form_id' => $formId,

            'table' => $table,
            'form_type' => $formType,
            'save_key' => $saveKey,

            /*
             * Useful for page_config generation later.
             */
            'plugin_content_uuid' => $contentUuid,

            'field_count' => count($fields),

            'config_json' => $configJson,

            'fields' => $fields,
        ];
    }

    /**
     * Load columns from information_schema.
     */
    private function loadTableColumns(
        string $table
    ): array {
        /*
         * Never insert the table name as a normal SQL parameter
         * into a prepared statement.
         *
         * We therefore validate the identifier first.
         */
        if (!$this->isValidIdentifier($table)) {
            throw new InvalidArgumentException(
                'JsonFormGenerator: Ungültiger Tabellenname: ' .
                $table
            );
        }

        /*
         * Get the current database name from the connection.
         */
        $databaseResult = $this->db->query(
            'SELECT DATABASE() AS database_name'
        );

        if (!$databaseResult) {
            throw new RuntimeException(
                'JsonFormGenerator: Datenbank konnte nicht ermittelt werden: ' .
                $this->db->error
            );
        }

        $databaseRow = $databaseResult->fetch_assoc();

        $database = trim(
            (string) (
                $databaseRow['database_name']
                ?? ''
            )
        );

        $databaseResult->free();

        if ($database === '') {
            throw new RuntimeException(
                'JsonFormGenerator: Keine aktive Datenbank ausgewählt.'
            );
        }

        /*
         * Validate database identifier as well.
         */
        if (!$this->isValidIdentifier($database)) {
            throw new RuntimeException(
                'JsonFormGenerator: Ungültiger Datenbankname.'
            );
        }

        /*
         * Use information_schema because this gives us
         * the complete column metadata.
         */
        $sql = "
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                EXTRA,
                COLUMN_KEY,
                ORDINAL_POSITION
            FROM information_schema.columns
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        if (!$stmt) {
            throw new RuntimeException(
                'JsonFormGenerator: Prepare für Spaltenabfrage fehlgeschlagen: ' .
                $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $database,
            $table
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'JsonFormGenerator: Spaltenabfrage fehlgeschlagen: ' .
                $error
            );
        }

        $result = $stmt->get_result();

        if (!$result) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'JsonFormGenerator: Result der Spaltenabfrage konnte nicht gelesen werden: ' .
                $error
            );
        }

        $columns = [];

        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }

        $result->free();
        $stmt->close();

        return $columns;
    }

    /**
     * Build one JSON form field from a database column.
     */
    private function buildField(
        array $column,
        string $formType
    ): ?array {
        $name = trim(
            (string) (
                $column['COLUMN_NAME']
                ?? ''
            )
        );

        if ($name === '') {
            return null;
        }

        $dataType = strtolower(
            trim(
                (string) (
                    $column['DATA_TYPE']
                    ?? ''
                )
            )
        );

        $columnType = strtolower(
            trim(
                (string) (
                    $column['COLUMN_TYPE']
                    ?? ''
                )
            )
        );

        $nullable = (
            ($column['IS_NULLABLE'] ?? 'YES')
            === 'YES'
        );

        $default = $column['COLUMN_DEFAULT']
            ?? null;

        $extra = strtolower(
            trim(
                (string) (
                    $column['EXTRA']
                    ?? ''
                )
            )
        );

        $columnKey = strtoupper(
            trim(
                (string) (
                    $column['COLUMN_KEY']
                    ?? ''
                )
            )
        );

        /*
         * ---------------------------------------------------------
         * AUTO GENERATED / SYSTEM COLUMNS
         * ---------------------------------------------------------
         *
         * These are generated by the database/application and
         * should not normally be entered manually.
         */
        if (
            str_contains($extra, 'auto_increment')
        ) {
            return null;
        }

        /*
         * UUID / generated identity columns.
         *
         * The current CMS uses UUID ids in several tables.
         * If a column has a generated UUID/default expression,
         * it should not become a manual form field.
         */
        if (
            $this->isGeneratedUuidColumn(
                $name,
                $default,
                $extra
            )
        ) {
            return null;
        }

        /*
         * created_at / updated_at are normally managed by
         * the database/application.
         */
        if (
            in_array(
                strtolower($name),
                [
                    'created_at',
                    'updated_at',
                ],
                true
            )
            && $default !== null
        ) {
            return null;
        }

        /*
         * ---------------------------------------------------------
         * UI TYPE
         * ---------------------------------------------------------
         */
        $uiType = $this->determineUiType(
            $dataType,
            $columnType
        );

        /*
         * ---------------------------------------------------------
         * DATABASE DEFINITION
         * ---------------------------------------------------------
         */
        $dbDefinition = [
            'type' => $dataType,
            'required' => !$nullable,
        ];

        /*
         * Preserve default value where useful.
         */
        if ($default !== null) {
            $dbDefinition['default'] =
                (string) $default;
        }

        /*
         * Primary keys are metadata, but they should not normally
         * be edited through a newly generated entry form.
         *
         * UUID primary keys are therefore omitted.
         */
        if (
            $columnKey === 'PRI'
            && $this->isUuidLikeColumn(
                $name,
                $columnType
            )
        ) {
            return null;
        }

        /*
         * ---------------------------------------------------------
         * UI DEFINITION
         * ---------------------------------------------------------
         */
        $uiDefinition = [
            'type' => $uiType,
        ];

        /*
         * Text-like fields get a sensible placeholder.
         */
        if (
            in_array(
                $uiType,
                [
                    'text',
                    'textarea',
                ],
                true
            )
        ) {
            $uiDefinition['placeholder'] =
                $name;
        }

        /*
         * Numeric fields get a number UI.
         */
        if (
            in_array(
                $uiType,
                [
                    'number',
                ],
                true
            )
        ) {
            $uiDefinition['inputmode'] =
                'numeric';
        }

        /*
         * ---------------------------------------------------------
         * FIELD
         * ---------------------------------------------------------
         */
        return [
            'name' => $name,

            'db' => $dbDefinition,

            'ui' => $uiDefinition,

            'label' => [
                'text' => $this->columnToLabel(
                    $name
                ),
            ],
        ];
    }

    /**
     * Determine the frontend UI type from the SQL type.
     */
    private function determineUiType(
        string $dataType,
        string $columnType
    ): string {
        /*
         * Boolean-like columns.
         */
        if (
            in_array(
                $dataType,
                [
                    'tinyint',
                    'boolean',
                    'bool',
                ],
                true
            )
            && (
                $dataType !== 'tinyint'
                || $columnType === 'tinyint(1)'
                || str_starts_with(
                    $columnType,
                    'tinyint(1)'
                )
            )
        ) {
            return 'checkbox';
        }

        /*
         * Integer / numeric.
         */
        if (
            in_array(
                $dataType,
                [
                    'int',
                    'integer',
                    'bigint',
                    'smallint',
                    'mediumint',
                    'decimal',
                    'numeric',
                    'float',
                    'double',
                ],
                true
            )
        ) {
            return 'number';
        }

        /*
         * Date/time.
         */
        if ($dataType === 'date') {
            return 'date';
        }

        if (
            in_array(
                $dataType,
                [
                    'datetime',
                    'timestamp',
                ],
                true
            )
        ) {
            return 'datetime-local';
        }

        if ($dataType === 'time') {
            return 'time';
        }

        /*
         * Long text.
         */
        if (
            in_array(
                $dataType,
                [
                    'text',
                    'mediumtext',
                    'longtext',
                ],
                true
            )
        ) {
            return 'textarea';
        }

        /*
         * JSON is represented as a textarea.
         */
        if ($dataType === 'json') {
            return 'textarea';
        }

        /*
         * Enum currently gets a select.
         */
        if ($dataType === 'enum') {
            return 'select';
        }

        /*
         * Everything else becomes a normal text field.
         */
        return 'text';
    }

    /**
     * Create a human-readable field label.
     *
     * Examples:
     *
     * user_id
     *     -> User Id
     *
     * first_name
     *     -> First Name
     */
    private function columnToLabel(
        string $column
    ): string {
        $column = str_replace(
            [
                '_',
                '-',
            ],
            ' ',
            trim($column)
        );

        $column = preg_replace(
            '/\s+/',
            ' ',
            $column
        );

        if ($column === null) {
            return '';
        }

        return ucwords(
            strtolower($column)
        );
    }

    /**
     * Determine whether a column is an automatically generated UUID.
     */
    private function isGeneratedUuidColumn(
        string $name,
        mixed $default,
        string $extra
    ): bool {
        $lowerName = strtolower(
            trim($name)
        );

        /*
         * Do not automatically exclude every "id" field.
         *
         * Only UUID-looking primary/system fields are excluded.
         */
        if (
            !in_array(
                $lowerName,
                [
                    'id',
                    'uuid',
                    'page_uuid',
                    'user_uuid',
                    'plugin_uuid',
                    'navigation_uuid',
                ],
                true
            )
        ) {
            return false;
        }

        $defaultString = strtolower(
            trim(
                (string) $default
            )
        );

        if (
            str_contains(
                $defaultString,
                'uuid'
            )
        ) {
            return true;
        }

        if (
            str_contains(
                $extra,
                'uuid'
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check whether a column looks like a UUID.
     */
    private function isUuidLikeColumn(
        string $name,
        string $columnType
    ): bool {
        $lowerName = strtolower(
            $name
        );

        if (
            str_contains(
                $lowerName,
                'uuid'
            )
        ) {
            return true;
        }

        /*
         * Common UUID storage formats.
         */
        if (
            preg_match(
                '/^char\(36\)$/',
                $columnType
            ) === 1
        ) {
            return true;
        }

        return false;
    }

    /**
     * Build a default headline for the generated form.
     */
    private function buildHeadline(
        array $config
    ): string {
        $entity = trim(
            (string) (
                $config['entity']
                ?? ''
            )
        );

        if ($entity !== '') {
            return $entity;
        }

        $table = trim(
            (string) (
                $config['table']
                ?? ''
            )
        );

        return $this->columnToLabel(
            $table
        );
    }

    /**
     * Store generated form in p_content_formular.
     */
    private function storeForm(
        string $contentUuid,
        string $formType,
        string $headline,
        string $configJson
    ): void {
        /*
         * fk_language_id is deliberately NULL.
         *
         * The generated form configuration itself is language
         * neutral. Labels/content can be handled separately.
         */
        $sql = "
            INSERT INTO p_content_formular
            (
                id,
                form_type,
                form_style,
                headline,
                text,
                config_json,
                fk_language_id
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        if (!$stmt) {
            throw new RuntimeException(
                'JsonFormGenerator: Prepare INSERT p_content_formular fehlgeschlagen: ' .
                $this->db->error
            );
        }

        /*
         * Keep form_style/text empty for now.
         *
         * The actual form definition lives in config_json.
         */
        $formStyle = 'default';
        $text = '';
        $languageId = null;

        $stmt->bind_param(
            'sssssss',
            $contentUuid,
            $formType,
            $formStyle,
            $headline,
            $text,
            $configJson,
            $languageId
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'JsonFormGenerator: INSERT in p_content_formular fehlgeschlagen: ' .
                $error
            );
        }

        $stmt->close();
    }

    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(
            16
        );

        /*
         * UUID version 4.
         */
        $data[6] = chr(
            (ord($data[6]) & 0x0f) | 0x40
        );

        /*
         * RFC 4122 variant.
         */
        $data[8] = chr(
            (ord($data[8]) & 0x3f) | 0x80
        );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(
                substr($data, 0, 4)
            ),
            bin2hex(
                substr($data, 4, 2)
            ),
            bin2hex(
                substr($data, 6, 2)
            ),
            bin2hex(
                substr($data, 8, 2)
            ),
            bin2hex(
                substr($data, 10, 6)
            )
        );
    }

    /**
     * Validate SQL identifiers.
     */
    private function isValidIdentifier(
        string $identifier
    ): bool {
        return preg_match(
            '/^[a-zA-Z0-9_]+$/',
            $identifier
        ) === 1;
    }
}
