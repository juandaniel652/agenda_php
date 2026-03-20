<?php
// ─────────────────────────────────────────────
//  app/core/Email.php
//  Espejo EXACTO de app/core/email.py
//
//  Python usaba: fastapi-mail → Gmail SMTP puerto 587, STARTTLS
//  PHP usa:      PHPMailer (mismo protocolo, misma config)
//
//  Requiere en composer.json:
//    "phpmailer/phpmailer": "^6.9"
//
//  Requiere en .env (mismas variables que Python):
//    MAIL_USERNAME=tu@gmail.com
//    MAIL_PASSWORD=tu_app_password_de_gmail
//    MAIL_FROM=tu@gmail.com
//    FRONTEND_URL=https://tu-frontend.com
// ─────────────────────────────────────────────

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class Email
{
    /**
     * Equivalente a send_reset_email(email: EmailStr, token: str) en email.py
     *
     * Python:
     *   reset_link = f"{settings.FRONTEND_URL}/reset-password?token={token}"
     *   html = f"<h2>Recuperar contraseña</h2>..."
     *   message = MessageSchema(subject="Recuperación de contraseña", ...)
     *   await fm.send_message(message)
     */
    public static function sendResetEmail(string $email, string $token): void
    {
        // Mismas variables de entorno que Python (settings.MAIL_* → $_ENV['MAIL_*'])
        $username    = $_ENV['MAIL_USERNAME'] ?? null;
        $password    = $_ENV['MAIL_PASSWORD'] ?? null;
        $from        = $_ENV['MAIL_FROM']     ?? $username;
        $frontendUrl = $_ENV['FRONTEND_URL']  ?? '';

        if (!$username || !$password) {
            throw new \RuntimeException('Configuración de email incompleta (MAIL_USERNAME / MAIL_PASSWORD)');
        }

        // Mismo link que Python: f"{settings.FRONTEND_URL}/reset-password?token={token}"
        $resetLink = rtrim($frontendUrl, '/') . '/reset-password?token=' . $token;

        // Mismo HTML que Python
        $html = "
            <h2>Recuperar contraseña</h2>
            <p>Haz clic en el siguiente enlace para cambiar tu contraseña:</p>
            <a href=\"{$resetLink}\">{$resetLink}</a>
            <p>Este enlace expira en 1 hora.</p>
        ";

        $mail = new PHPMailer(true);

        try {
            // Misma config que Python:
            // MAIL_SERVER="smtp.gmail.com", MAIL_PORT=587, MAIL_STARTTLS=True
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS = puerto 587
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Mismo subject y recipients que Python
            $mail->setFrom($from, 'Agenda');
            $mail->addAddress($email);
            $mail->Subject  = 'Recuperación de contraseña';
            $mail->isHTML(true);
            $mail->Body     = $html;
            $mail->AltBody  = "Usá este link para cambiar tu contraseña: {$resetLink} (expira en 1 hora)";

            $mail->send();

        } catch (MailException $e) {
            throw new \RuntimeException('Error al enviar email: ' . $mail->ErrorInfo);
        }
    }
}