<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
    exit;
}

include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$connection = getDbConnection();

function handlePlaintextContentEditor($connection) {
    global $id, $headline, $text, $image_path, $link, $image_description, $idx, $label, $action;

    // Variablen initialisieren
    $id = "";
    $headline = "";
    $text = "";
    $image_path = "";
    $link = "";
    $image_description = "";
    $idx = "";
    $label = "";
    $action = "";

    if (isset($_POST['action'])) {
        try {
            $action = $_POST['action'];
            $id = $_POST['id'];
            if ($action == "store") {
                $action = $id == "" ? "add" : "update";
            }

            if ($action == "add" || $action == "update") {
                $headline = $_POST['headline'];
                $text = $_POST['text'];
                $link = $_POST['link'];
                $image_path = $_POST['image_path'];
                $image_description = $_POST['image_description'];
                $idx = $_POST['idx'];
                $label = $_POST['label'];
            }

            if ($action == "add") {
                $prepared_stmt = $connection->prepare(
                    "INSERT INTO p_content_plaintext (headline, text, image_path, link, image_description, idx, label) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $prepared_stmt->bind_param("sssssis", $headline, $text, $image_path, $link, $image_description, $idx, $label);
                $prepared_stmt->execute();
                $result = $connection->query("SELECT id FROM p_content_plaintext ORDER BY id DESC LIMIT 1");
                if ($rec = $result->fetch_assoc()) {
                    $id = $rec['id'];
                }
            } elseif ($action == "update") {
                $prepared_stmt = $connection->prepare(
                    "UPDATE p_content_plaintext SET headline=?, text=?, image_path=?, link=?, image_description=?, idx=?, label=? WHERE id=?"
                );
                $prepared_stmt->bind_param("sssssiss", $headline, $text, $image_path, $link, $image_description, $idx, $label, $id);
                $prepared_stmt->execute();
            } elseif ($action == "delete") {
                $prepared_stmt = $connection->prepare(
                    "DELETE FROM p_content_plaintext WHERE id=?"
                );
                $prepared_stmt->bind_param("s", $id);
                $prepared_stmt->execute();
                $id = "";
                $action = "add";
            } elseif ($action == "edit") {
                $prepared_stmt = $connection->prepare(
                    "SELECT * FROM p_content_plaintext WHERE id=?"
                );
                $prepared_stmt->bind_param("s", $id);
                $prepared_stmt->execute();
                $result = $prepared_stmt->get_result();
                if ($rec = $result->fetch_assoc()) {
                    $headline = $rec['headline'];
                    $text = $rec['text'];
                    $image_path = $rec['image_path'];
                    $link = $rec['link'];
                    $image_description = $rec['image_description'];
                    $idx = $rec['idx'];
                    $label = $rec['label'];
                }
            }
        } catch (mysqli_sql_exception $e) {
            echo "<div style='color:red;'>Datenbankfehler: " . $e->getMessage() . "</div>";
        }
    }
}
?>
