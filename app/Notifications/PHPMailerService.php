<?php

namespace App\Notifications;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PHPMailerService
{
    public function sendEmail($to, $subject, $body)
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 0; // Set to 2 for debug output
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST', 'smtp.gmail.com'); // Use SMTP host like Gmail
            $mail->SMTPAuth = true;
            $mail->Username = env('milbertgaringa5@gmail.com'); // SMTP username (your email)
            $mail->Password = env('Gauro331'); // SMTP password (App password for Gmail)
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
            $mail->Port = env('MAIL_PORT', 587); // 587 for tls, 465 for ssl

            // Recipients
            $mail->setFrom(env('milbertgaringa5@gmail.com'), env('DMO')); // Your email and name
            $mail->addAddress($to); // Add recipient

            // Content
            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Send the email
            $mail->send();

            return 'Message has been sent';
        } catch (Exception $e) {
            return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
        }
    }
}
