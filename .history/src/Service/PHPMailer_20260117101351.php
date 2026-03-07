<?php>

use CMS\public CoreDatabaseConnection;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    private array $config;

    public function __construct()
    {
        $this->config = CMSConfig::load('MailConfig.php');
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