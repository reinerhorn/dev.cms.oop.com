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
<div class="admin_container">

<!-- Admin Box 1 -->
<div class="admin_box">
    <h2>Page Editor</h2>
    <form name="editor_page" action="" method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo isset($_POST['id']) ? $_POST['id'] : 'neu' ?>">
        <select name="record_selection" onchange="selectRecord()">
            <option value="">auswählen...</option>
            <option value="neu" <?= ($_POST['record_selection'] ?? '') === 'neu' ? 'selected' : '' ?>>neu</option>
            <option value="-" disabled>────────────</option>
            <?php
            $stmt = $connection->prepare("SELECT * FROM page");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($page = $result->fetch_assoc()) {
                $selected = ($page['id'] == ($_POST['record_selection'] ?? '')) ? 'selected' : '';
                echo "<option value=\"{$page['id']}\" $selected>" . htmlspecialchars($page['name']) . "</option>";
            }
            ?>
        </select>

        <!-- Felder -->
        <label for="name">Name</label>
        <input type="text" id="name" name="name" class="input_color" value="<?php echo $name ?>" required>

        <label for="css">CSS</label>
        <input type="text" id="css" name="css" class="input_color" value="<?php echo $css ?>">

        <label for="type">Type</label>
        <input type="text" id="type" name="type" class="input_color" value="<?php echo $type ?>">

        <label for="parent_id">Parent ID</label>
        <input type="text" id="parent_id" name="parent_id" class="input_color" value="<?php echo $parent_id ?>">

        <label for="fk_translation_placeholder">FK Translation Placeholder</label>
        <input type="text" id="fk_translation_placeholder" name="fk_translation_placeholder" class="input_color" value="<?php echo $fk_translation_placeholder ?>">

        <label for="meta_keywords">Meta Keywords</label>
        <input type="text" id="meta_keywords" name="meta_keywords" class="input_color" value="<?php echo $meta_keywords ?>">

        <label for="meta_description">Meta Description</label>
        <input type="text" id="meta_description" name="meta_description" class="input_color" value="<?php echo $meta_description ?>">

        <label for="idx">Index</label>
        <input type="text" id="idx" name="idx" class="input_color" value="<?php echo $idx ?>">

        <label for="enabled">Enabled</label>
        <input type="text" id="enabled" name="enabled" class="input_color" value="<?php echo $enabled ?>">

        <label for="print_all">Print All</label>
        <input type="text" id="print_all" name="print_all" class="input_color" value="<?php echo $print_all ?>">

        <label for="admin_page">Admin</label>
        <input type="text" id="admin_page" name="role" class="input_color" value="<?php echo $admin_role ?>">

        <div class="buttons">
            <button type="submit" name="action" value="save">Speichern</button>
            <button type="submit" name="action" value="delete">Löschen</button>
        </div>
    </form>
</div>

<!-- Admin Box 2 -->
<div class="admin_box">
    <h2>Plaintext Editor</h2>
    <form name="editor_plaintext" action="" method="post">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action ?? '') ?>">
        <input type="hidden" name="id" value="<?php echo $id ?>">
        <select name="id" onchange="this.form.querySelector('[name=action]').value='edit'; this.form.submit();">
            <option value="">auswählen...</option>
            <option value="neu" <?= ($id ?? '') === 'neu' ? 'selected' : '' ?>>neu</option>
            <option value="-" disabled>────────────</option>
            <?php
            $stmt = $connection->prepare("SELECT * FROM p_content_plaintext");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($rec = $result->fetch_assoc()) {
                $selected = ($rec['id'] == $id) ? 'selected' : '';
                echo "<option value=\"{$rec['id']}\" $selected>" . htmlspecialchars($rec['label']) . "</option>\n";
            }
            ?>
        </select>

        <label for="headline">Title</label>
        <input type="text" id="headline" name="headline" class="input_color" value="<?php echo $headline ?>" required>

        <label for="image_path">Image Path</label>
        <input type="text" name="image_path" class="input_color" value="<?php echo $image_path ?>" required>

        <label for="link">Link</label>
        <input type="text" name="link" class="input_color" value="<?php echo $link ?>" required>

        <label for="image_description">Image Description</label>
        <input type="text" name="image_description" class="input_color" value="<?php echo $image_description ?>" required>

        <label for="label">Label</label>
        <input type="text" name="idx" class="input_color" value="<?php echo $idx ?>" required>

        <label for="text">Text</label>
        <textarea name="text" id="text" class="input_color"><?php echo $text ?></textarea>

        <div class="buttons">
            <button type="submit" name="action" value="save">Speichern</button>
            <button type="submit" name="action" value="delete">Löschen</button>
        </div>
    </form>
</div>
<div class="admin_box">
    <h2>Page Config Editor</h2>
    <form name="editor" action="" method="post">
    <input type="hidden" name="action" value="">
    <select onchange="if(this.options[this.selectedIndex].value=='reset') {resetForm(this.form)} else {this.form.elements['action'].value='load_config'; this.form.submit()}" name="page_config_id" onchange="">
        <option value="">Konfiguration auswählen...</option>
        <option value="reset">neu</option>
        <option value="-" disabled=disabled></option>
    <?php
        $result = $connection->query(
            'SELECT page.name AS page_name, plugin.name AS plugin_name, page_config.content_label AS content_label, page_config.id AS id FROM page_config JOIN page ON page_config.fk_page_id=page.id JOIN plugin ON page_config.fk_plugin_id=plugin.id ORDER BY page.name, plugin.name, page_config.content_label'
        );
        while($config = $result->fetch_assoc()) {
            $selected = '';
            if(isset($_POST['page_config_id']) && $_POST['page_config_id'] == $config['id']) {
                $selected = ' selected="selected"';
            }
            echo PHP_EOL . '<option' . $selected .' value="' . $config['id'] . '">' . $config['page_name'] . '/' . $config['plugin_name'] . '/' . $config['content_label'] . '</option>';
        }
    ?>
    </select>
    <br><br>
  <!--  <label for="page_id">Page</label>-->
    <select name="page_id">
        <option value="">auswählen...</option>
        <option value="-" disabled=disabled></option>
    <?php
        $stmt = $connection->prepare("SELECT * FROM page ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while($page = $result->fetch_assoc()) {
            $selected = '';
            if(isset($_POST['page_id']) && $_POST['page_id'] == $page['id']) {
                $selected = ' selected="selected"';
            }
            echo '<option' . $selected . ' value="' . $page['id'] . '">' . $page['name'] . '</option>' . PHP_EOL; 
        }
    ?>
    </select>
    <br><br>
  <!--  <label for="plugin_id">Plug-In</label>-->
    <select name="plugin_id" onchange="this.form.submit()">
        <option value="">auswählen...</option> 
        <option value="-" disabled=disabled></option>
    <?php
        $stmt = $connection->prepare("SELECT * FROM plugin WHERE enabled=1 ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while($plugin = $result->fetch_assoc()) {
            $selected = '';
            if(isset($_POST['plugin_id']) && $plugin['id'] == $_POST['plugin_id']) {
                $selected = ' selected="selected"';
            }
            echo '<option' . $selected . ' value="' . $plugin['id'] . '">' . $plugin['name'] . '</option>' . PHP_EOL; 
        }
    ?>
    </select> 
    <br><br>
    <div class="group">
    <input class="input_color" data-default="0" type="text" id="idx" name="idx" value="<?php echo isset($_POST['idx']) ? $_POST['idx'] : '0' ?>">
        <span class="highlight" value=""></span>
        <span class="bar" value=""></span>
        <label type="text" for="idx">Index</label>
</div>
    <br><br>
 <!--   <label type="text" for="plugin_content_id">Content</label>-->
    <select name="plugin_content_id">
        <option value="">auswählen...</option>
        <option value="-" disabled=disabled></option>
    <?php
        if(isset($_POST['plugin_id']) && $_POST['plugin_id'] != '') {
            $stmt = $connection->prepare(
                'SELECT table_name FROM plugin WHERE id=? LIMIT 1'
            );
            $stmt->bind_param('s', $_POST['plugin_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if($plugin=$result->fetch_assoc()) {
                $stmt = $connection->prepare(
                    "SELECT * FROM " . $plugin['table_name'] . " ORDER BY idx ASC"
                );
                $stmt->execute();
                $result = $stmt->get_result();
                while($content=$result->fetch_assoc()) {
                    $selected = '';
                    if(isset($_POST['plugin_content_id']) && $_POST['plugin_content_id'] == $content['id']) {
                        $selected = ' selected="selected"';
                    }
                    echo PHP_EOL . '<option' . $selected . ' value="' . $content['id'] . '">' . $content['label'] . '</option>';
                }
            } else {
                echo '<option>keine Tabelle zugewiesen</option>';
            }
        }
        #$connection->close();
    ?>
    </select>
    <div class="buttons">
        <button type="submit" name="action" value="save">Speichern</button>
        <button type="submit" name="action" value="delete">Löschen</button>
    </div> 
    </form>
</div>
<script>
function resetForm(form) {
    form.reset();
    form.elements['action'].value = '';
    const selects = form.querySelectorAll('select');
    selects.forEach(select => {
        const options = select.options;
        if (select.name !== 'page_config_id') {
            for (let i = 0; i < options.length; i++) {
                options[i].selected = false;
            }
            select.selectedIndex = 0;
        }
    });
}
 
function selectRecord() {
  const select = document.querySelector('select[name="record_selection"]');
  const form = document.forms['editor_page'];
  form.id.value = select.value;
  form.submit();
}
</script>