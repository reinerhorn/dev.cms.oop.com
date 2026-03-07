<php
use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	
	function send_confirmation_email($email, $username, $token) {
		require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
	
		$mail = new PHPMailer(true);
	
		try {
			$mail->isSMTP();
			$mail->Host = 'securemail.t-online.de'; 
			$mail->SMTPAuth = true;
			$mail->Username = 'horn.it@t-online.de'; 
			$mail->Password = '101TanZen101'; 
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port = 587;
			$mail->CharSet = 'UTF-8';
	
			$mail->setFrom('no-reply@h-and-d.eu', 'H&D System');
			$mail->addAddress($email, $username);
			$mail->addReplyTo('no-reply@h-and-d.eu', 'H&D System');
	
			$mail->isHTML(true);
			$mail->Subject = 'Bestätigung Ihrer Anmeldung';
			$mail->Body = "Hallo $username,<br><br>Bitte klicken Sie auf den folgenden Link:<br>"
				. "<a href='http://dev.h-and-d.eu/confirm.php?token=$token'>Bestätigung</a>";
	
			$mail->send();
			return true;
		} catch (Exception $e) {
			error_log("Fehler beim Senden der E-Mail: {$mail->ErrorInfo}");
			return false;
		}
	}
	?>
