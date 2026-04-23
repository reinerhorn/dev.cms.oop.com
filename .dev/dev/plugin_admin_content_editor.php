<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
    exit;
}

include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/function/handle_plaintext_editor.php";
$connection = getDbConnection();
handlePlaintextContentEditor($connection);
?>

<div class="admin_box"> 
<form class="" name="editor" action="" method="post">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action ?? '') ?>">
        <input type="hidden" name="id" value="<?php echo $id ?>">
   <select name="id" onchange="this.form.querySelector('[name=action]').value='edit'; this.form.submit();">
       <option value="">auswählen...</option>
       <option value="neu">neu</option>
       <option value="-" disabled>────────────</option>
       <?php
       $stmt = $connection->prepare("SELECT * FROM p_content_plaintext");
       $stmt->execute();
       $result = $stmt->get_result();
       while($rec = $result->fetch_assoc()) {
           $selected = ($rec['id'] == $id) ? 'selected' : '';
           echo "<option value=\"{$rec['id']}\" $selected>" . htmlspecialchars($rec['label']) . "</option>\n";
       }
       ?>
   </select>

    <label class="text" type="text" for="headline">Title</label>    
    <input type="text" id="headline" name="headline" class="input_color" value="<?php echo $headline?>" required>
    <label for="image_path">Image Path</label>  
    <input type="text" name="image_path" class="input_color" value="<?php echo $image_path?>" required><br>
    <label for="link"><b>Link</b></label>
    <input type="text" name="link" class="input_color" value="<?php echo $link?>" required><br>
    <label for="image_description"><b>Image Description</b></label>
    <input type="text" name="image_description" class="input_color" value="<?php echo $image_description?>" required><br>
    <label for="label"><b>Label</b></label>
    <input type="text" name="idx" class="input_color" value="<?php echo $idx?>" required>

    <textarea name="text" id="text"><?php echo $text?></textarea><br><br>

    <div class="buttons">
        <button type="submit" name="action" value="save">Speichern</button>
        <button type="submit" name="action" value="delete">Löschen</button>
    </div>
</form>
</div>