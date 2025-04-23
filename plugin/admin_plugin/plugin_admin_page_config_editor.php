 
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
	header('Location:/index.php');
} 

#include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";   
$connection = getDbConnection();
$id=""; 
$fk_page_id="";
$fk_plugin_id="";
$plugin_content_id="";
$name="";
$print_all="";
if (isset($_POST['action'])) {
    if($_POST['action'] == 'load_config') {
        $stmt = $connection->prepare(
            'SELECT * FROM page_config WHERE id=? LIMIT 1'
        );
        $stmt->bind_param('s', $_POST['page_config_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if($config = $result->fetch_assoc()) {
            $_POST['page_id'] = $config['fk_page_id'];
            $_POST['plugin_id'] = $config['fk_plugin_id'];
            $_POST['plugin_content_id'] = $config['plugin_content_id'];
            $_POST['idx'] = $config['idx'];
        } else {
            $_POST['idx'] = '0';
        }
    } elseif($_POST['action'] == 'store') {
        $is_update = isset($_POST['page_config_id']) && $_POST['page_config_id'] != '';
        $content_label = null;
        $stmt = $connection->prepare(
            'SELECT table_name FROM plugin WHERE id=?'
        );
        $stmt->bind_param('s', $_POST['plugin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if($plugin = $result->fetch_assoc()) {
            $table_name = $plugin['table_name'];
            # WARN : Stelle ist gefaehrlich fuer Injection
            $resultb = $connection->query(
                "SELECT label FROM " . $table_name . " WHERE id='" . $_POST['plugin_content_id'] . "'"
            );
            if($content = $resultb->fetch_assoc()) {
                $content_label = $content['label'];
            }
        }
        if(isset($content_label) && $is_update) {
            $stmt = $connection->prepare(
                'UPDATE page_config SET fk_page_id=?, fk_plugin_id=?, plugin_content_id=?, content_label=?, idx=? WHERE id=?'
            );
            $stmt->bind_param('ssssis', $_POST['page_id'], $_POST['plugin_id'], $_POST['plugin_content_id'], $content_label, $_POST['idx'], $_POST['page_config_id']);
        } elseif(isset($content_label) && !$is_update) {
            $stmt = $connection->prepare(
                'INSERT INTO page_config (fk_page_id, fk_plugin_id, plugin_content_id, content_label, idx) VALUES(?,?,?,?,?)'
            );
            $stmt->bind_param('ssssi', $_POST['page_id'], $_POST['plugin_id'], $_POST['plugin_content_id'], $content_label, $_POST['idx']);
        }
        $stmt->execute();
    } elseif($_POST['action'] == 'delete' && isset($_POST['page_config_id']) && $_POST['page_config_id'] != '') {
        $stmt = $connection->prepare(
            'DELETE FROM page_config WHERE id=?'
        );
        $stmt->bind_param('s', $_POST['page_config_id']);
        #$stmt->execute();
        #unset($_POST);
    }
}
?>
 <div class="flex_container">  
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
        $connection->close();
    ?>
    </select>
   <br><br>
        <button onclick="this.form.elements['action'].value='store'" type="submit">Speichern</button>
        <button onclick="this.form.elements['action'].value='delete'">Löschen</button>
</form>
</div>