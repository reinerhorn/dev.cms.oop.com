<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

 private static function sendConfirmationEmail($email, $username, $token = '') {       
        require_once dirname(__DIR__) . '/vendor/autoload.php';

        // SMTP-Konfiguration laden
        $smtpConfig = require('/private/conf/smtp_config.php'); // Pfad zur Konfigurationsdatei

        $mail = new PHPMailer(true);         
        try {
            // SMTP-Serverdaten setzen
            $mail->isSMTP();
            $mail->Host = $smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['username'];
            $mail->Password = $smtpConfig['password']; 
            $mail->SMTPSecure = $smtpConfig['smtp_secure'];
            $mail->Port = $smtpConfig['port'];
            $mail->CharSet = $smtpConfig['charset'];

            $mail->setFrom('hdserviceprovider25@gmail.com', 'H & D');
            $mail->addAddress($email, $username);

            $mail->isHTML(true);
            $mail->Subject = 'Bestätigung deiner Anmeldung';
            $mail->Body    = "Hallo $username,<br>Bitte klicke auf den folgenden Link, um deine Anmeldung zu bestätigen:<br><a href='https://dev.staffingservices.de/verify.php?token=$token'>Konto bestätigen</a>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            return "Fehler beim Senden: {$mail->ErrorInfo}";
        }
 
 }