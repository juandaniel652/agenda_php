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
        // Usamos la que tienes pero completando los 32 caracteres
        'secret'     => 'oprofjekddoijdkdfjordjde34332abc', 
        'algorithm'  => 'HS256',
        'expires_in' => 3600, 
    ],

    // ── App ───────────────────────────────────
    'app' => [
        'env'      => $_ENV['APP_ENV']  ?? 'development',
        'debug'    => ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
        'base_url' => $_ENV['APP_URL']  ?? 'http://localhost',
        'frontend_url' => 'https://andros-net.com.ar/agenda/html',
    ],

    // ── CORS ──────────────────────────────────
    'cors' => [
        'allowed_origins' => [
            'https://andros-net.com.ar', // <--- Dominio principal
            'http://localhost:5173',      // Por si sigues probando en local con Vite
        ],
    ],

    'mail' => [
        'host'       => 'mail.andros-net.com.ar', // Usualmente es así
        'port'       => 465,                     // SSL
        'username'   => 'no-reply@andros-net.com.ar', 
        'password'   => 'PM?8+?sU.rF}',
        'from_email' => 'no-reply@andros-net.com.ar',
        'from_name'  => 'S-Link Agenda',
    ],

];
