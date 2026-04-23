<?php
// /plugin/admin_plugin/form_editor_page.php

// Datenbankverbindung und Handler sollten vor Einbindung bereits gesetzt sein
?>
<div class="admin_box">
    <h2>Page Editor</h2>
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
            [ 'onchange' => "selectRecord()" ]
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

        <label for="admin_page">Admin</label>
        <input type="text" id="admin_page" name="role" class="input_color" value="<?php echo $admin_role ?>">

        <div class="buttons">
            <button type="button" class="button-save" onclick="setActionAndSubmit(this.form, 'store')">Speichern</button>
            <button type="button" class="button-delete" onclick="setActionAndSubmit(this.form, 'delete')">Löschen</button>
        </div>
    </form>
</div>
 