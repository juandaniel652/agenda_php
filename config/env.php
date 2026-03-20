<?php
// ─────────────────────────────────────────────
//  config/env.php  –  Centraliza toda la config
//  Cargá variables reales desde .env o directamente
// ─────────────────────────────────────────────

return [

    // ── Base de datos ─────────────────────────
    'db' => [
        'driver'   => 'mysql',          // cambiar a 'pgsql' para PostgreSQL
        'host'     => $_ENV['DB_HOST']     ?? 'localhost',
        'port'     => $_ENV['DB_PORT']     ?? '3306',
        'name'     => $_ENV['DB_NAME']     ?? 'agenda',
        'user'     => $_ENV['DB_USER']     ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset'  => 'utf8mb4',
    ],

    // ── JWT ───────────────────────────────────
    'jwt' => [
        'secret'     => $_ENV['JWT_SECRET']      ?? 'CAMBIA_ESTE_SECRET_EN_PRODUCCION',
        'algorithm'  => 'HS256',
        'expires_in' => (int)($_ENV['JWT_EXPIRE_MINUTES'] ?? 60) * 60, // segundos
    ],

    // ── App ───────────────────────────────────
    'app' => [
        'env'      => $_ENV['APP_ENV']  ?? 'development',
        'debug'    => ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
        'base_url' => $_ENV['APP_URL']  ?? 'http://localhost',
    ],

    // ── CORS ──────────────────────────────────
    'cors' => [
        'allowed_origins' => explode(',', $_ENV['CORS_ORIGINS'] ?? '*'),
    ],

];
