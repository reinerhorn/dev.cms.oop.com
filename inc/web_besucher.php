 <?php
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$browser = $_SERVER['HTTP_USER_AGENT'];
$os = "Unbekanntes Betriebssystem";
$browser_keywords = array('Firefox', 'Chrome', 'Safari', 'Opera', 'MSIE','Edge','Trident');
$os_keywords = array('Windows', 'Macintosh', 'Linux');
    foreach ($browser_keywords as $keyword) {
        if (strpos($user_agent, $keyword) !== false) {
            $browser = $keyword;
            break;
        }
    }
    foreach ($os_keywords as $keyword) {
        if (strpos($user_agent, $keyword) !== false) {
            $os = $keyword;
            break;
        }
    }
$ip_address = $_SERVER['REMOTE_ADDR'];
$db_connector = getDbConnection();
$stmt = $db_connector->prepare("SELECT COUNT(*) AS count FROM plugin_besucher WHERE ip_address=?"
);
$stmt->bind_param('s', $ip_address);
$stmt->execute();
$result = $stmt->get_result();
if ($record = $result->fetch_assoc()) {
    $count = $record['count'];
}
$visitor_ip = $_SERVER['REMOTE_ADDR'];
$server_ip1 = "84.186.224.243";
$server_ip = "127.0.0.1";
if ($visitor_ip !== $server_ip && $server_ip1){
    $prepared_stmt = $db_connector->prepare(
        "INSERT INTO plugin_besucher (browser, betriebssystem, ip_address) VALUES (?, ?, ? )"
        );
        $prepared_stmt->bind_param("sss", $browser, $os, $visitor_ip);
        $prepared_stmt->execute();
}
#$stmt->close();
?>