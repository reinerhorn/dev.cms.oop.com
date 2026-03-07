<?php
declare(strict_types=1);

namespace CMS\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $configPath = getenv('CMS_MAIL_CONFIG');
        if (!$configPath || !file_exists($configPath)) {
            throw new \RuntimeException('CMS_MAIL_CONFIG nicht gesetzt oder ungültig');
        }

        $config = require $configPath;

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host       = $config['host'];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $config['username'];
        $this->mailer->Password   = $config['password'];
        $this->mailer->SMTPSecure = $config['encryption'];
        $this->mailer->Port       = $config['port'];

        $this->mailer->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function send(string $to, string $subject, string $html): void
    {
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($to);
        $this->mailer->Subject = $subject;
        $this->mailer->Body    = $html;

        $this->mailer->send();
    }
}
