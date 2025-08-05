<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
}
include_once $_SERVER['DOCUMENT_ROOT'] . "/class/admin/admin_editor_handler.inc.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/class/helper/SelectGenerator.php";
$connection = getDbConnection();

$handler = new AdminEditorHandler($connection);
error_log("🔧 POST=" . print_r($_POST, true));
$handler->handle();
extract($handler->exportVariables());
if (!isset($_POST['form_name'])) {
    $_POST['form_name'] = '';
}

// Plaintext edit fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['record_selection']) || isset($_POST['id'])) && !isset($_POST['action'])) {
    $_POST['action'] = 'edit';
}
?>
