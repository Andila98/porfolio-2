<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Env;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends contact-form notifications over SMTP with PHPMailer. When SMTP is not
 * configured (or Composer packages are missing) it returns false and the
 * message simply stays in the database for the admin page.
 */
final class Mailer
{
    public function enabled(): bool
    {
        return Env::get('MAIL_HOST') !== null && class_exists(PHPMailer::class);
    }

    public function send(string $subject, string $body, ?string $replyTo = null, ?string $replyName = null): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) Env::get('MAIL_HOST');
            $mail->Port = Env::int('MAIL_PORT', 587);
            $mail->SMTPAuth = Env::get('MAIL_USER') !== null;
            $mail->Username = (string) Env::get('MAIL_USER', '');
            $mail->Password = (string) Env::get('MAIL_PASS', '');
            $encryption = Env::get('MAIL_ENCRYPTION', 'tls');
            $mail->SMTPSecure = match ($encryption) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'none' => '',
                default => PHPMailer::ENCRYPTION_STARTTLS,
            };
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) Env::get('MAIL_FROM', 'no-reply@example.com'), (string) Env::get('MAIL_FROM_NAME', 'Portfolio'));
            $mail->addAddress((string) Env::get('MAIL_TO'));
            if ($replyTo !== null) {
                $mail->addReplyTo($replyTo, (string) $replyName);
            }
            $mail->Subject = $subject;
            $mail->Body = $body;
            return $mail->send();
        } catch (MailException) {
            return false;
        }
    }
}
