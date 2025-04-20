<?php
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$db_connector = getDbConnection();
$stmt = $db_connector->prepare('SELECT besucherdatum as datum, id as besucherzahl FROM plugin_besucher');
$stmt->execute();
$result = $stmt->get_result();

$data = array();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$db_connector->close();

echo json_encode($data);
?>