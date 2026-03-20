<?php
// ─────────────────────────────────────────────
//  app/utils/CloudinaryUpload.php
//  Espejo de app/utils/cloudinary_upload.py
//
//  Requiere en .env:
//    CLOUDINARY_CLOUD_NAME=xxx
//    CLOUDINARY_API_KEY=xxx
//    CLOUDINARY_API_SECRET=xxx
// ─────────────────────────────────────────────

namespace App\Utils;

use App\Core\Response;

class CloudinaryUpload
{
    /**
     * Equivalente a upload_image(imagen: UploadFile) en cloudinary_upload.py
     *
     * Recibe el array de $_FILES['imagen'] y devuelve la URL pública.
     *
     * @param  array  $file  Elemento de $_FILES (tmp_name, name, type, size)
     * @return string        URL pública de Cloudinary
     */
    public static function upload(array $file): string
    {
        $cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? null;
        $apiKey    = $_ENV['CLOUDINARY_API_KEY']    ?? null;
        $apiSecret = $_ENV['CLOUDINARY_API_SECRET'] ?? null;

        if (!$cloudName || !$apiKey || !$apiSecret) {
            Response::error('Cloudinary no configurado (faltan variables de entorno)', 500);
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            Response::error('Archivo de imagen inválido', 400);
        }

        // Validar tipo MIME
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime         = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedTypes, true)) {
            Response::error('Tipo de imagen no permitido. Use JPG, PNG, WEBP o GIF', 400);
        }

        // Construir firma para la API de Cloudinary
        $timestamp = time();
        $params    = ['timestamp' => $timestamp];
        $signature = self::sign($params, $apiSecret);

        // Upload via API REST de Cloudinary (igual que el SDK de Python)
        $url = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

        $postFields = [
            'file'      => new \CURLFile($file['tmp_name'], $mime, $file['name']),
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Response::error('Error al subir imagen a Cloudinary', 500);
        }

        $result = json_decode($response, true);

        if (empty($result['secure_url'])) {
            Response::error('Cloudinary no devolvió URL de imagen', 500);
        }

        return $result['secure_url'];
    }

    private static function sign(array $params, string $secret): string
    {
        ksort($params);
        $str = implode('&', array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($params),
            array_values($params)
        ));
        return sha1($str . $secret);
    }
}