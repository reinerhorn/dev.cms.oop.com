<!--  ===================================================================
	  Urheberrechtshinweis / Copyright

	  Die Gestaltung, Inhalte und Programmierung dieser Seiten
	  unterliegen dem Urheberrecht. Urheber ist Reiner Horn
	  Eine Verwendung der Inhalte außerhalb der vom Urheber betriebenen
	  Domains ist nicht gestattet. Ein Verstoß gegen diese Bestimmungen
	  wird als Urheberrechtsverletzung betrachtet und bei Bekanntwerdung 
	  unter Einsatz von Rechtsmitteln geahndet.
      Verwndung von der leeren datenbank und code muss eine genehmigung
      des Urhebers eingeholt werden.
      Die Datenbank und der Code sind urheberrechtlich geschützt.
      Die Verwendung der Datenbank und des Codes ist nur mit
      ausdrücklicher Genehmigung des Urhebers gestattet.
      Die Datenbank und der Code dürfen nicht ohne Genehmigung
      des Urhebers kopiert, verbreitet oder veröffentlicht werden.

	 Reiner Horn
	 Huaptstr. 8
	 40597 Düsseldorf
     horm.it@t-online.de
===================================================================  -->

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