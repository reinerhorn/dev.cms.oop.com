<?php

use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    private array $config;

    public function __construct()
    {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envPath)) {
            throw new \RuntimeException('.env-Datei nicht gefunden');
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), 'CMS_CONFIG_PATH=')) {
                putenv($line);
                break;
            }
        }

        $configPath = getenv('CMS_CONFIG_PATH');
        if ($configPath === false || !file_exists($configPath)) {
            throw new \RuntimeException('CMS_CONFIG_PATH ist nicht gesetzt oder ungültig');
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
        $mail->Username   = $this->config['username'];
        $mail->Password   = $this->config['password'];
        $mail->SMTPSecure = $this->config['secure'];

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