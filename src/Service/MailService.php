<?php
declare(strict_types=1);

namespace CMS\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class MailService
{
    private array $config;

    public function __construct()
    {
        $configPath = getenv('CMS_MAIL_CONFIG');

        if (!$configPath || !file_exists($configPath)) {
            throw new \RuntimeException('CMS_MAIL_CONFIG ist nicht gesetzt oder ungültig');
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException('Mail-Konfiguration ist ungültig');
        }

        $this->config = $config;
    }

    public function send(string $to, string $subject, string $html): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->config['host'];
        $mail->Port       = $this->config['port'];
        $mail->SMTPAuth   = true;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;
        $mail->Username   = $this->config['username'];
        $mail->Password   = $this->config['password'];
        // SMTP Verschlüsselung sauber aus Config ableiten
        $secure = $this->config['smtp_secure'] ?? null;

        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = false;
        }

        $mail->setFrom(
            $this->config['from_email'],
            $this->config['from_name']
        );

        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        $mail->send();
    }
}
