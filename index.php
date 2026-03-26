<?php
// ─────────────────────────────────────────────
//  public/index.php  –  Entry point único
//  Equivalente al: uvicorn app.main:app
//
//  Apache/Nginx redirige TODO el tráfico aquí
//  via .htaccess o server block
// ─────────────────────────────────────────────

declare(strict_types=1);

// ── Cargar .env antes de cualquier otra cosa ──
$envFile  = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// ── Autoloader (Composer) ─────────────────────
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode(['detail' => 'Autoloader no encontrado. Ejecutá: composer install']);
    exit;
}
require $autoload;

// ── Timezone ──────────────────────────────────
date_default_timezone_set($_ENV['TZ'] ?? 'America/Argentina/Buenos_Aires');

// ── Error handling en desarrollo ─────────────
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // No exponer en HTML, lo manejamos nosotros
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'detail' => 'Internal Server Error',
            'debug'  => [
                'error'   => $errstr,
                'file'    => $errfile,
                'line'    => $errline,
            ],
        ]);
        exit;
    });
    set_exception_handler(function (\Throwable $e): void {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'detail' => 'Unhandled Exception',
            'debug'  => [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ],
        ]);
        exit;
    });
} else {
    // Producción: silenciar errores, solo loguear
    error_reporting(0);
    ini_set('display_errors', '0');
    set_exception_handler(function (\Throwable $e): void {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['detail' => 'Internal Server Error']);
        exit;
    });
}

// ── Despachar al router ───────────────────────
require dirname(__DIR__) . '/app/main.php';
