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
<div class="admin_container">

<!-- Admin Box 1 -->
<div class="admin_box">
    <h2>Page Editor</h2>
    <?php
    // Workaround: Wenn nur ein Datensatz ausgewählt wird, soll nicht fälschlich gelöscht werden
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
        $_POST['action'] = 'edit';
    }

    // Page Editor: Felder initialisieren und ggf. laden
    $name = '';
    $css = '';
    $type = '';
    $parent_id = '';
    $fk_translation_placeholder = '';
    $meta_keywords = '';
    $meta_description = '';
    $idx = '';
    $enabled = '';
    $print_all = '';
    $admin_role = '';

    if (isset($_POST['record_selection']) && ($_POST['form_name'] ?? '') === 'editor_page') {
        $selectedId = $_POST['record_selection'];
        $stmt = $connection->prepare("SELECT * FROM page WHERE id = ?");
        $stmt->bind_param("s", $selectedId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($record = $result->fetch_assoc()) {
            $name = $record['name'];
            $css = $record['css'];
            $type = $record['type'];
            $parent_id = $record['parent_id'];
            $fk_translation_placeholder = $record['fk_translation_placeholder'];
            $meta_keywords = $record['meta_keywords'];
            $meta_description = $record['meta_description'];
            $idx = $record['idx'];
            $enabled = $record['enabled'];
            $print_all = $record['print_all'];
            $admin_role = $record['role'];
        }
    }
    ?>
    <form name="editor_page" action="" method="post">
        <input type="hidden" name="form_name" value="editor_page">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($_POST['action'] ?? 'store'); ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($_POST['record_selection'] ?? $id ?? '') ?>">
        <?php
        $stmt = $connection->prepare("SELECT * FROM page");
        $stmt->execute();
        $result = $stmt->get_result();
        $pages = [];
        while ($page = $result->fetch_assoc()) {
            $pages[] = $page;
        }
        array_unshift($pages, ['id' => 'neu', 'name' => 'neu']);
        echo SelectGenerator::render(
            'record_selection',
            $pages,
            (($_POST['form_name'] ?? '') === 'editor_page') ? ($_POST['record_selection'] ?? $id ?? '') : '',
            'auswählen...',
            'id',
            'name',
            [
                'onchange' => "selectRecord()"
            ]
        );
        ?>

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
            <button type="button" class="button-save" onclick="setActionAndSubmit(this.form, 'store')">Speichern</button>
            <button type="button" class="button-delete" onclick="setActionAndSubmit(this.form, 'delete')">Löschen</button>
        </div>
    </form>
</div>

<!-- Admin Box 2 -->
<div class="admin_box">
    <h2>Plaintext Editor</h2>
    <?php
    // Plaintext edit fallback
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['record_selection']) || isset($_POST['id'])) && !isset($_POST['action'])) {
        $_POST['action'] = 'edit';
    }
    ?>
    <?php
    // Plaintext-Editor: id aus record_selection_plaintext übernehmen (nur für das richtige Formular)
    if (isset($_POST['record_selection_plaintext']) && (!isset($_POST['form_name']) || $_POST['form_name'] === 'editor_plaintext')) {
        $_POST['id'] = $_POST['record_selection_plaintext'];
        $_POST['form_name'] = 'editor_plaintext';
        $_POST['action'] = 'edit';
    }
    ?>
    <form name="editor_plaintext" action="" method="post">
        <input type="hidden" name="form_name" value="editor_plaintext">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($action ?? 'store') ?>">
        <input type="hidden" name="id" id="plaintext_id" value="<?php echo htmlspecialchars($_POST['record_selection_plaintext'] ?? $id ?? '') ?>">
        <?php
        $plaintextOptions = [];
        $stmt = $connection->prepare("SELECT * FROM p_content_plaintext");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($rec = $result->fetch_assoc()) {
            $plaintextOptions[] = $rec;
        }
        array_unshift($plaintextOptions, ['id' => 'neu', 'label' => 'neu']);
        echo SelectGenerator::render(
            'record_selection_plaintext',
            $plaintextOptions,
            $_POST['record_selection_plaintext'] ?? $id ?? '',
            'auswählen...',
            'id',
            'label',
            [
                'onchange' => "document.getElementById('plaintext_id').value=this.value; this.form.querySelector('[name=action]').value='edit'; this.form.submit();"
            ]
        );

        $selectedId = $_POST['record_selection_plaintext'] ?? $id ?? '';
        $headline = '';
        $image_path = '';
        $link = '';
        $image_description = '';
        $label = '';
        $text = '';
        $idx = '';

        if ($selectedId !== '') {
            $stmt = $connection->prepare("SELECT * FROM p_content_plaintext WHERE id = ?");
            $stmt->bind_param("s", $selectedId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($record = $result->fetch_assoc()) {
                $headline = $record['headline'];
                $image_path = $record['image_path'];
                $link = $record['link'];
                $image_description = $record['image_description'];
                $label = $record['label'];
                $text = $record['text'];
                $idx = $record['idx'];
            }
        }
        ?>

        <label for="headline">Title</label>
        <input type="text" id="headline" name="headline" class="input_color" value="<?= htmlspecialchars($headline) ?>" required>

        <label for="image_path">Image Path</label>
        <input type="text" name="image_path" class="input_color" value="<?= htmlspecialchars($image_path) ?>" >

        <label for="link">Link</label>
        <input type="text" name="link" class="input_color" value="<?= htmlspecialchars($link) ?>">

        <label for="image_description">Image Description</label>
        <input type="text" name="image_description" class="input_color" value="<?= htmlspecialchars($image_description) ?>">

        <label for="idx">Index</label>
        <input type="text" name="idx" class="input_color" value="<?= htmlspecialchars($idx) ?>" >

        <label for="text">Text</label>
        <textarea name="text" id="text" class="input_color"><?= htmlspecialchars($text) ?></textarea>

        <div class="buttons">
        <button type="button" class="button-save" onclick="setActionAndSubmit(this.form, 'store')">Speichern</button>
        <button type="button" class="button-delete" onclick="setActionAndSubmit(this.form, 'delete')">Löschen</button>
        </div>
    </form>
</div>
<div class="admin_box">
    <h2>Page Config Editor</h2>
    <form name="editor" action="" method="post">
    <input type="hidden" name="action" value="<?php echo htmlspecialchars($_POST['action'] ?? 'store'); ?>">
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

    <?php
    // Seiten vorbereiten
    $pages = [];
    $stmt = $connection->prepare("SELECT * FROM page ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $pages[] = $row;
    }

    // Plugins vorbereiten
    $plugins = [];
    $stmt = $connection->prepare("SELECT * FROM plugin WHERE enabled=1 ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $plugins[] = $row;
    }

    // Content vorbereiten (wenn plugin_id gesetzt ist)
    $pluginContentOptions = [];
    if (isset($_POST['plugin_id']) && $_POST['plugin_id'] !== '') {
        $stmt = $connection->prepare('SELECT table_name FROM plugin WHERE id=? LIMIT 1');
        $stmt->bind_param('s', $_POST['plugin_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($plugin = $res->fetch_assoc()) {
            $stmt = $connection->prepare("SELECT * FROM " . $plugin['table_name'] . " ORDER BY idx ASC");
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $pluginContentOptions[] = $row;
            }
        }
    }

    echo SelectGenerator::render('page_id', $pages, $_POST['page_id'] ?? '', 'Seite auswählen...', 'id', 'name');
    ?>

    <br><br>
<!--  <label for="plugin_id">Plug-In</label>-->

    <?php
    echo SelectGenerator::render('plugin_id', $plugins, $_POST['plugin_id'] ?? '', 'Plugin auswählen...', 'id', 'name');
    ?>

    <br><br>
    <div class="group">
    <input class="input_color" data-default="0" type="text" id="idx" name="idx" value="<?php echo isset($_POST['idx']) ? $_POST['idx'] : '0' ?>">
        <span class="highlight" value=""></span>
        <span class="bar" value=""></span>
        <label type="text" for="idx">Index</label>
</div>
    <br><br>
 <!--   <label type="text" for="plugin_content_id">Content</label>-->

    <?php
    echo SelectGenerator::render('plugin_content_id', $pluginContentOptions, $_POST['plugin_content_id'] ?? '', 'Content auswählen...', 'id', 'label');
    ?>

    <div class="buttons">
    <button type="button" class="button-save" onclick="setActionAndSubmit(this.form, 'store')">Speichern</button>
    <button type="button" class="button-delete" onclick="setActionAndSubmit(this.form, 'delete')">Löschen</button>
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

    // Action auf 'edit' setzen, damit die Daten korrekt geladen werden
    form.action.value = 'edit';
    form.submit();
}
function setActionAndSubmit(form, actionValue) {
    form.action.value = actionValue;
    form.submit();
}
</script>