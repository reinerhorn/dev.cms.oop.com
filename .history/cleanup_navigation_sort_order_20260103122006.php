<?php
declare(strict_types=1);

use CMS\Core\Service\NavigationOrderService;

require_once __DIR__ . '/../bootstrap.php'; // DB + Autoload

$db = $container->get('db'); // oder wie du mysqli beziehst

$service = new NavigationOrderService($db);

echo "🔧 Cleanup navigation.sort_order gestartet\n\n";

/**
 * 1️⃣ Alle Parent-IDs ermitteln (inkl. NULL)
 */
$result = $db->query("
    SELECT DISTINCT parent_id
    FROM navigation
");

if (!$result) {
    throw new RuntimeException($db->error);
}

while ($row = $result->fetch_assoc()) {
    $parentId = $row['parent_id']; // NULL oder UUID

    echo "➡️  Normalisiere parent_id = " . ($parentId ?? 'NULL') . "\n";

    $service->normalizeSortOrderForParent($parentId);
}

echo "\n✅ Cleanup abgeschlossen – sort_order ist jetzt sauber.\n";
