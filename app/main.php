<?php
require_once '/home2/androsnet/public_html/api/vendor/autoload.php';

// ─────────────────────────────────────────────
//  app/main.php  –  Router principal (Producción)
// ─────────────────────────────────────────────

use App\Core\Response;
use App\Api\V1\AuthController;
use App\Api\V1\ClienteController;
use App\Api\V1\HealthController;
use App\Api\V1\TecnicoController;
use App\Api\V1\TurnoController;

// ── Headers globales y CORS ───────────────────
Response::setHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Parsear y Normalizar URI ──────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Forzar que la ruta sea relativa a /api/v1
$pos = strpos($uri, '/api/v1');
if ($pos !== false) {
    $uri = substr($uri, $pos); 
}

// Convertir en array limpio de segmentos
$segments = array_values(array_filter(explode('/', $uri)));

/**
 * Estructura esperada en $segments tras normalizar:
 * [0] => 'api'
 * [1] => 'v1'
 * [2] => recurso (auth, clientes, turnos...)
 * [3] => id o acción (login, register, 123-uuid...)
 * [4] => sub-acción (cancelar, estado...)
 */

$resource = isset($segments[2]) ? trim(strtolower($segments[2])) : ''; 
$id       = isset($segments[3]) ? trim(strtolower($segments[3])) : null; 
$action   = isset($segments[4]) ? trim(strtolower($segments[4])) : null; 

// ── Validación de Prefijo ─────────────────────
if (($segments[0] ?? '') !== 'api' || ($segments[1] ?? '') !== 'v1') {
    // Si la URI está vacía o es solo /, dar bienvenida
    if (empty($segments)) {
        Response::success([
            'service' => 'agenda-php',
            'status'  => 'running',
            'version' => '1.0.0'
        ]);
    }
    Response::notFound("Endpoint '{$uri}' no reconocido fuera de /api/v1");
}

// ── Ruteo por Recurso ─────────────────────────

match ($resource) {
    // 1. HEALTH
    'health' => (new HealthController())->check(),

    // 2. AUTH
    'auth' => (function() use ($method, $id, $resource) {
        $ctrl = new AuthController();
        match (true) {
            $method === 'POST' && $id === 'login'           => $ctrl->login(),
            $method === 'POST' && $id === 'register'        => $ctrl->register(),
            $method === 'POST' && $id === 'forgot-password' => $ctrl->forgotPassword(),
            $method === 'POST' && $id === 'reset-password'  => $ctrl->resetPassword(),
            $method === 'GET'  && $id === 'me'              => $ctrl->me(),
            $method === 'GET'  && $id === 'ping'            => Response::success(['auth' => 'ok']),
            default => Response::notFound("Acción '{$id}' no válida para el recurso '{$resource}'")
        };
    })(),

    // 3. CLIENTES
    'clientes' => (function() use ($method, $id) {
        $ctrl = new ClienteController();
        match (true) {
            $method === 'GET'    && $id === null     => $ctrl->index(),
            $method === 'GET'    && $id !== null     => $ctrl->show($id),
            $method === 'POST'   && $id === null     => $ctrl->store(),
            in_array($method, ['PUT', 'PATCH']) && $id !== null => $ctrl->update($id),
            $method === 'DELETE' && $id !== null     => $ctrl->destroy($id),
            default => Response::notFound('Ruta de Clientes no válida')
        };
    })(),

    // 4. TÉCNICOS
    'tecnicos' => (function() use ($method, $id) {
        $ctrl = new TecnicoController();
        match (true) {
            $method === 'GET'    && $id === null => $ctrl->index(),
            $method === 'POST'   && $id === null => $ctrl->store(),
            in_array($method, ['PUT', 'PATCH']) && $id !== null => $ctrl->update($id),
            $method === 'DELETE' && $id !== null => $ctrl->destroy($id),
            default => Response::notFound('Ruta de Técnicos no válida')
        };
    })(),

    // 5. TURNOS
    'turnos' => (function() use ($method, $id, $action) {
        $ctrl = new TurnoController();
        match (true) {
            // Rutas fijas
            $method === 'GET'  && $id === 'menu'           => $ctrl->menu(),
            $method === 'GET'  && $id === 'disponibilidad' => $ctrl->disponibilidad(),
            $method === 'GET'  && $id === 'sugerencias'    => $ctrl->sugerencias(),
            
            // CRUD
            $method === 'GET'  && $id === null             => $ctrl->index(),
            $method === 'POST' && $id === null             => $ctrl->store(),
            $method === 'GET'  && $id !== null && $action === null => $ctrl->show($id),
            
            // Acciones
            $method === 'PATCH' && $id !== null && $action === 'cancelar' => $ctrl->cancelar($id),
            $method === 'PATCH' && $id !== null && $action === 'estado'   => $ctrl->updateEstado($id),
            
            default => Response::notFound('Ruta de Turnos no válida')
        };
    })(),

    // DEFAULT FALLBACK
    default => Response::notFound("Recurso '{$resource}' no encontrado")
};