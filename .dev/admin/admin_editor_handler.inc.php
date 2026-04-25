<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
    exit;
}

include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

class AdminEditorHandler {
    private mysqli $connection;
    private string $action;
    private ?string $id;

    public function __construct(mysqli $connection) {
        $this->connection = $connection;
        $this->action = $_POST['action'] ?? '';
        $this->id = $_POST['id'] ?? null;

        if ($this->action === "store" && !isset($_POST['record_selection'])) {
            $this->action = empty($this->id) ? "add" : "update";
        }

        if (isset($_POST['record_selection']) && $_POST['record_selection'] === 'neu') {
            $this->action = 'add';
            $_POST['id'] = '';
        }
    }

    public function handle(): void {
        try {
            $this->handlePlaintext();
            $this->handlePage();
            $this->handlePageConfig();
        } catch (Throwable $e) {
            echo "<div style='color:red;'>Fehler: " . $e->getMessage() . "</div>";
        }
    }

    private function handlePlaintext(): void {
        if (!isset($_POST['form_name']) || $_POST['form_name'] !== 'editor_plaintext') return;

        $stmt = null;
        $headline = $_POST['headline'];
        $text = $_POST['text'];
        $link = $_POST['link'];
        $image_path = $_POST['image_path'];
        $image_description = $_POST['image_description'];
        $idx = is_numeric($_POST['idx']) ? (int)$_POST['idx'] : 0;
        $label = $_POST['label'] ?? '';
        $fk_language_id = $_POST['fk_language_id'] ?? 'de';

        if ($this->action === "add") {
            $fk_language_id = $_POST['fk_language_id'] ?? 'de';
            $stmt = $this->connection->prepare("INSERT INTO p_content_plaintext (headline, text, image_path, link, image_description, idx, label, fk_language_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $headline, $text, $image_path, $link, $image_description, $idx, $label, $fk_language_id);
            $stmt->execute();
            $this->id = $this->connection->insert_id;
        } elseif ($this->action === "update") {
            $stmt = $this->connection->prepare("UPDATE p_content_plaintext SET headline=?, text=?, image_path=?, link=?, image_description=?, idx=?, label=? WHERE id=?");
            $stmt->bind_param("sssssiis", $headline, $text, $image_path, $link, $image_description, $idx, $label, $this->id);
            $stmt->execute();
        } elseif ($this->action === "delete" && isset($_POST['confirm_delete'])) {
            $stmt = $this->connection->prepare("DELETE FROM p_content_plaintext WHERE id=?");
            $stmt->bind_param("s", $this->id);
            $stmt->execute();
        } elseif ($this->action === "edit" && isset($_POST['id']) && $_POST['id'] !== 'neu' && ($_POST['form_name'] ?? '') === 'editor_plaintext') {
            $id = $_POST['id'];
            $stmt = $this->connection->prepare("SELECT * FROM p_content_plaintext WHERE id=?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $rec = $stmt->get_result()->fetch_assoc();
            if ($rec) {
                foreach ($rec as $key => $value) {
                    $_POST['plaintext_' . $key] = $value;
                }
                $_POST['action'] = 'edit';
            }
        }
    }

    private function handlePage(): void {
        if (($_POST['form_name'] ?? '') !== 'editor_page') return;
        if (!isset($_POST['name']) || ($_POST['form_name'] ?? '') !== 'editor_page') return;

        $stmt = null;
        $parent_id = $_POST['parent_id'] !== '' ? $_POST['parent_id'] : null;
        $idx = is_numeric($_POST['idx']) ? (int)$_POST['idx'] : 0;
        $name = $_POST['name'];
        $type = $_POST['type'];
        $css = $_POST['css'];
        $fk_translation_placeholder = $_POST['fk_translation_placeholder'];
        $meta_keywords = $_POST['meta_keywords'];
        $meta_description = $_POST['meta_description'];
        $print_all = is_numeric($_POST['print_all']) ? (int)$_POST['print_all'] : 0;
        $enabled = is_numeric($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        $admin_role = is_numeric($_POST['role']) ? (int)$_POST['role'] : null;

        if ($this->action === "edit" && (!isset($_POST['record_selection']) || $_POST['record_selection'] !== 'neu')) {
            $id = $this->id;
            $stmt = $this->connection->prepare("SELECT * FROM page WHERE id=?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $rec = $stmt->get_result()->fetch_assoc();
            if ($rec) {
                foreach ($rec as $key => $value) {
                    $_POST[$key] = $value;
                }
                $_POST['action'] = 'edit';
            }
        } elseif ($this->action === "add") {
            $stmt = $this->connection->prepare("INSERT INTO page (parent_id, idx, name, type, css, fk_translation_placeholder, meta_keywords, meta_description, print_all, enabled, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssssiii", $parent_id, $idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $print_all, $enabled, $admin_role);
            $stmt->execute();
        } elseif ($this->action === "update") {
            $stmt = $this->connection->prepare("UPDATE page SET parent_id=?, idx=?, name=?, type=?, css=?, fk_translation_placeholder=?, meta_keywords=?, meta_description=?, print_all=?, enabled=?, role=? WHERE id=?");
            $stmt->bind_param("sissssssiiis", $parent_id, $idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $print_all, $enabled, $admin_role, $this->id);
            $stmt->execute();
        }
    }

    private function handlePageConfig(): void {
        if (!isset($_POST['action'])) return;

        if ($_POST['action'] === 'load_config') {
            $stmt = $this->connection->prepare("SELECT * FROM page_config WHERE id=? LIMIT 1");
            $stmt->bind_param("s", $_POST['page_config_id']);
            $stmt->execute();
            $config = $stmt->get_result()->fetch_assoc();
            if ($config) {
                $_POST['page_id'] = $config['fk_page_id'];
                $_POST['plugin_id'] = $config['fk_plugin_id'];
                $_POST['plugin_content_id'] = $config['plugin_content_id'];
                $_POST['idx'] = $config['idx'];
            }
        } elseif ($_POST['action'] === 'store') {
            $is_update = isset($_POST['page_config_id']) && $_POST['page_config_id'] !== '';
            $stmt = $this->connection->prepare("SELECT table_name FROM plugin WHERE id=?");
            $stmt->bind_param("s", $_POST['plugin_id']);
            $stmt->execute();
            $plugin = $stmt->get_result()->fetch_assoc();
            if ($plugin) {
                $table_name = $plugin['table_name'];
                $stmtContent = $this->connection->prepare("SELECT label FROM $table_name WHERE id = ?");
                $stmtContent->bind_param("s", $_POST['plugin_content_id']);
                $stmtContent->execute();
                $content = $stmtContent->get_result()->fetch_assoc();
                $content_label = $content['label'] ?? '';
                if ($is_update) {
                    $stmt = $this->connection->prepare("UPDATE page_config SET fk_page_id=?, fk_plugin_id=?, plugin_content_id=?, content_label=?, idx=? WHERE id=?");
                    $stmt->bind_param("sssssi", $_POST['page_id'], $_POST['plugin_id'], $_POST['plugin_content_id'], $content_label, $_POST['idx'], $_POST['page_config_id']);
                } else {
                    $stmt = $this->connection->prepare("INSERT INTO page_config (fk_page_id, fk_plugin_id, plugin_content_id, content_label, idx) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $_POST['page_id'], $_POST['plugin_id'], $_POST['plugin_content_id'], $content_label, $_POST['idx']);
                }
                $stmt->execute();
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['page_config_id'])) {
            $stmt = $this->connection->prepare("DELETE FROM page_config WHERE id=?");
            $stmt->bind_param("s", $_POST['page_config_id']);
            $stmt->execute();
        }
    }

    public function exportVariables(): array {
        return [
            'name' => $_POST['name'] ?? '',
            'css' => $_POST['css'] ?? '',
            'type' => $_POST['type'] ?? '',
            'parent_id' => $_POST['parent_id'] ?? '',
            'fk_translation_placeholder' => $_POST['fk_translation_placeholder'] ?? '',
            'meta_keywords' => $_POST['meta_keywords'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'idx' => $_POST['idx'] ?? '',
            'enabled' => $_POST['enabled'] ?? '',
            'print_all' => $_POST['print_all'] ?? '',
            'admin_role' => $_POST['role'] ?? '',
            'headline' => $_POST['plaintext_headline'] ?? '',
            'text' => $_POST['plaintext_text'] ?? '',
            'link' => $_POST['plaintext_link'] ?? '',
            'image_path' => $_POST['plaintext_image_path'] ?? '',
            'image_description' => $_POST['plaintext_image_description'] ?? '',
            'label' => $_POST['plaintext_label'] ?? '',
            'fk_language_id' => $_POST['fk_language_id'] ?? 'de',
            'id' => $_POST['id'] ?? '',
            'action' => $_POST['action'] ?? '',
        ];
    }
}
