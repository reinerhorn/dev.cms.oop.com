<?php
class SelectGenerator {
    /**
     * Erzeugt ein HTML-Select-Feld.
     *
     * @param string $name             Der Name des Select-Feldes (z. B. "id").
     * @param array $entries           Die Datensätze (z. B. aus der Datenbank).
     * @param string|null $selected    Der aktuell ausgewählte Wert.
     * @param string $defaultLabel     Platzhaltertext für den ersten Eintrag.
     * @param string|null $keyField    Optional: Welches Feld als value genommen wird (Standard: 'id').
     * @param string|null $labelField  Optional: Welches Feld als Label genommen wird (Standard: 'label' oder 'name').
     * @return string                  Das generierte HTML.
     */
    public static function render(
        string $name,
        array $entries,
        ?string $selected = null,
        string $defaultLabel = "Neu auswählen",
        ?string $keyField = 'id',
        ?string $labelField = null
    ): string {
        $html = "<select name=\"" . htmlspecialchars($name) . "\" onchange=\"this.form.submit()\">\n";
        $html .= "<option value=\"\">" . htmlspecialchars($defaultLabel) . "</option>\n";

        foreach ($entries as $entry) {
            $value = htmlspecialchars($entry[$keyField] ?? '');
            $labelKey = $labelField ?? (isset($entry['label']) ? 'label' : (isset($entry['name']) ? 'name' : $keyField));
            $label = htmlspecialchars(trim($entry[$labelKey] ?? '') ?: '[Ohne Bezeichnung]');

            $selectedAttr = ($value === $selected) ? ' selected' : '';
            $html .= "<option value=\"$value\"$selectedAttr>$label</option>\n";
        }

        $html .= "</select>\n";
        return $html;
    }
}