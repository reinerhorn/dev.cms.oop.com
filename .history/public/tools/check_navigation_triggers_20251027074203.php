<?php
/**
 * Prüft, ob alle Navigation-Trigger korrekt existieren.
 * Ort: /public/tools/check_navigation_triggers.php
 */

$mysqli = new mysqli("localhost", "root", "101@TanZen@101", "dbs_cms_oop");

if ($mysqli->connect_errno) {
    die("<strong style='color:red;'>❌ Fehler bei Verbindung:</strong> " . $mysqli->connect_error);
}

echo "<h2>🧩 Trigger-Statusprüfung für Tabelle <code>navigation</code></h2>";

// 🔹 Erwartete Trigger
$expectedTriggers = [
    'trg_navigation_sort_after_insert',
    'trg_navigation_sort_after_update',
    'trg_navigation_after_insert',
    'trg_navigation_after_update',
    'trg_navigation_after_delete'
];

// 🔹 Trigger aus DB laden
$query = "SHOW TRIGGERS LIKE 'navigation'";
$result = $mysqli->query($query);

if (!$result) {
    die("<strong style='color:red;'>❌ Fehler bei Abfrage:</strong> " . $mysqli->error);
}

$existing = [];
while ($row = $result->fetch_assoc()) {
    $existing[] = $row['Trigger'];
}

echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-family:Arial;'>";
echo "<tr style='background:#eee; font-weight:bold;'><td>Trigger-Name</td><td>Status</td><td>Aktion</td><td>Event</td></tr>";

foreach ($expectedTriggers as $trigger) {
    if (in_array($trigger, $existing)) {
        // 🔹 Detailinformationen holen
        $details = $mysqli->query("SHOW TRIGGERS WHERE `Trigger` = '{$trigger}'")->fetch_assoc();
        $event = $details['Event'] ?? '?';
        $timing = $details['Timing'] ?? '?';
        echo "<tr><td>{$trigger}</td><td style='color:green;'>✅ vorhanden</td><td>{$timing}</td><td>{$event}</td></tr>";
    } else {
        echo "<tr><td>{$trigger}</td><td style='color:red;'>❌ fehlt</td><td colspan='2'>Bitte neu anlegen</td></tr>";
    }
}

echo "</table>";
echo "<p><strong>💡 Tipp:</strong> Falls Trigger fehlen, führe das Skript <code>restore_navigation_triggers.sql</code></p>";

$mysqli->close();
?>