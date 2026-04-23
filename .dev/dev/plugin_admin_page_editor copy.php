<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
}
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
 
$connection = getDbConnection();
include_once $_SERVER['DOCUMENT_ROOT'] . "/function/admin_editor_handler.inc.php";
handleAdminEditorRequests(getDbConnection());
?>
<div class="flex_container">
<form name="editor" action="" method="post">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" value="<?php echo isset($_POST['id']) ? $_POST['id'] : 'neu' ?>">
        <select name="record_selection" onchange="selectRecord()">
        <option value="">auswählen...</option>
        <option value="neu" <?= ($_POST['record_selection'] ?? '') === 'neu' ? 'selected' : '' ?>>neu</option>
        <option value="-" disabled=disabled></option>
        <?php
           $connection = getDbConnection();
           $stmt = $connection->prepare("SELECT * FROM page ");
           $stmt->execute();
           $result = $stmt->get_result();
           while($page = $result->fetch_assoc()) {
               echo '<option value="' . $page['id'] . '">' . $page['name']  .'</option>' . PHP_EOL; 
           }
        ?>
        </select>
<div class="admin_box"> 
    <label type="text" for="name"> Name</label>
    <input type="text" id="name" name="name" class="input_color" value="<?php echo $name?>" required>
    <label type="text" for="css">CSS</label>
    <input type="text" id="type" name="type" class="input_color" value="<?php echo $css?>">

    <label type="text" for="type">Type</label>
    <input type="text" id="css" name="css" class="input_color" value="<?php echo $type?>">
    <label type="text" for="parent_id">Parent ID</label>
    <input type="text" id="parent_id" name="parent_id" class="input_color" value="<?php echo $parent_id?>">

    <label type="text" for="fk_translation_placeholder">FK Translation Placeholder</label>
    <input type="text" id="fk_translation_placeholder" name="fk_translation_placeholder" class="input_color" value="<?php echo $fk_translation_placeholder?>" >

    <label type="text" for="meta_keywords">Meta Keywords</label>
    <input type="text" id="meta_keywords" name="meta_keywords" class="input_color" value="<?php echo $meta_keywords?>" >
    <label type="text" for="meta_description">Meta Description</label>
    <input type="text" id="meta_description" name="meta_description" class="input_color" value="<?php echo $meta_description?>">
    <label type="text" for="idx">Index</label>
    <input type="text" id="idx" name="idx" class="input_color" value="<?php echo $idx?>">
    <label type="text" for="enabled">Enabled</label>
    <input type="text" id="enabled" name="enabled" class="input_color" value="<?php echo $enabled?>">
    <label type="text" for="print_all">Print All</label>
    <input type="text" id="print_all" name="print_all" class="input_color" value="<?php echo $print_all?>">
    <label type="text" for="admin_page">Admin</label>
    <input type="text" id="admin_page" name="role" class="input_color" value="<?php echo $admin_role?>">
 
    <div class="buttons">
        <button type="submit" name="action" value="save">Speichern</button>
        <button type="submit" name="action" value="delete">Löschen</button>
    </div>





<script>
  function selectRecord() {
      const select = document.querySelector('select[name="record_selection"]');
      const form = document.forms['editor'];
      form.id.value = select.value;
      form.submit();
  }
</script>
</form>
</div>