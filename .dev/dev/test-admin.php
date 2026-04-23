<!--  ===================================================================
	  Urheberrechtshinweis / Copyright

	  Die Gestaltung, Inhalte und Programmierung dieser Seiten
	  unterliegen dem Urheberrecht. Urheber ist Reiner Horn
	  Eine Verwendung der Inhalte außerhalb der vom Urheber betriebenen
	  Domains ist nicht gestattet. Ein Verstoß gegen diese Bestimmungen
	  wird als Urheberrechtsverletzung betrachtet und bei Bekanntwerdung 
	  unter Einsatz von Rechtsmitteln geahndet.
      Verwndung von der leeren datenbank und code muss eine genehmigung
      des Urhebers eingeholt werden.
      Die Datenbank und der Code sind urheberrechtlich geschützt.
      Die Verwendung der Datenbank und des Codes ist nur mit
      ausdrücklicher Genehmigung des Urhebers gestattet.
      Die Datenbank und der Code dürfen nicht ohne Genehmigung
      des Urhebers kopiert, verbreitet oder veröffentlicht werden.

	 Reiner Horn
	 Huaptstr. 8
	 40597 Düsseldorf
     horm.it@t-online.de
===================================================================  -->

<style>
    body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    margin: 20px;
    padding: 20px;
}

.container {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    max-width: 1100px;
    margin: auto;
}

.box {
    flex: 1;
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    min-width: 200px;
}

h2 {
    text-align: center;
    margin-bottom: 10px;
}

form {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: bold;
    margin-top: 10px;
    display: block;
}

select, input {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

button {
    background: #007BFF;
    color: white;
    padding: 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    width: 48%;
    display: inline-block;
}

button[name="action"][value="delete"] {
    background: #dc3545;
}

button:hover {
    opacity: 0.9;
}

.buttons {
    display: flex;
    justify-content: space-between;
}

img {
    max-width: 100px;
    height: auto;
    display: block;
    margin-top: 10px;
}
    </style>
<?php
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$main_db_connection = getDbConnection();
if (!$main_db_connection) {
    die('<p>Fehler: Datenbankverbindung nicht gesetzt.</p>');
}

// **Verarbeitung von POST-Daten nur für den jeweiligen Bereich**
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    
    if ($type === 'header' || $type === 'footer') {
        $table = ($type === 'header') ? 'header' : 'footer';

        if (isset($_POST['action']) && $_POST['action'] === 'save') {
            $headline = $_POST['headline'] ?? '';
            $link = $_POST['link'] ?? '';
            $language = $_POST['language'] ?? '';
            $label = $_POST['label'] ?? '';
            $version = $_POST['version'] ?? '';
            $css = $_POST['css'] ?? '';
            $role = $_POST['role'] ?? null;
            $images = $_POST['images'] ?? null;

            // **Bild-Upload nur wenn eine Datei hochgeladen wurde**
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/";
                $image_name = time() . "_" . basename($_FILES['image']['name']);
                $upload_file = $upload_dir . $image_name;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_file)) {
                    $images = "/uploads/" . $image_name;
                }
            }

            // **Entscheidung: INSERT oder UPDATE**
            if (empty($_POST['id'])) {
                // **Neuer Eintrag**
                $stmt = $main_db_connection->prepare(
                    "INSERT INTO $table (headline, link, language, label, version, css, images, role) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssssssi', $headline, $link, $language, $label, $version, $css, $images, $role);
            } else {
                // **Vorhandenen Eintrag aktualisieren**
                $id = $_POST['id'];
                $stmt = $main_db_connection->prepare(
                    "UPDATE $table SET headline=?, link=?, language=?, label=?, version=?, css=?, images=?, role=? WHERE id=?"
                );
                $stmt->bind_param('sssssssii', $headline, $link, $language, $label, $version, $css, $images, $role, $id);
            }

            $stmt->execute();
            $stmt->close();
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $stmt = $main_db_connection->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->bind_param('s', $_POST['id']);
            $stmt->execute();
        }
    }
}

// **Header und Footer aus der DB abrufen**
$headers_result = $main_db_connection->query("SELECT * FROM header ORDER BY UNIX_TIMESTAMP(id) DESC LIMIT 1;");
$footers_result = $main_db_connection->query("SELECT * FROM footer ORDER BY UNIX_TIMESTAMP(id) DESC LIMIT 1;");

$headers = [];
while ($row = $headers_result->fetch_assoc()) {
    $headers[$row['id']] = $row;
}

$footers = [];
while ($row = $footers_result->fetch_assoc()) {
    $footers[$row['id']] = $row;
}

// **GET-Parameter für Selektion nutzen, um Überschreibung zu vermeiden**
$selected_header_id = $_GET['header_id'] ?? (key($headers) ?? 0);
$selected_footer_id = $_GET['footer_id'] ?? (key($footers) ?? 0);

$selected_header = $headers[$selected_header_id] ?? [
    'id' => 0, 'headline' => '', 'link' => '', 'language' => '', 
    'label' => '', 'version' => '', 'css' => '', 'images' => '', 'role' => NULL
];

$selected_footer = $footers[$selected_footer_id] ?? [
    'id' => 0, 'headline' => '', 'link' => '', 'language' => '', 
    'label' => '', 'version' => '', 'css' => '', 'images' => '', 'role' => NULL
];
 ?>
 
 <!DOCTYPE html>
 <html lang="de">
 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Admin Header & Footer Editor</title>
     <link rel="stylesheet" href="plugin_admin_footer_editor.css">
 </head>
 <body>
 
 <div class="container">
     <!-- HEADER -->
     <div class="box">
         <h2>Header bearbeiten</h2>
         <form method="post" enctype="multipart/form-data">
             <input type="hidden" name="type" value="header">
 
             <label>Header auswählen:</label>
             <select name="id" onchange="this.form.submit()">
                 <option value="0">Neuer Header</option>
                 <?php foreach ($headers as $id => $header): ?>
                     <option value="<?= $id ?>" <?= ($id == $selected_header_id) ? 'selected' : '' ?>>
                         <?= htmlspecialchars($header['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                     </option>
                 <?php endforeach; ?>
             </select>
 
             <label>Text:</label>
             <input type="text" name="headline" value="<?= htmlspecialchars($selected_header['headline'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
 
             <label>Link:</label>
             <input type="text" name="link" value="<?= htmlspecialchars($selected_header['link'], ENT_QUOTES, 'UTF-8') ?>">
 
             <label>Sprache:</label>
             <input type="text" name="language" value="<?= htmlspecialchars($selected_header['language'], ENT_QUOTES, 'UTF-8') ?>">
 
             <label>Bild-Upload:</label>
             <input type="file" name="image">
 
             <div class="buttons">
                 <button type="submit" name="action" value="save">Speichern</button>
                 <button type="submit" name="action" value="delete">Löschen</button>
             </div>
         </form>
     </div>
 
     <!-- FOOTER -->
     <div class="box">
         <h2>Footer bearbeiten</h2>
         <form method="post" enctype="multipart/form-data">
             <input type="hidden" name="type" value="footer">
 
             <label>Footer auswählen:</label>
             <select name="id" onchange="this.form.submit()">
                 <option value="0">Neuer Footer</option>
                 <?php foreach ($footers as $id => $footer): ?>
                     <option value="<?= $id ?>" <?= ($id == $selected_footer_id) ? 'selected' : '' ?>>
                         <?= htmlspecialchars($footer['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                     </option>
                 <?php endforeach; ?>
             </select>
 
             <label>Text:</label>
             <input type="text" name="headline" value="<?= htmlspecialchars($selected_footer['headline'], ENT_QUOTES, 'UTF-8') ?>">
 
             <label>Link:</label>
             <input type="text" name="link" value="<?= htmlspecialchars($selected_footer['link'], ENT_QUOTES, 'UTF-8') ?>">
 
             <label>Sprache:</label>
             <input type="text" name="language" value="<?= htmlspecialchars($selected_footer['language'], ENT_QUOTES, 'UTF-8') ?>">
 
             <label>Bild-Upload:</label>
             <input type="file" name="image">
 
             <div class="buttons">
                 <button type="submit" name="action" value="save">Speichern</button>
                 <button type="submit" name="action" value="delete">Löschen</button>
             </div>
         </form>
     </div>
 </div>
 
 </body>
 </html>