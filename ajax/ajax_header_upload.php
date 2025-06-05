<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';

class HeaderUploadHandler {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function handle(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_upload'])) {
            $headerId = $_POST['header_id'] ?? '';
            $altText = $_POST['alt_text'] ?? '';
            $linkUrl = $_POST['link_url'] ?? '';

            if (!empty($_FILES['image_file']['name'])) {
                $uploadDir = '/images/uploads/';
                $uploadPath = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;

                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }

                $fileName = time() . '_' . basename($_FILES['image_file']['name']);
                $fullPath = $uploadPath . $fileName;
                $relativePath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $fullPath)) {
                    $stmt = $this->db->prepare("INSERT INTO header_images (header_id, image_url, link_url, alt_text) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $headerId, $relativePath, $linkUrl, $altText);
                    $stmt->execute();

                    http_response_code(200);
                    echo "Bild erfolgreich gespeichert";
                } else {
                    http_response_code(500);
                    echo "Fehler beim Speichern des Bildes.";
                }
            } else {
                http_response_code(400);
                echo "Kein Bild empfangen.";
            }
        }
    }
}

$uploadHandler = new HeaderUploadHandler(getDbConnection());
$uploadHandler->handle();