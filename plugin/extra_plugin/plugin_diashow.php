<?php 
 
  $stmt = $db_connection->prepare(
    "SELECT * FROM p_content_diashow WHERE id=?"
  );    
  $stmt->bind_param('s', $plugin_content_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if($record = $result->fetch_assoc()) {
    echo $pfad=include $_SERVER['DOCUMENT_ROOT'].$record['folder'].'.'.'php';   
  }
?>