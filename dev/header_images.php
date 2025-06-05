<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/session.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$connection = getDbConnection();

// Beispiel: Alle Header mit zugehörigen Bildern anzeigen
$headers = $connection->query("SELECT * FROM header ORDER BY id DESC");

while ($header = $headers->fetch_assoc()) {
    echo "<h3>Header: {$header['label']} (Sprache: {$header['language']}, Rolle: {$header['role']})</h3>";
    
    $stmt = $connection->prepare("SELECT * FROM header_images WHERE header_id = ?");
    $stmt->bind_param("s", $header['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<ul>";
    while ($img = $result->fetch_assoc()) {
        echo "<li>
            <img src='{$img['image_url']}' alt='{$img['alt_text']}' width='100'>
            <a href='{$img['link_url']}' target='_blank'>{$img['link_url']}</a>
            <form method='post' action=''>
                <input type='hidden' name='delete_image_id' value='{$img['id']}'>
                <button type='submit'>Löschen</button>
            </form>
        </li>";
    }
    echo "</ul>";

    echo '
    <style>
        .dropzone {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
            cursor: pointer;
            margin: 10px 0;
        }
    </style>
    <form method="post" action="" enctype="multipart/form-data" class="dropzone-form" id="dropzone-form-'. $header['id'] .'">
        <input type="hidden" name="header_id" value="'. $header['id'] .'">
        <input type="text" name="alt_text" placeholder="Alt-Text"><br>
        <input type="text" name="link_url" placeholder="Link URL"><br>
        <div class="dropzone" onclick="document.getElementById(\'fileinput-'. $header['id'] .'\').click();">
            Hier Bild per Drag & Drop ablegen oder klicken
            <input type="file" name="image_file" accept="image/*" style="display:none;" id="fileinput-'. $header['id'] .'">
        </div>
        <button type="submit" name="upload_image">Bild hinzufügen</button>
    </form><hr>';
}

// Bild speichern
if (isset($_POST['upload_image']) && isset($_FILES['image_file'])) {
    $target_dir = "/images/uploads/";
    $target_path = $target_dir . basename($_FILES["image_file"]["name"]);
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $target_path;

    if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $full_path)) {
        $stmt = $connection->prepare("INSERT INTO header_images (header_id, image_url, link_url, alt_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $_POST['header_id'], $target_path, $_POST['link_url'], $_POST['alt_text']);
        $stmt->execute();
        header("Location: " . $_SERVER['REQUEST_URI']);
    } else {
        echo "Fehler beim Hochladen.";
    }
}

// Bild löschen
if (isset($_POST['delete_image_id'])) {
    $stmt = $connection->prepare("DELETE FROM header_images WHERE id = ?");
    $stmt->bind_param("s", $_POST['delete_image_id']);
    $stmt->execute();
    header("Location: " . $_SERVER['REQUEST_URI']);
}
?>
