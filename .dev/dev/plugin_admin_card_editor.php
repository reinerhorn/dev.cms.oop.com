<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
	header('Location:/index.php');
}
$connection = getDbConnection();
    $id=""; 
    $fk_cardstack_id="Null";
    $idx="";
    $label="";
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $id = $_POST['id'];
        if ($action == "store") {
            $action = $id == "" ? "add" : "update";
        }
        if ($action == "add" || $action == "update") {
            $fk_card_id = $_POST['fk_cardstack_id'];
            $idx = $_POST['idx'];
            $label = $_POST['label'];
        }  
        if ($action == "add") {
            $prepared_stmt = $connection->prepare(
            "INSERT INTO p_card (fk_cardstack_id, idx ,label) VALUES (?, ?, ?)"
            );
            $prepared_stmt->bind_param("sis", $fk_cardstack_id, $idx ,$label);
            $prepared_stmt->execute();
            $result = $connection->query("SELECT id FROM p_card ORDER BY id DESC LIMIT 1");
            if ($rec = $result->fetch_assoc()) {
                $id = $rec['id'];
            }
        } elseif ($action == "update") {
            $prepared_stmt = $connection->prepare(
                "UPDATE p_card SET fk_cardstack_id=?, idx=? ,label=? WHERE id=?"
            );
            $prepared_stmt->bind_param("siss", $fk_cardstack_id, $idx ,$label, $id);
            $prepared_stmt->execute();
        } elseif ($action == "delete") {
            $prepared_stmt = $connection->prepare(
                "DELETE FROM p_card WHERE id=?"
            );
            $prepared_stmt->bind_param("s", $id);
            $prepared_stmt->execute();
            $id = "";
            $action = "add";
        } elseif ($action == "edit") {
            $prepared_stmt = $connection->prepare(
                "SELECT * FROM p_card WHERE id=?"
            );
            $prepared_stmt->bind_param("s", $id);
            $prepared_stmt->execute();
            $card_result = $prepared_stmt->get_result();
            if ($card_record = $card_result->fetch_assoc()) {
				$fk_cardstack_id = $card_record['fk_cardstack_id'];
				$idx = $card_record['idx'];
				$label = $card_record['label'];
            }
        }
    } 
?>
<div class="flex_container">
    <form name="editor" action="" method="post">
        <input type="hidden" name="action" value="page">
        <input type="hidden" name="id" value="<?php echo isset($_POST['id']) ? $_POST['id'] : 'neu' ?>">
        <select name="record_selection" onchange="selectRecord()">
           <option value="">auswählen...</option>
           <option value="neu">neu</option>
           <option value="-" disabled=disabled></option>
           <?php
               $stmt = $connection->prepare("SELECT * FROM p_card");
               $stmt->execute();
               $result = $stmt->get_result();
               while($page = $result->fetch_assoc()) {
                   echo '<option value="' . $page['id'] . '">' . $page['fk_cardstack_id'] .'</option>' . PHP_EOL; 
               }
               $connection->close();
           ?>
       </select>
<br><br>
    
    <div class="group">
    <input type="text" id="idx" name="idx" class="input_color" value="<?php echo $idx?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="idx">Index</label>
    </div>
    <div class="group">
    <input type="text" id="label" name="label" class="input_color" value="<?php echo $label?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="label">Label</label>
    </div>
        <button onclick="this.form.elements['action'].value='store'" type="submit">Speichern</button>
        <button onclick="this.form.elements['action'].value='delete'">Löschen</button>
</form>
</div>