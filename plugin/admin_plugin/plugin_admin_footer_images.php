 

<div class="drop-zone" id="dropZone">
    Datei hierher ziehen oder klicken, um hochzuladen
    <form id="uploadForm" method="post" enctype="multipart/form-data" style="display: none;">
        <input type="file" name="footer_image_file" id="fileInput" accept="image/*">
        <input type="hidden" name="footer_id" value="FOOTER_ID_PLACEHOLDER">
        <input type="hidden" name="alt_text" value="Auto Alt Text">
        <input type="hidden" name="link_url" value="#">
        <button type="submit" name="upload_footer_image">Hochladen</button>
    </form>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadForm = document.getElementById('uploadForm');

dropZone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', () => uploadForm.submit());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        uploadForm.submit();
    }
});
</script>
<?php
// Bild in footer_images speichern
if (isset($_POST['upload_footer_image']) && isset($_FILES['footer_image_file'])) {
    $target_dir = "/images/uploads/";
    $target_path = $target_dir . basename($_FILES["footer_image_file"]["name"]);
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $target_path;

    if (move_uploaded_file($_FILES["footer_image_file"]["tmp_name"], $full_path)) {
        $stmt = $connection->prepare("INSERT INTO footer_images (footer_id, image_url, link_url, alt_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $_POST['footer_id'], $target_path, $_POST['link_url'], $_POST['alt_text']);
        $stmt->execute();
        header("Location: " . $_SERVER['REQUEST_URI']);
    } else {
        echo "Fehler beim Hochladen.";
    }
}

// footer_images Eintrag löschen
if (isset($_POST['delete_footer_image_id'])) {
    $stmt = $connection->prepare("DELETE FROM footer_images WHERE id = ?");
    $stmt->bind_param("s", $_POST['delete_footer_image_id']);
    $stmt->execute();
    header("Location: " . $_SERVER['REQUEST_URI']);
}
?>