<?php
class HeaderUploadHandler {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function handle(): void {
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['ajax_upload']) &&
            isset($_FILES['image_file']) &&
            isset($_POST['header_id'])
        ) {
            $this->processUpload();
        } else {
            http_response_code(400);
            echo "Invalid request";
        }
    }

    private function processUpload(): void {
        $headerId = $_POST['header_id'];
        $altText = $_POST['alt_text'] ?? '';
        $linkUrl = $_POST['link_url'] ?? '';

        $targetDir = "/images/uploads/";
        $filename = basename($_FILES["image_file"]["name"]);
        $targetPath = $targetDir . $filename;
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $targetPath;

        if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $fullPath)) {
            $stmt = $this->db->prepare("INSERT INTO header_images (header_id, image_url, link_url, alt_text) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $headerId, $targetPath, $linkUrl, $altText);
            $stmt->execute();

            echo "success";
        } else {
            http_response_code(500);
            echo "Upload failed";
        }
    }
}
