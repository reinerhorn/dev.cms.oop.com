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
       error_log('ENTER PluginFormular::loadByUuid'); 
       error_log('PLUGIN UUID: ' . $pluginContentUuid);
        // Dynamische Tabellenauflösung über plugin + page_config
        $table = 'p_content_formular'; // fallback
        $formTable = $table;

        $tblStmt = $db->prepare(" 
            SELECT p.table_name
            FROM page_config pc
            LEFT JOIN plugin p ON p.plugin_uuid = pc.fk_plugin_uuid
            WHERE pc.plugin_content_uuid = ?
            LIMIT 1
        ");

        if ($tblStmt) {
            $tblStmt->bind_param('s', $pluginContentUuid);
            $tblStmt->execute();
            $tblRes = $tblStmt->get_result();
            $tblRow = $tblRes ? $tblRes->fetch_assoc() : null;
            $tblStmt->close();

            if (!empty($tblRow['table_name'])) {
                $formTable = $tblRow['table_name'];
            }
        }

        // Sicherheit: Tabellenname validieren
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $formTable)) {
            error_log('INVALID TABLE NAME: ' . $formTable);
            return [];
        }
        $stmt = $db->prepare("
            SELECT id, form_type, form_style, headline, text, config_json, fk_language_id
            FROM {$formTable}
            WHERE pc.id = ?
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

        error_log('RAW JSON: ' . ($row['config_json'] ?? 'NULL'));

        $raw = json_decode($row['config_json'] ?? '{}', true);
        error_log('PLUGIN RAW CONFIG: ' . print_r($raw, true));

        // Ensure form_id exists (must come from JSON)
        if (!isset($raw['form_id'])) {
            error_log('FORM ERROR: form_id missing in config_json');
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON ERROR: ' . json_last_error_msg());
        }

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
              'name'        => $definition['name'] ?? '',
              'type'        => $ui['type'] ?? 'text',
              'id'          => $ui['id'] ?? ($definition['name'] ?? ''),
              'class'       => $ui['class'] ?? '',
              'placeholder' => $ui['placeholder'] ?? '',
              'label'       => is_array($label)
                  ? ($label['text'] ?? ucfirst($definition['name'] ?? ''))
                  : (string)$label,
              'required'    => !empty($definition['db']['required']),
              'options'     => []
          ];

                // Select-Optionen automatisch aus DB laden
                if (
                    ($ui['type'] ?? null) === 'select'
                    && !empty($ui['options_source'])
                    && is_array($ui['options_source'])
                ) {
                    $src        = $ui['options_source'];
                    $optionTable = $src['table'] ?? null;
                    $valueField = $src['value_field'] ?? null;
                    $labelField = $src['label_field'] ?? null;

                    if (
                        $optionTable && $valueField && $labelField &&
                        preg_match('/^[a-zA-Z0-9_]+$/', $optionTable) &&
                        preg_match('/^[a-zA-Z0-9_]+$/', $valueField) &&
                        preg_match('/^[a-zA-Z0-9_]+$/', $labelField)
                    ) {
                        $sql = "SELECT {$valueField}, {$labelField} FROM {$optionTable}";
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
        // vollständige JSON-Konfiguration beibehalten (inkl. entity, form_id, method, etc.)
        $raw['fields'] = $fields;
        error_log('EXIT PluginFormular::loadByUuid');
        return [
            'type'     => $row['form_type'] ?? 'forms',
            'style'    => $row['form_style'] ?? 'default',
            'headline' => $row['headline'] ?? '',
            'text'     => $row['text'] ?? '',
            'config'   => $raw,
            'form_id'  => $raw['form_id'] ?? null,
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
            WHERE pc.fk_page_uuid = ?
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