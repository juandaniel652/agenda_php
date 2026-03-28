<?php
namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class Email
{
    public static function sendResetEmail(string $email, string $token): void
    {
        // 1. Cargamos la configuración desde env.php
        $config = require dirname(__DIR__, 2) . '/config/env.php';
        $mailCfg = $config['mail'];
        $appCfg  = $config['app'];

        // 2. Construimos el link usando la URL de andros-net.com.ar
        // El link será: https://andros-net.com.ar/agenda/html/reset-password.html?token=...
        $frontendUrl = rtrim($appCfg['frontend_url'], '/');
        $resetLink = $frontendUrl . '/reset-password.html?token=' . $token;

        $html = "
            <div style='font-family: sans-serif; color: #333;'>
                <h2>Recuperar contraseña</h2>
                <p>Haz clic en el siguiente botón para cambiar tu contraseña:</p>
                <div style='margin: 20px 0;'>
                    <a href='{$resetLink}' 
                       style='background: #005691; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                       Cambiar Contraseña
                    </a>
                </div>
                <p>O copia y pega este enlace:</p>
                <p style='font-size: 12px; color: #666;'>{$resetLink}</p>
                <hr>
                <p style='font-size: 11px; color: #999;'>Este enlace expira en 1 hora. Si no solicitaste esto, ignora este correo.</p>
            </div>
        ";

        $mail = new PHPMailer(true);

        try {
            // Configuración del Servidor SMTP de tu Hosting
            $mail->isSMTP();
            $mail->Host       = $mailCfg['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailCfg['username'];
            $mail->Password   = $mailCfg['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Para puerto 465 se usa SMTPS
            $mail->Port       = $mailCfg['port'];
            $mail->CharSet    = 'UTF-8';

            // Remitente y Destinatario
            $mail->setFrom($mailCfg['from_email'], $mailCfg['from_name']);
            $mail->addAddress($email);

            $mail->Subject = 'Recuperación de contraseña - S-Link';
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = "Usa este link para cambiar tu contraseña: {$resetLink}";

            $mail->send();

        } catch (MailException $e) {
            throw new \RuntimeException('Error al enviar email: ' . $mail->ErrorInfo);
        }
    }
}