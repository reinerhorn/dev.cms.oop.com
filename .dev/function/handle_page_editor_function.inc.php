<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
}
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$connection = getDbConnection();

// Sicherstellen, dass die Variablen auch dann gesetzt sind, wenn die Funktion später aufgerufen wird
$parent_id = '';
$idx = '';
$name = '';
$type = '';
$css = '';
$fk_translation_placeholder = '';
$meta_keywords = '';
$meta_description = '';
$enabled = '';
$print_all = '';
$admin_role = '';

function handlePageEditorRequest($connection) {
    global $parent_id, $idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $enabled, $print_all, $admin_role;

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id = $_POST['id'];
        if ($action == "store") {
            $action = $id == "" ? "add" : "update";
        }
        if ($action == "add" || $action == "update") {
            $parent_id = $_POST['parent_id'];
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
        }

        try {
            if ($action == "add") {
                $prepared_stmt = $connection->prepare(
                    "INSERT INTO page (parent_id ,idx, name, type, css, fk_translation_placeholder, meta_keywords, meta_description, print_all, enabled, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ? ,?)"
                );
                $prepared_stmt->bind_param("sissssssiii",$parent_id ,$idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $print_all, $enabled, $admin_role);
                $prepared_stmt->execute();
                $result = $connection->query("SELECT id FROM page ORDER BY id DESC LIMIT 1");
                if ($rec = $result->fetch_assoc()) {
                    $id = $rec['id'];
                }
            } elseif ($action == "update") {
                $prepared_stmt = $connection->prepare(
                    "UPDATE page SET parent_id=?, idx=?, name=?, type=?, css=?, fk_translation_placeholder=?, meta_keywords=?, meta_description=?, print_all=?, enabled=?, role=? WHERE id=?"
                );
                $prepared_stmt->bind_param("sissssssiisi", $parent_id ,$idx, $name, $type, $css, $fk_translation_placeholder, $meta_keywords, $meta_description, $print_all, $enabled,$admin_role, $id);
                $prepared_stmt->execute();
            } elseif ($action == "delete") {
                $prepared_stmt = $connection->prepare(
                    "DELETE FROM page WHERE id=?"
                );
                $prepared_stmt->bind_param("s", $id);
                $prepared_stmt->execute();
                $id = "";
                $action = "add";
            } elseif ($action === "edit" || $action === "page") {
                $prepared_stmt = $connection->prepare(
                    "SELECT * FROM page WHERE id=?"
                );
                $prepared_stmt->bind_param("s", $id);
                $prepared_stmt->execute();
                $result = $prepared_stmt->get_result();
                if ($rec = $result->fetch_assoc()) {
                    $parent_id = $rec['parent_id'];
                    $idx = $rec['idx'];
                    $type = $rec['type'];
                    $name = $rec['name'];
                    $css = $rec['css'];
                    $fk_translation_placeholder = $rec['fk_translation_placeholder'];
                    $meta_keywords = $rec['meta_keywords'];
                    $meta_description = $rec['meta_description'];
                    $print_all = $rec['print_all'];
                    $enabled = $rec['enabled'];
                    $admin_page = $rec['role'];
                }
            }
        } catch (Exception $e) {
            echo "<div style='color:red;'>Fehler: " . $e->getMessage() . "</div>";
        }
    }
}
