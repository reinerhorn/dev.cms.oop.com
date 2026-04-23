<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
    exit;
}
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

function handleAdminEditorRequests(mysqli $connection): void {
    global $id, $headline, $text, $image_path, $link, $image_description, $idx, $label, $action;
    global $parent_id, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $enabled, $print_all, $admin_role;

    $fields = [
        'id', 'headline', 'text', 'image_path', 'link', 'image_description', 'idx', 'label',
        'parent_id', 'name', 'type', 'css', 'fk_translation_placeholder', 'meta_keywords',
        'meta_description', 'enabled', 'print_all', 'admin_role'
    ];
    foreach ($fields as $field) $$field = '';

    if (!isset($_POST['action'])) return;
    $action = $_POST['action'];
    $id = $_POST['id'] ?? '';

    if ($action === "store") $action = empty($id) ? "add" : "update";
    if (isset($_POST['record_selection']) && $_POST['record_selection'] === 'neu') {
        $_POST = [];
        $action = 'add';
        return;
    }

    try {
        // ---------- PLAINTEXT ----------
        if (isset($_POST['headline'])) {
            $headline = $_POST['headline'];
            $text = $_POST['text'];
            $link = $_POST['link'];
            $image_path = $_POST['image_path'];
            $image_description = $_POST['image_description'];
            $idx = $_POST['idx'];
            $label = $_POST['label'] ?? '';

            if ($action === "add") {
                $stmt = $connection->prepare("INSERT INTO p_content_plaintext (headline, text, image_path, link, image_description, idx, label) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $headline, $text, $image_path, $link, $image_description, $idx, $label);
                $stmt->execute();
                $id = $connection->insert_id;
            } elseif ($action === "update") {
                $stmt = $connection->prepare("UPDATE p_content_plaintext SET headline=?, text=?, image_path=?, link=?, image_description=?, idx=?, label=? WHERE id=?");
                $stmt->bind_param("sssssis", $headline, $text, $image_path, $link, $image_description, $idx, $label, $id);
                $stmt->execute();
            } elseif ($action === "delete") {
                $stmt = $connection->prepare("DELETE FROM p_content_plaintext WHERE id=?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
            } elseif ($action === "edit") {
                $stmt = $connection->prepare("SELECT * FROM p_content_plaintext WHERE id=?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $rec = $stmt->get_result()->fetch_assoc();
                if ($rec) {
                    foreach (['headline', 'text', 'image_path', 'link', 'image_description', 'idx', 'label'] as $key) {
                        $$key = $rec[$key];
                    }
                }
            }
        }

        // ---------- PAGE ----------
        if (isset($_POST['name'])) {
            $parent_id = $_POST['parent_id'] !== '' ? $_POST['parent_id'] : null;
            $idx = $_POST['idx'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            $css = $_POST['css'];
            $fk_translation_placeholder = $_POST['fk_translation_placeholder'];
            $meta_keywords = $_POST['meta_keywords'];
            $meta_description = $_POST['meta_description'];
            $print_all = $_POST['print_all'];
            $enabled = $_POST['enabled'];
            $admin_role = $_POST['role'];

            if ($action === "add") {
                $sql = "INSERT INTO page (parent_id, idx, name, type, css, fk_translation_placeholder, meta_keywords, meta_description, print_all, enabled, role)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("sissssssiii", $parent_id, $idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $print_all, $enabled, $admin_role);
                $stmt->execute();
            } elseif ($action === "update") {
                $sql = "UPDATE page SET parent_id=?, idx=?, name=?, type=?, css=?, fk_translation_placeholder=?, meta_keywords=?, meta_description=?, print_all=?, enabled=?, role=?
                        WHERE id=?";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("ssssssssiiis", $parent_id, $idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $print_all, $enabled, $admin_role, $id);
                $stmt->execute();
            } elseif ($action === "edit" || $action === "page") {
                $stmt = $connection->prepare("SELECT * FROM page WHERE id=?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $rec = $stmt->get_result()->fetch_assoc();
                if ($rec) {
                    foreach (['parent_id', 'idx', 'name', 'type', 'css', 'fk_translation_placeholder', 'meta_keywords', 'meta_description', 'print_all', 'enabled', 'role'] as $key) {
                        $$key = $rec[$key];
                    }
                }
            }
        }

        // ---------- PAGE CONFIG ----------
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'load_config') {
                $stmt = $connection->prepare("SELECT * FROM page_config WHERE id=? LIMIT 1");
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
                $stmt = $connection->prepare("SELECT table_name FROM plugin WHERE id=?");
                $stmt->bind_param("s", $_POST['plugin_id']);
                $stmt->execute();
                $plugin = $stmt->get_result()->fetch_assoc();
                if ($plugin) {
                    $table_name = $plugin['table_name'];
                    $content = $connection->query("SELECT label FROM $table_name WHERE id='" . $connection->real_escape_string($_POST['plugin_content_id']) . "'")->fetch_assoc();
                    $content_label = $content['label'] ?? '';
                    if ($is_update) {
                        $stmt = $connection->prepare("UPDATE page_config SET fk_page_id=?, fk_plugin_id=?, plugin_content_id=?, content_label=?, idx=? WHERE id=?");
                        $stmt->bind_param("sssssi", $_POST['page_id'], $_POST['plugin_id'], $_POST['plugin_content_id'], $content_label, $_POST['idx'], $_POST['page_config_id']);
                    } else {
                        $stmt = $connection->prepare("INSERT INTO page_config (fk_page_id, fk_plugin_id, plugin_content_id, content_label, idx) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssi", $_POST['page_id'], $_POST['plugin_id'], $_POST['plugin_content_id'], $content_label, $_POST['idx']);
                    }
                    $stmt->execute();
                }
            } elseif ($_POST['action'] === 'delete' && isset($_POST['page_config_id'])) {
                $stmt = $connection->prepare("DELETE FROM page_config WHERE id=?");
                $stmt->bind_param("s", $_POST['page_config_id']);
                $stmt->execute();
            }
        }
    } catch (Throwable $e) {
        echo "<div style='color:red;'>Fehler: " . $e->getMessage() . "</div>";
    }
}
?>