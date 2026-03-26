<?php
// ─────────────────────────────────────────────
//  app/core/Response.php  –  JSON response helper
//  Equivalente a JSONResponse / HTTPException de FastAPI
// ─────────────────────────────────────────────

namespace App\Core;

class Response
{
    /**
     * Envía respuesta JSON y termina la ejecución.
     *
     * @param mixed $data    Datos a serializar
     * @param int   $status  HTTP status code
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Respuesta de éxito estándar
     */
    public static function success(mixed $data, int $status = 200): never
    {
        self::json($data, $status);
    }

    /**
     * Equivalente a raise HTTPException(status_code=..., detail=...)
     */
    public static function error(string $detail, int $status = 400): never
    {
        self::json(['detail' => $detail], $status);
    }

    /**
     * 401 Unauthorized
     */
    public static function unauthorized(string $detail = 'No autenticado'): never
    {
        self::error($detail, 401);
    }

    /**
     * 403 Forbidden
     */
    public static function forbidden(string $detail = 'Sin permisos'): never
    {
        self::error($detail, 403);
    }

    /**
     * 404 Not Found
     */
    public static function notFound(string $detail = 'Recurso no encontrado'): never
    {
        self::error($detail, 404);
    }

    /**
     * 422 Unprocessable Entity (validación, igual que FastAPI)
     */
    public static function validationError(array $errors): never
    {
        self::json(['detail' => $errors], 422);
    }

    /**
     * Aplica headers CORS y Content-Type a todas las respuestas
     */
    public static function setHeaders(): void
    {
        $config  = require dirname(__DIR__, 1) . '/config/env.php';
        $origins = $config['cors']['allowed_origins'];
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $origins) || in_array($origin, $origins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        header('Content-Type: application/json; charset=utf-8');
    }
}
