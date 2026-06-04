<?php

declare(strict_types=1);

namespace CMS\Application\FormData\JsonEditor;
use mysqli;

final class JsonFormBuilderHandler
{
    public function __construct(
        private mysqli $db
    ) {}

    public function handle(array $postData, array $pageMeta): array
    {
        $config = $this->build($postData);

        return [
            'success' => true,
            'message' => 'Formular erfolgreich generiert',
            'config' => $config,
        ];
    }

    public function build(array $post): array
    {
        $table = trim($post['table'] ?? '');
        $formType = trim($post['form_type'] ?? 'simple');
        $saveKey = trim($post['save_key'] ?? '');

        if ($table === '') {
            throw new \RuntimeException('Keine Tabelle gewählt.');
        }

        if ($saveKey === '') {
            throw new \RuntimeException('Kein Form Key gesetzt.');
        }

        // -----------------------------------
        // Tabellenstruktur laden
        // -----------------------------------

        $result = $this->db->query("
            SHOW COLUMNS FROM {$table}
        ");

        if (!$result) {
            throw new \RuntimeException(
                'Tabelle konnte nicht gelesen werden.'
            );
        }

        $fields = [];

        // -----------------------------------
        // ENTRY LOADER
        // -----------------------------------

        if ($formType === 'entry') {

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

            // -----------------------------------
            // UI TYPE ERKENNUNG
            // -----------------------------------

            $uiType = $this->detectUiType(
                $name,
                $type
            );

            $field = [

                'name' => $name,

                'db' => [
                    'type' => 'column',
                    'required' => ($null === 'NO')
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

            // -----------------------------------
            // SELECT FK ERKENNUNG
            // -----------------------------------

            if (
                str_starts_with($name, 'fk_')
                || str_ends_with($name, '_id')
            ) {

                $relatedTable = $this->guessRelatedTable($name);

                if ($this->tableExists($relatedTable)) {

                    $field['ui'] = [

                        'type' => 'select',

                        'options_source' => [
                            'table' => $relatedTable,
                            'value_field' => 'id',
                            'label_field' => $this->detectLabelField(
                                $relatedTable
                            )
                        ]
                    ];
                }
            }

            $fields[] = $field;
        }

        // -----------------------------------
        // JSON CONFIG
        // -----------------------------------

        return [

            'entity' => [
                'table' => $table,
                'primary_key' => 'id'
            ],

            'method' => 'POST',

            'form_id' => $saveKey,

            'fields' => $fields
        ];
    }

    // =====================================================
    // UI TYPE DETECTION
    // =====================================================

    private function detectUiType(
        string $name,
        string $type
    ): string {

        if (
            str_contains($type, 'tinyint(1)')
            || str_starts_with($name, 'is_')
            || $name === 'enabled'
        ) {
            return 'checkbox';
        }

        if (
            str_contains($type, 'text')
            || str_contains($name, 'content')
            || str_contains($name, 'description')
        ) {
            return 'textarea';
        }

        if (
            str_contains($type, 'int')
            || str_contains($type, 'decimal')
        ) {
            return 'number';
        }

        return 'text';
    }

    // =====================================================
    // LABEL BUILDER
    // =====================================================

    private function beautifyLabel(string $name): string
    {
        $name = str_replace('_', ' ', $name);

        return ucwords($name);
    }

    // =====================================================
    // FK TABLE GUESSER
    // =====================================================

    private function guessRelatedTable(string $field): string
    {
        $field = preg_replace('/^fk_/', '', $field);
        $field = preg_replace('/_id$/', '', $field);

        return $field;
    }

    // =====================================================
    // TABLE EXISTS
    // =====================================================

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SHOW TABLES LIKE ?
        ");

        $stmt->bind_param("s", $table);
        $stmt->execute();

        $exists = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    // =====================================================
    // LABEL FIELD DETECTOR
    // =====================================================

    private function detectLabelField(string $table): string
    {
        $result = $this->db->query("
            SHOW COLUMNS FROM {$table}
        ");

        $preferred = [
            'headline',
            'title',
            'label',
            'name',
            'username',
            'email'
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
}