<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*if (!isset($_SESSION['admin_a'])) {
	header('Location:/index.php');
}*/
$connection = getDbConnection();
    $id=""; 
    $fk_formular_id ="";
    $type="";
    $label="";
    $column="";
    $row="";
    $label_enabled="";
    $folder="";

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id = $_POST['id'];
        if ($action == "store") {
            $action = $id == "" ? "add" : "update";
        }
        if ($action == "add" || $action == "update") {
            $fk_formular_id = $_POST['fk_formular_id'];
            $type = $_POST['type'];
            $column = $_POST['column'];
            $row = $_POST['row'];
            $label_enabled= $_POST['label_enabled'];
            $folder = $_POST['folder'];
        }  
        if ($action == "add") {
            $prepared_stmt = $connection->prepare(
            "INSERT INTO p_content_formular_field (fk_formular_id, type,label, column ,row, label_enabled, folder) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $prepared_stmt->bind_param("sssssis", $fk_formular_id, $type, $label, $column ,$row, $label_enabled, $folder );
            $prepared_stmt->execute();
            $result = $connection->query("SELECT id FROM p_content_formular_field ORDER BY id DESC LIMIT 1");
            if ($rec = $result->fetch_assoc()) {
                $id = $rec['id'];
            }
        } elseif ($action == "update") {
            $prepared_stmt = $connection->prepare(
                "UPDATE p_content_formular_field SET fk_formular_id=?,type=?, label=? column=? ,row=?, label_enabled=?, folder=? WHERE id=?"
            );
            $prepared_stmt->bind_param("sssssiss", $fk_formular_id, $type,$label, $column ,$row, $label_enabled, $folder, $id);
            $prepared_stmt->execute();
        } elseif ($action == "delete") {
            $prepared_stmt = $connection->prepare(
                "DELETE FROM p_content_formular_field WHERE id=?"
            );
            $prepared_stmt->bind_param("s", $id);
            $prepared_stmt->execute();
            $id = "";
            $action = "add";
        } elseif ($action == "edit") {
            $prepared_stmt = $connection->prepare(
                "SELECT * FROM p_content_formular_field WHERE id=?"
            );
            $prepared_stmt->bind_param("s", $id);
            $prepared_stmt->execute();
            $result = $prepared_stmt->get_result();
            if ($formular_rec = $result->fetch_assoc()) {
                $fk_formular_id = $formular_rec['fk_formular_id'];
                $type = $formular_rec['type'];
                $column = $formular_rec['column'];
                $row = $formular_rec['row'];
                $label_enabled= $formular_rec['label_enabled'];
                $folder = $formular_rec['folder'];
            }
        }
    } 
?>
<div class="flex_container">
    <form  name="editor" action="" method="post">
        <input type="hidden" name="action" value="page">
        <input type="hidden" name="id" value="<?php echo isset($_POST['id']) ? $_POST['id'] : 'neu' ?>">
        <select name="record_selection" onchange="selectRecord()">
           <option value="">auswählen...</option>
           <option value="neu">neu</option>
           <option value="-" disabled=disabled></option>
           <?php
               $stmt = $connection->prepare("SELECT * FROM p_content_formular_field");
               $stmt->execute();
               $result = $stmt->get_result();
               while($page = $result->fetch_assoc()) {
                   echo '<option value="' . $page['id'] . '">' . $page['label'] .' '.$page['type'] .'</option>' . PHP_EOL; 
               }
           ?>
 </select>
<br><br>
    <div class="group">
    <input type="text" id="fk_formular_id" name=" fk_formular_id" class="input_color" value="<?php echo $fk_formular_id?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="fk_formular_id"> FK Formular ID</label>
    </div>
    <div class="group">
    <input type="text" id="type" name=" type" class="input_color" value="<?php echo $type?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="type">Index Type</label>
    </div>
    <div class="group">
    <input type="text" id="label" name="label" class="input_color" value="<?php echo $label?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="email">Label</label>
    </div>
    <div class="group">
    <input type="text" id="column" name="column" class="input_color" value="<?php echo $column?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="colums">Column</label>
    </div>
    <div class="group">
    <input type="text" id="row" name="row" class="input_color" value="<?php echo $row?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="row">Row</label>
    </div>
    <div class="group">
    <input type="text" id="label_enabled" name="label_enabled" class="input_color" value="<?php echo $label_enabled?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="label_enabled">Label Enabled</label>
    </div>
    <div class="group">
    <input type="text" id="folder" name="folder" class="input_color" value="<?php echo $folder?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="folder">Folder</label>
    </div>
        <button onclick="this.form.elements['action'].value='store'" type="submit">Speichern</button>
        <button onclick="this.form.elements['action'].value='delete'">Löschen</button>
</form>
</div>