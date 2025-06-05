
<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/session.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/SelectGenerator.php';

class HeaderEditor {
    private mysqli $db;
    private array $headers = [];
    private array $selectedHeader = [];
    private string $selectedId = '';

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->handlePost();
        $this->loadHeaders();
        $this->setSelectedHeader();
    }

    private function handlePost(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'save') {
                $stmt = $this->db->prepare('REPLACE INTO header (id, headline, link, images, label, css, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isssssi', $_POST['id'], $_POST['text'], $_POST['link'], $_POST['images'], $_POST['label'], $_POST['css'], $_POST['role']);
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
        $idFromPost = $_POST['id'] ?? null;

        // Wenn id leer ist oder "0", bedeutet das "Neuer Header"
        if (empty($idFromPost) || $idFromPost === "0") {
            $this->selectedId = '';
            $this->selectedHeader = [
                'id' => 0,
                'headline' => '',
                'link' => '',
                'images' => '',
                'label' => '',
                'css' => '',
                'role' => 0
            ];
        } else {
            $this->selectedId = $idFromPost;
            $this->selectedHeader = $this->headers[$this->selectedId] ?? [
                'id' => 0,
                'headline' => '',
                'link' => '',
                'images' => '',
                'label' => '',
                'css' => '',
                'role' => 0
            ];
        }
    }

    public function renderForm(): void {
        echo '<form method="post">
            <input type="hidden" name="action" value="select">
            <input type="hidden" name="type" value="header">
            <input type="hidden" name="id" value="' . htmlspecialchars($this->selectedId, ENT_QUOTES, 'UTF-8') . '">
            <div>
                <label for="header_select">Header auswählen:</label>';
        echo SelectGenerator::render(
            'id',
            array_values($this->headers),
            $this->selectedId,
            'Neuer Header',
            'id',
            'label'
        );
        echo '
            </div>
        </form>';

        if ($this->selectedId === '') {
            $this->selectedHeader = [
                'id' => 0,
                'headline' => '',
                'link' => '',
                'images' => '',
                'label' => '',
                'css' => '',
                'role' => 0
            ];
        }

        echo '<form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="' . htmlspecialchars($this->selectedId, ENT_QUOTES, 'UTF-8') . '">
            <input type="text" name="text" value="' . htmlspecialchars($this->selectedHeader['headline'], ENT_QUOTES, 'UTF-8') . '" placeholder="headline">
            <input type="text" name="link" value="' . htmlspecialchars($this->selectedHeader['link'], ENT_QUOTES, 'UTF-8') . '" placeholder="Link">
            <input type="text" name="images" value="' . htmlspecialchars($this->selectedHeader['images'], ENT_QUOTES, 'UTF-8') . '" placeholder="Bild URL">
            <input type="text" name="label" value="' . htmlspecialchars($this->selectedHeader['label'], ENT_QUOTES, 'UTF-8') . '" placeholder="Label">
            <input type="text" name="css" value="' . htmlspecialchars($this->selectedHeader['css'], ENT_QUOTES, 'UTF-8') . '" placeholder="CSS-Klasse">
            <input type="number" name="role" value="' . htmlspecialchars($this->selectedHeader['role'], ENT_QUOTES, 'UTF-8') . '" placeholder="Rolle">
            <div class="buttons">
                <button class="button-save" name="action" value="save">Speichern</button>
                <button class="button-delete" name="action" value="delete">Löschen</button>
            </div>
        </form>';
    }
}

class HeaderImageManager {
    private $connection;

    public function __construct($db) {
        $this->connection = $db;
    }

    public function displayHeadersWithImages() {
        $headers = $this->connection->query("SELECT * FROM header ORDER BY id DESC");
        while ($header = $headers->fetch_assoc()) {
            echo "<h3>Header: {$header['label']} (Rolle: {$header['role']})</h3>";
            $this->displayImagesForHeader($header['id']);
            $this->renderUploadForm($header['id']);
        }
    }

    private function displayImagesForHeader($headerId) {
        $stmt = $this->connection->prepare("SELECT * FROM header_images WHERE header_id = ?");
        $stmt->bind_param("s", $headerId);
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
    }

    private function renderUploadForm($headerId) {
        echo '
       <form method="post" action="" enctype="multipart/form-data" class="dropzone-form image-upload-box" id="dropzone-form-' . $headerId . '">
            <input type="hidden" name="header_id" value="' . $headerId . '" id="header_id_' . $headerId . '">
            <input type="text" name="alt_text" placeholder="Alt-Text" class="alt-text" id="alt_text_' . $headerId . '"><br>
            <input type="text" name="link_url" placeholder="Link URL" class="link-url" id="link_url_' . $headerId . '"><br>
            <div class="dropzone" data-header-id="' . $headerId . '">
                Hier Bild per Drag & Drop ablegen oder klicken
                <input type="file" name="image_file" accept="image/*" style="display:none;" class="image-file" id="fileinput-' . $headerId . '">
            </div>
        </form>';
    }

    public function handleImageUpload() {
        if (isset($_POST['upload_image']) && isset($_FILES['image_file'])) {
            $target_dir = "/images/uploads/";
            $target_path = $target_dir . basename($_FILES["image_file"]["name"]);
            $full_path = $_SERVER['DOCUMENT_ROOT'] . $target_path;

            if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $full_path)) {
                $stmt = $this->connection->prepare("INSERT INTO header_images (header_id, image_url, link_url, alt_text) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $_POST['header_id'], $target_path, $_POST['link_url'], $_POST['alt_text']);
                $stmt->execute();
                header("Location: " . $_SERVER['REQUEST_URI']);
            } else {
                echo "Fehler beim Hochladen.";
            }
        }
    }

    public function handleImageDelete() {
        if (isset($_POST['delete_image_id'])) {
            $stmt = $this->connection->prepare("DELETE FROM header_images WHERE id = ?");
            $stmt->bind_param("s", $_POST['delete_image_id']);
            $stmt->execute();
            header("Location: " . $_SERVER['REQUEST_URI']);
        }
    }
}

$connection = getDbConnection();
echo '<div class="admin_container">';
echo '<div class="admin_box">';
$headerEditor = new HeaderEditor($connection);
$headerEditor->renderForm();

$headerImageManager = new HeaderImageManager($connection);
$headerImageManager->handleImageUpload();
$headerImageManager->handleImageDelete();
$headerImageManager->displayHeadersWithImages();
echo '</div>';
echo '</div>';
?>
<script>
document.querySelectorAll('.dropzone').forEach(dropzone => {
    dropzone.addEventListener('dragover', e => {
        e.preventDefault();
        dropzone.style.background = '#e0f7fa';
    });

    dropzone.addEventListener('dragleave', e => {
        dropzone.style.background = '#f9f9f9';
    });

    dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.style.background = '#f9f9f9';

        const headerId = dropzone.dataset.headerId;
        const container = dropzone.closest('.image-upload-box');
        const altText = container.querySelector('.alt-text').value;
        const linkUrl = container.querySelector('.link-url').value;
        const file = e.dataTransfer.files[0];

        if (!file) return;

        const formData = new FormData();
        formData.append('header_id', headerId);
        formData.append('alt_text', altText);
        formData.append('link_url', linkUrl);
        formData.append('image_file', file);
        formData.append('ajax_upload', '1');

        fetch('/ajax/ajax_header_upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            alert('✅ Bild erfolgreich hochgeladen!');
            location.reload();
        })
        .catch(error => {
            alert('❌ Fehler beim Upload');
            console.error(error);
        });
    });
});
</script>
