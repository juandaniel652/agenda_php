<?php
// ─────────────────────────────────────────────
//  config/env.php  –  Centraliza toda la config
//  Cargá variables reales desde .env o directamente
// ─────────────────────────────────────────────

return [

    // ── Base de datos ─────────────────────────
    'database' => [
        'driver'   => 'mysql',          // cambiar a 'pgsql' para PostgreSQL
        'host'     => $_ENV['DB_HOST']     ?? 'localhost',
        'port'     => $_ENV['DB_PORT']     ?? '3306',
        'name'     => $_ENV['DB_NAME']     ?? 'androsne_agend0952pn',
        'user'     => $_ENV['DB_USER']     ?? 'androsne_8agendnewml',
        'password' => $_ENV['DB_PASSWORD'] ?? '%EbM%BLN)%?D',
        'charset'  => 'utf8mb4',
    ],

    // ── JWT ───────────────────────────────────
    'jwt' => [
        // Esta clave tiene exactamente 32 caracteres y es segura
        'secret'     => $_ENV['JWT_SECRET'] ?? '8f7d6e5c4b3a2f1e0d9c8b7a6f5e4d3c',
        'algorithm'  => 'HS256',
        'expires_in' => (int)($_ENV['JWT_EXPIRE_MINUTES'] ?? 60) * 60, // 1 hora
    ],

    // ── App ───────────────────────────────────
    'app' => [
        'env'      => $_ENV['APP_ENV']  ?? 'development',
        'debug'    => ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
        'base_url' => $_ENV['APP_URL']  ?? 'http://localhost',
    ],

    // ── CORS ──────────────────────────────────
    'cors' => [
        'allowed_origins' => [
            'https://andros-net.com.ar', // <--- Dominio principal
            'http://localhost:5173',      // Por si sigues probando en local con Vite
        ],
    ],

];
