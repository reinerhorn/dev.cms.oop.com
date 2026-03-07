<?php
namespace CMS\Plugin;

use mysqli;

class PluginFormular implements PluginInterface
{
    /**
     * Lädt ein Formular-Plugin-Inhalt per plugin_content_uuid (ein Datensatz aus p_content_formular).
     * Rückgabe: strukturierter Array: ['type','style','headline','text','config' => [fields...], 'lang']
     */
    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array
    {
        $stmt = $db->prepare("
            SELECT id, form_type, form_style, headline, text, config_json, fk_language_id
            FROM p_content_formular
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $pluginContentUuid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) return [];

        $raw = json_decode($row['config_json'] ?? '{}', true);
        $fields = [];

        if (!empty($raw['fields']) && is_array($raw['fields'])) {
            foreach ($raw['fields'] as $fieldName => $definition) {

                // Minimal-Fallback wenn alte Struktur (String)
                if (is_string($definition)) {
                    $fields[] = [
                        'name' => $definition,
                        'type' => 'text',
                        'label' => ucfirst($definition),
                        'required' => false,
                        'options' => []
                    ];
                    continue;
                }

                if (!is_array($definition)) {
                    continue;
                }

                $ui = $definition['ui'] ?? [];
                $label = $definition['label'] ?? [];

                $field = [
                    'name'     => $fieldName,
                    'type'     => $ui['type'] ?? 'text',
                    'label'    => is_array($label) ? ($label['text'] ?? ucfirst($fieldName)) : $label,
                    'required' => !empty($ui['required']),
                    'options'  => []
                ];

                // Select-Optionen automatisch aus DB laden
                if (($ui['type'] ?? null) === 'select' && !empty($ui['options_source'])) {

                    $src = $ui['options_source'];
                    $table = $src['table'] ?? null;
                    $valueField = $src['value_field'] ?? null;
                    $labelField = $src['label_field'] ?? null;

                    if ($table && $valueField && $labelField) {

                        $sql = "SELECT {$valueField}, {$labelField} FROM {$table}";
                        $result = $db->query($sql);

                        if ($result) {
                            while ($opt = $result->fetch_assoc()) {
                                $field['options'][] = [
                                    'value' => $opt[$valueField],
                                    'label' => $opt[$labelField]
                                ];
                            }
                        }
                    }
                }

                $fields[] = $field;
            }
        }

        return [
            'type'   => $row['form_type'] ?? 'forms',
            'style'  => $row['form_style'] ?? 'default',
            'headline'=> $row['headline'] ?? '',
            'text'   => $row['text'] ?? '',
            'config' => ['fields' => $fields],
            'lang'   => $row['fk_language_id'] ?? $language,
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
        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {
            $idx = (int)$r['idx'];
            $uuid = $r['plugin_content_uuid'] ?? null;
            if (!$uuid) continue;

            $block = self::loadByUuid($db, $uuid, $language);
            if (!empty($block)) {
                $out[$idx][] = $block;
            }
        }

        return $out;
    }

    /**
     * Lädt den Formular-Plugin-Inhalt für ContentController nach Sprache.
     */
    public static function loadContentByLanguage(mysqli $db, string $pluginContentUuid, string $language): array
    {
        return self::loadByUuid($db, $pluginContentUuid, $language);
    }
}