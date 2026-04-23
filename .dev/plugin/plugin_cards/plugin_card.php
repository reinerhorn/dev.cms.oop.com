 <?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$db_connector = getDbConnection();

// SQL-Abfrage, um mehrere Karten mit zugehörigem Inhalt zu laden
$sql = "
    SELECT s.id AS stack_id, c.headline, c.text, c.link 
    FROM p_card_stack s
    JOIN p_card_editor e ON s.id = e.fk_cardstack_id
    JOIN p_card_content c ON e.id = c.fk_card_id
    ORDER BY s.id DESC, c.id DESC 
    LIMIT 6
";

$result = $db_connector->query($sql);

$cards_three = "";
$cards_one = "";
$counter = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $card = "<div>
                    <h3>" . htmlspecialchars($row["headline"]) . "</h3>
                    <p>" . nl2br(htmlspecialchars($row["text"])) . "</p>";

        if (!empty($row["link"])) {
            $card .= "<a href='" . htmlspecialchars($row["link"]) . "' target='_blank'>Mehr erfahren</a>";
        }
        $card .= "</div>";

        if ($counter < 2 || $counter == 3) {
            $cards_three .= $card;
        } else {
            $cards_one .= $card;
        }
        $counter++;
    }
} else {
    $cards_three = "<p>Keine Karten verfügbar.</p>";
}


?>

<div class="card_container">
    <div class="three">
        <?= $cards_three ?>
    </div>
    <div class="one">
        <?= $cards_one ?>
    </div>
 </div>
    
 