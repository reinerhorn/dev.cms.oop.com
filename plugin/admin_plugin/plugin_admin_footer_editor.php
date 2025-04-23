
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$main_db_connection = getDbConnection();
if (!isset($main_db_connection)) {
  die('<p>Fehler: Datenbankverbindung nicht gesetzt.</p>');
}

// Verarbeiten von POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
      if ($_POST['action'] === 'save') {
         

          $stmt = $main_db_connection->prepare('REPLACE INTO footer (id, headline, link, language, label, version, css, images, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
          $stmt->bind_param('isssssssi', $_POST['id'], $_POST['headline'], $_POST['link'], $_POST['language'], $_POST['label'], $_POST['version'], $_POST['css'], $_POST['images'], $_POST['role']);
          $stmt->execute();
      } elseif ($_POST['action'] === 'delete') {
          $stmt = $main_db_connection->prepare('DELETE FROM footer WHERE id = ?');
          $stmt->bind_param('i', $_POST['id']);
          $stmt->execute();
      }
  }
}

// Laden der Header-Daten
$stmt = $main_db_connection->prepare('SELECT * FROM footer');
$stmt->execute();
$footer_result = $stmt->get_result();

$headers = [];
while ($rec = $footer_result->fetch_assoc()) {
  $headers[$rec['id']] = $rec;
}

$selected_id = $_POST['id'] ?? key($headers) ?? 0;
$selected_footer = $headers[$selected_id] ?? ['id' => 0, 'headline' => '', 'link' => '', 'language' => '', 'label' => '', 'version' => '', 'css' => '', 'images' => '', 'role' => NULL, 'text' => ''];

// Formular zur Bearbeitung
echo '<form method="post" enctype="multipart/form-data">
  <div>
      <label for="footer_select">Footer auswählen:</label>
      <select name="id" id="footer_select" onchange="this.form.submit()">
          <option value="0">Neuer Footer</option>';
          
          foreach ($headers as $id => $header) {
              $selected = ($id == $selected_id) ? 'selected' : '';
              echo '<option value="' . $id . '" ' . $selected . '>' . htmlspecialchars($header['label'] ?? '', ENT_QUOTES, 'UTF-8') . '</option>';
          }
      
      echo '</select>
  </div>

  <input type="text" name="text" value="' . htmlspecialchars($selected_footer['text'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Text">
  <input type="text" name="link" value="' . htmlspecialchars($selected_footer['link'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Link">
  <input type="text" name="images" value="' . htmlspecialchars($selected_footer['images'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Bild URL">
  <input type="text" name="language" value="' . htmlspecialchars($selected_footer['language'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Sprache">
  <input type="text" name="version" value="' . htmlspecialchars($selected_footer['version'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Version">
  <input type="number" name="role" value="' . htmlspecialchars($selected_footer['role'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Rolle">
  
  <div>
      <button type="submit" name="action" value="save">Speichern</button>
      <button type="submit" name="action" value="delete">Löschen</button>
  </div>
</form>';

#$main_db_connection->close();
?>