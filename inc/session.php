 
<?php 
session_start();
ob_start();
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";

$main_db_connection = getDbConnection();

function session_exists() {
	return isset($_SESSION['email']);
}

function get_page_id_requested() {
	return isset($_REQUEST['page']) ? $_REQUEST['page'] : null;
}

function handle_login() {
	# 1692888607 = login form
	if(get_page_id_requested() != '1692888607') {
		return;
	}
	if(session_exists()) {
		$is_admin = isset($_SESSION['admin_a']) ? $_SESSION['admin_a'] == 1 : 2;
		# ADMIN : MEMBER
		$name = $is_admin ? 'ADMIN-START' : 'MEMBER_BEREICH';
		$page_id = $is_admin ? '1695451523' : '1741942625';
		my_redirect($page_id);
	} elseif(isset($_REQUEST['email']) && isset($_REQUEST['password'])) {
		$db_connection = getDbConnection();
		try {
			$statement = $db_connection->prepare("SELECT * FROM plugin_login_users WHERE email=? LIMIT 1");
			$statement->bind_param('s', $_REQUEST['email']);
			$statement->execute();
			# result wird ggf. bereits genutzt, darf nicht ueberschrieben werden
			$result2 = $statement->get_result();
			if($rec = $result2->fetch_assoc()) {
				if(password_verify($_REQUEST['password'], $rec['password'])) {
					$_SESSION['userid'] = $rec['id'];
					$_SESSION['name'] = $rec['username'];
					$_SESSION['admin_a'] = $rec['admin'];
					$_SESSION['email'] = $rec['email'];
					my_redirect('1692888607'); #1692888607
				}
			}
		} finally {
			#$db_connection->close();
		}
	}
}

function my_redirect($page_id) {
	# do not close main db connection here - causes STRATO problems
	if(!headers_sent()) {
		header('Status: 302 Moved Temporarily', false, 302);
		header('Location: /?page=' . $page_id);
	} else {
		# STRATO fallback
		echo '<script type="text/javascript">';
        echo 'window.location.href="?page='.$page_id.'";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=?page='.$page_id.'" />';
        echo '</noscript>'; exit;
	}
}
	handle_login();

	function is_valid_email($email) {
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		
		$local_part = strstr($email, '@', true);
		if (ctype_digit($local_part)) {
			return false;
		}
		
		$domain = substr(strrchr($email, "@"), 1);
		if (!checkdnsrr($domain, "MX")) {
			return false;
		}
		
		return true;
	}
	
	function handleUserAction($postData) {
		$connection = getDbConnection(); 
		$id = $postData['id'] ?? "";
		$email = $postData['email'] ?? "";
		$password = $postData['password'] ?? "";
		$username = $postData['username'] ?? "";
		$admin = $postData['admin'] ?? "";
		$message = "";
		
		if (!isset($postData['action'])) {
			return compact('id', 'email', 'password', 'username', 'admin', 'message');
		}
		
		$action = $postData['action'];
		if ($action == "store") {
			$action = empty($id) ? "add" : "update";
		}
		
		if (!is_valid_email($email)) {
			$message = "Ungültige E-Mail-Adresse. Bitte eine echte Adresse verwenden.";
			return compact('id', 'email', 'password', 'username', 'admin', 'message');
		}
	
		if ($action == "add") {
			$password = password_hash($password, PASSWORD_DEFAULT);
			$token = bin2hex(random_bytes(16));
			$stmt = $connection->prepare("INSERT INTO plugin_login_users (email, password, username, admin, token) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param("sssis", $email, $password, $username, $admin, $token);
			if ($stmt->execute()) {
				$message = "Benutzer erfolgreich hinzugefügt. Bitte überprüfen Sie Ihre E-Mails zur Bestätigung.";
				send_confirmation_email($email, $username, $token);
			} else {
				$message = "Fehler beim Hinzufügen des Benutzers.";
			}
			$id = "";
			$email = "";
			$password = "";
			$username = "";
			$admin = "";
			$action = 'edit';
		} elseif ($action == "update") {
			$stmt = $connection->prepare("UPDATE plugin_login_users SET email=?, username=?, admin=? WHERE UNIX_TIMESTAMP(id)=?");
			$stmt->bind_param("sssi", $email, $username, $admin, $id);
			if ($stmt->execute()) {
				$message = "Benutzerdaten erfolgreich aktualisiert.";
			} else {
				$message = "Fehler beim Aktualisieren der Benutzerdaten.";
			}
		}
		return compact('id', 'email', 'password', 'username', 'admin', 'message');
	}
	
	$userData = handleUserAction($_POST);
	$loggedInAdmin = $_SESSION['admin'] ?? 0;

	
	#require_once dirname(__DIR__) . '/vendor/autoload.php';
		use PHPMailer\PHPMailer\PHPMailer;
		use PHPMailer\PHPMailer\Exception;

	function send_confirmation_email($email, $username, $token) {		
		require_once dirname(__DIR__) . '/vendor/autoload.php';
	
		$mail = new PHPMailer(true);		 		
		try {
			$mail->isSMTP();
			$mail->Host = 'smtp.gmail.com';
			$mail->SMTPAuth = true;
			$mail->Username = 'hdserviceprovider25@gmail.com';
			$mail->Password = '101@TanZen@101'; // App-Passwort von Google verwenden!
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port = 587;
			$mail->CharSet = 'UTF-8';
	
			$mail->setFrom('hdserviceprovider25@gmail.com', 'H & D');
			$mail->addAddress($email, $username);
	
			$mail->isHTML(true);
			$mail->Subject = 'Bestätigung deiner Anmeldung';
			$mail->Body    = "Hallo $username,<br>Bitte klicke auf den folgenden Link, um deine Anmeldung zu bestätigen:<br><a href='https://deine-webseite.de/verify.php?token=$token'>Konto bestätigen</a>";
	
			$mail->send();
			return true;
		} catch (Exception $e) {
			return "Fehler beim Senden: {$mail->ErrorInfo}";
		}
	}

	?>