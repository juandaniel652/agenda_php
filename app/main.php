<?php
// ─────────────────────────────────────────────
//  app/main.php  –  Router principal
//  Espejo EXACTO de app/main.py
//
//  RUTAS NUEVAS vs versión anterior:
//  - GET /turnos/menu
//  - GET /turnos/disponibilidad
//  - GET /turnos/sugerencias
//
//  CORRECCIONES:
//  - TecnicoController ya no tiene sub-rutas de disponibilidad individual
//  - Rutas de turno reordenadas (las rutas fijas antes que las variables)
// ─────────────────────────────────────────────

require_once __DIR__ . '/../vendor/autoload.php';

// ─────────────────────────────────────────────
//  app/main.php – Router principal
// ─────────────────────────────────────────────

use App\Core\Response;
use App\Api\V1\AuthController;
use App\Api\V1\ClienteController;
use App\Api\V1\HealthController;
use App\Api\V1\TecnicoController;
use App\Api\V1\TurnoController;

// ── Headers globales ──────────────────────────
Response::setHeaders();

// ── Preflight CORS ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Parsear URI ───────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

$basePath = rtrim($_ENV['BASE_PATH'] ?? '', '/');
if ($basePath && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}

$segments = array_values(array_filter(explode('/', $uri)));
// /api/v1/turnos/disponibilidad → ['api','v1','turnos','disponibilidad']

// ── Health ────────────────────────────────────
// Python: prefix="/health" → GET /api/v1/health
if ($uri === '/api/v1/health' || $uri === '/health') {
    (new HealthController())->check();
}

// ── Root ─────────────────────────────────────
if ($uri === '' || $uri === '/') {
    Response::success([
        'service' => 'agenda-php',
        'status'  => 'running',
        'version' => '1.0.0',
    ]);
}

if (($segments[0] ?? '') !== 'api' || ($segments[1] ?? '') !== 'v1') {
    Response::notFound('Endpoint no encontrado');
}

$resource = $segments[2] ?? '';
$id       = $segments[3] ?? null;
$action   = $segments[4] ?? null;

// ─────────────────────────────────────────────
//  AUTH  →  /api/v1/auth/{accion}
// ─────────────────────────────────────────────
if ($resource === 'auth') {
    $ctrl = new AuthController();

    match (true) {
        $method === 'POST' && $id === 'login'           => $ctrl->login(),
        $method === 'POST' && $id === 'register'        => $ctrl->register(),
        $method === 'POST' && $id === 'forgot-password' => $ctrl->forgotPassword(),
        $method === 'POST' && $id === 'reset-password'  => $ctrl->resetPassword(),
        $method === 'GET'  && $id === 'me'              => $ctrl->me(),
        $method === 'GET'  && $id === 'ping'            => Response::success(['auth' => 'ok']),
        default => Response::notFound("Auth endpoint '{$id}' no encontrado"),
    };
}

// ─────────────────────────────────────────────
//  CLIENTES  →  /api/v1/clientes
// ─────────────────────────────────────────────
if ($resource === 'clientes') {
    $ctrl = new ClienteController();

    match (true) {
        $method === 'GET'    && $id === null     => $ctrl->index(),
        $method === 'GET'    && $id !== null     => $ctrl->show($id),
        $method === 'POST'   && $id === null     => $ctrl->store(),
        $method === 'PUT'    && $id !== null     => $ctrl->update($id),
        $method === 'PATCH'  && $id !== null     => $ctrl->update($id),
        $method === 'DELETE' && $id !== null     => $ctrl->destroy($id),
        default => Response::notFound('Clientes endpoint no encontrado'),
    };
}

// ─────────────────────────────────────────────
//  TÉCNICOS  →  /api/v1/tecnicos
//  Sin sub-rutas individuales de disponibilidad
//  (los horarios se manejan desde el PUT del técnico)
// ─────────────────────────────────────────────
if ($resource === 'tecnicos') {
    $ctrl = new TecnicoController();

    match (true) {
        $method === 'GET'    && $id === null => $ctrl->index(),
        $method === 'POST'   && $id === null => $ctrl->store(),
        in_array($method, ['PUT', 'PATCH']) && $id !== null => $ctrl->update($id),
        $method === 'DELETE' && $id !== null => $ctrl->destroy($id),
        default => Response::notFound('Técnicos endpoint no encontrado'),
    };
}

// ─────────────────────────────────────────────
//  TURNOS  →  /api/v1/turnos
//  IMPORTANTE: rutas fijas ANTES que /{id}
//  para que 'menu','disponibilidad','sugerencias'
//  no sean interpretadas como UUIDs
// ─────────────────────────────────────────────
if ($resource === 'turnos') {
    $ctrl = new TurnoController();

    match (true) {
        // ── Rutas fijas (sin ID) ───────────────────
        $method === 'GET'  && $id === 'menu'           => $ctrl->menu(),
        $method === 'GET'  && $id === 'disponibilidad' => $ctrl->disponibilidad(),
        $method === 'GET'  && $id === 'sugerencias'    => $ctrl->sugerencias(),

        // ── CRUD base ─────────────────────────────
        $method === 'GET'  && $id === null             => $ctrl->index(),
        $method === 'POST' && $id === null             => $ctrl->store(),
        $method === 'GET'  && $id !== null && $action === null => $ctrl->show($id),

        // ── Acciones sobre un turno ───────────────
        $method === 'PATCH' && $id !== null && $action === 'cancelar' => $ctrl->cancelar($id),
        $method === 'PATCH' && $id !== null && $action === 'estado'   => $ctrl->updateEstado($id),

        default => Response::notFound('Turnos endpoint no encontrado'),
    };
}

// ── Fallback ──────────────────────────────────
Response::notFound("Endpoint '{$uri}' no encontrado");