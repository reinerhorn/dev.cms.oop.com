<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
class HeaderEditor {
    private mysqli $db;
    private array $headers = [];
    private array $selectedHeader = [];
    private int $selectedId = 0;

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->handlePost();
        $this->loadHeaders();
        $this->setSelectedHeader();
    }

    private function handlePost(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'save') {
                $fotoPath = $_POST['foto'] ?? '';

                if (!empty($_FILES['foto']['name'])) {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/";
                    $uploadFile = $uploadDir . basename($_FILES['foto']['name']);
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadFile)) {
                        $fotoPath = "/uploads/" . basename($_FILES['foto']['name']);
                    }
                }

                $stmt = $this->db->prepare('REPLACE INTO header (id, text, link, images, label, css, foto) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('issssss', $_POST['id'], $_POST['text'], $_POST['link'], $_POST['images'], $_POST['label'], $_POST['css'], $fotoPath);
                $stmt->execute();
            } elseif ($_POST['action'] === 'delete') {
                $stmt = $this->db->prepare('DELETE FROM header WHERE id = ?');
                $stmt->bind_param('i', $_POST['id']);
                $stmt->execute();
            }
        }
    }

    private function loadHeaders(): void {
        $stmt = $this->db->prepare('SELECT * FROM header');
        $stmt->execute();
        $result = $stmt->get_result();
        while ($rec = $result->fetch_assoc()) {
            $this->headers[$rec['id']] = $rec;
        }
    }

    private function setSelectedHeader(): void {
        if (isset($_POST['id'])) {
            $this->selectedId = (int)$_POST['id'];
        } elseif (key($this->headers) !== null) {
            $this->selectedId = (int)key($this->headers);
        } else {
            $this->selectedId = 0;
        }
        $this->selectedHeader = $this->headers[$this->selectedId] ?? [
            'id' => 0, 'text' => '', 'link' => '', 'images' => '', 'label' => '', 'css' => '', 'foto' => ''
        ];
    }

    public function renderForm(): void {
        echo '<form method="post" enctype="multipart/form-data">
            <div>
                <label for="header_select">Header auswählen:</label>
                <select name="id" id="header_select" onchange="this.form.submit()">
                    <option value="0">Neuer Header</option>';
                    foreach ($this->headers as $id => $header) {
                        $selected = ($id == $this->selectedId) ? 'selected' : '';
                        echo '<option value="' . $id . '" ' . $selected . '>' . htmlspecialchars($header['label'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
        echo '  </select>
            </div>

            <input type="text" name="text" value="' . htmlspecialchars($this->selectedHeader['text'], ENT_QUOTES, 'UTF-8') . '" placeholder="Text">
            <input type="text" name="link" value="' . htmlspecialchars($this->selectedHeader['link'], ENT_QUOTES, 'UTF-8') . '" placeholder="Link">
            <input type="text" name="images" value="' . htmlspecialchars($this->selectedHeader['images'], ENT_QUOTES, 'UTF-8') . '" placeholder="Bild URL">
            <input type="text" name="label" value="' . htmlspecialchars($this->selectedHeader['label'], ENT_QUOTES, 'UTF-8') . '" placeholder="Label">
            <input type="text" name="css" value="' . htmlspecialchars($this->selectedHeader['css'], ENT_QUOTES, 'UTF-8') . '" placeholder="CSS-Klasse">
            <input type="file" name="foto">
            <input type="hidden" name="existing_foto" value="' . htmlspecialchars($this->selectedHeader['foto'], ENT_QUOTES, 'UTF-8') . '">

            <div class="buttons">
                <button class="button-save" name="action" value="save">Speichern</button>
                <button class="button-delete" name="action" value="delete">Löschen</button>
            </div>
        </form>';
    }

    public function run(): void {
        $this->renderForm();
    }
}
 
    require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
 
$main_db_connection = getDbConnection();
if (!isset($main_db_connection)) {
    die('<p>Fehler: Datenbankverbindung nicht gesetzt.</p>');
}

$editor = new HeaderEditor($main_db_connection);
$editor->run();
?>