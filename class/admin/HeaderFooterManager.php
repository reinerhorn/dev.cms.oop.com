

<?php

class HeaderFooterManager {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function save(string $type, array $data, ?array $file = null): void {
        $table = ($type === 'header') ? 'header' : 'footer';

        $headline = $data['headline'] ?? '';
        $link = $data['link'] ?? '';
        $language = $data['language'] ?? '';
        $label = $data['label'] ?? '';
        $version = $data['version'] ?? '';
        $css = $data['css'] ?? '';
        $role = $data['role'] ?? null;
        $images = $data['images'] ?? null;
        $id = $data['id'] ?? '';

        // Bild-Upload
        if ($file && isset($file['image']) && $file['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/";
            $image_name = time() . "_" . basename($file['image']['name']);
            $upload_file = $upload_dir . $image_name;

            if (move_uploaded_file($file['image']['tmp_name'], $upload_file)) {
                $images = "/uploads/" . $image_name;
            }
        }

        if (empty($id)) {
            $stmt = $this->db->prepare("
                INSERT INTO $table (headline, link, language, label, version, css, images, role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssssi', $headline, $link, $language, $label, $version, $css, $images, $role);
        } else {
            $stmt = $this->db->prepare("
                UPDATE $table SET headline=?, link=?, language=?, label=?, version=?, css=?, images=?, role=? WHERE id=?
            ");
            $stmt->bind_param('sssssssii', $headline, $link, $language, $label, $version, $css, $images, $role, $id);
        }

        $stmt->execute();
        $stmt->close();
    }

    public function delete(string $type, string $id): void {
        $table = ($type === 'header') ? 'header' : 'footer';

        $stmt = $this->db->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();
    }

    public function fetchAll(string $type): array {
        $table = ($type === 'header') ? 'header' : 'footer';
        $result = $this->db->query("SELECT * FROM $table ORDER BY UNIX_TIMESTAMP(id) DESC");

        $entries = [];
        while ($row = $result->fetch_assoc()) {
            $entries[$row['id']] = $row;
        }
        return $entries;
    }
}