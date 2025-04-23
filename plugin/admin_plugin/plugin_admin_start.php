<script src="/function/js/chart.js"></script>
<script src="/function/js/charts-loader.js"></script>
 
    
<?php
 if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
	header('Location:/index.php');
}
//  Zeile 1 spalte 1
if(isset( $_SESSION["admin_a"]));
$istUserAngemeldet = isset( $_SESSION["admin_a"]);
if($istUserAngemeldet){
  $user= $_SESSION["name"] ;
} else {
  $user = "";
}
$datum="";
    $datum .= date('d.m.Y l H:i:s');
    $datum .= '<br> Einen schönen, guten Tag: ' . $user . '<br>';

    $main_db_connection = getDbConnection();
    if (!isset($main_db_connection)) {
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
 
  
  
 
 <div class="admin_container">
     <!-- HEADER -->
     <div class="admin_box">
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
     <div class="admin_box">
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
 
 
 