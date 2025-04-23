 

<?php echo "<!-- ✅ Language Selector geladen -->";?>
<div id="LanguageSelector" onclick="toggleLanguageMenu()">
	<div class="label">
	<?php
		$result = $main_db_connection->query('SELECT label FROM translation WHERE fk_translation_placeholder="LANG_SELECTOR_LABEL" AND fk_language_id="' . $language . '"');
		if($rec = $result->fetch_assoc()) {
			echo $rec['label'];
		} else {
			echo 'ups...';
		}
	?>
	</div>
	<div class="list">
	<?php
		$result = $main_db_connection->query(
			'SELECT * FROM trans_language ORDER BY label ASC'
		);
		$page = '';
		if(isset($_REQUEST['page'])) {
			$page = '&page=' . $_REQUEST['page'];
		}
		while($rec = $result->fetch_assoc()){
			echo '<a href="?language=' . $rec['id'] . $page . '">' . $rec['label'] . '</a>';
		}
	?>
	</div>
</div>