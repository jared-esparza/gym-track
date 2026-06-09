<?php

declare(strict_types=1);

namespace GymTracker;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        if (Config::get('APP_ENV', 'production') === 'local' || !class_exists(PHPMailer::class)) {
            $logDir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0775, true);
            }
            file_put_contents($logDir . '/mail.log', "To: {$to}\nSubject: {$subject}\n{$body}\n\n", FILE_APPEND);
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = Config::get('SMTP_HOST', '');
        $mail->Port = (int) Config::get('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = Config::get('SMTP_USER', '');
        $mail->Password = Config::get('SMTP_PASS', '');
        $secure = Config::get('SMTP_SECURE', 'tls');
        if ($secure !== '') {
            $mail->SMTPSecure = $secure;
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(Config::get('SMTP_FROM', 'no-reply@example.com'), Config::get('SMTP_FROM_NAME', 'Gym Tracker'));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    }
}
