
<style> 
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    margin: 0;
    padding: 20px;
}

.header-container {
    display: flex;
    align-items: center;
    background: #333;
    padding: 10px;
    border-radius: 5px;
}

.header-container a {
    color: #fff;
    text-decoration: none;
    font-size: 20px;
    margin-left: 10px;
}

.header-container img.logo {
    max-height: 50px;
    border-radius: 5px;
}

form {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 400px;
    margin-top: 20px;
}

form div {
    margin-bottom: 15px;
}

label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

input[type="text"], select, input[type="file"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background: #28a745;
    color: #fff;
    border: none;
    padding: 10px 15px;
    cursor: pointer;
    border-radius: 5px;
}

button:hover {
    background: #218838;
}

button[name="action"][value="delete"] {
    background: #dc3545;
}

button[name="action"][value="delete"]:hover {
    background: #c82333;
}

</style>

<?php
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$main_db_connection = getDbConnection();
if (!isset($main_db_connection)) {
  die('<p>Fehler: Datenbankverbindung nicht gesetzt.</p>');
}

// Verarbeiten von POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
      if ($_POST['action'] === 'save') {
          $fotoPath = $_POST['foto'] ?? '';

          if (!empty($_FILES['foto']['name'])) {
              $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/";
              $uploadFile = $uploadDir . basename($_FILES['foto']['name']);
              if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadFile)) {
                  $fotoPath = "/uploads/" . basename($_FILES['foto']['name']);
              }
          }

          $stmt = $main_db_connection->prepare('REPLACE INTO header (id, text, link, images, label, css, foto) VALUES (?, ?, ?, ?, ?, ?, ?)');
          $stmt->bind_param('issssss', $_POST['id'], $_POST['text'], $_POST['link'], $_POST['images'], $_POST['label'], $_POST['css'], $fotoPath);
          $stmt->execute();
      } elseif ($_POST['action'] === 'delete') {
          $stmt = $main_db_connection->prepare('DELETE FROM header WHERE id = ?');
          $stmt->bind_param('i', $_POST['id']);
          $stmt->execute();
      }
  }
}

// Laden der Header-Daten
$stmt = $main_db_connection->prepare('SELECT * FROM header');
$stmt->execute();
$header_result = $stmt->get_result();

$headers = [];
while ($rec = $header_result->fetch_assoc()) {
  $headers[$rec['id']] = $rec;
}

$selected_id = $_POST['id'] ?? key($headers) ?? 0;
$selected_header = $headers[$selected_id] ?? ['id' => 0, 'text' => '', 'link' => '', 'images' => '', 'label' => '', 'css' => '', 'foto' => ''];

 

// Formular zur Bearbeitung
echo '<form method="post" enctype="multipart/form-data">
  <div>
      <label for="header_select">Header auswählen:</label>
      <select name="id" id="header_select" onchange="this.form.submit()">
          <option value="0">Neuer Header</option>';
          foreach ($headers as $id => $header) {
              $selected = ($id == $selected_id) ? 'selected' : '';
              echo '<option value="' . $id . '" ' . $selected . '>' . htmlspecialchars($header['label'], ENT_QUOTES, 'UTF-8') . '</option>';
          }
      echo '</select>
  </div>

  <input type="text" name="text" value="' . htmlspecialchars($selected_header['text'], ENT_QUOTES, 'UTF-8') . '" placeholder="Text">
  <input type="text" name="link" value="' . htmlspecialchars($selected_header['link'], ENT_QUOTES, 'UTF-8') . '" placeholder="Link">
  <input type="text" name="images" value="' . htmlspecialchars($selected_header['images'], ENT_QUOTES, 'UTF-8') . '" placeholder="Bild URL">
  <input type="text" name="label" value="' . htmlspecialchars($selected_header['label'], ENT_QUOTES, 'UTF-8') . '" placeholder="Label">
  <input type="text" name="css" value="' . htmlspecialchars($selected_header['css'], ENT_QUOTES, 'UTF-8') . '" placeholder="CSS-Klasse">
  <input type="file" name="foto">
  <input type="hidden" name="existing_foto" value="' . htmlspecialchars($selected_header['foto'], ENT_QUOTES, 'UTF-8') . '">
  
  <div>
      <button type="submit" name="action" value="save">Speichern</button>
      <button type="submit" name="action" value="delete">Löschen</button>
  </div>
</form>';

#$stmt->close();
?>