<?php
// ─────────────────────────────────────────────
//  app/api/deps.php  –  Dependencias / Middleware
//  Espejo EXACTO de app/api/deps.py
//
//  CORRECCIÓN: require_roles(["admin","user"]) en vez de requireAdmin()
//  El Python original tiene roles flexibles por endpoint
// ─────────────────────────────────────────────

namespace App\Api;

use App\Core\Security;
use App\Core\Response;
use App\Repositories\UserRepository;

class Deps
{
    /**
     * Equivalente a get_current_user() en deps.py
     *
     * Extrae Bearer token → decodifica JWT → busca user en DB
     * Si el user no existe o está inactivo → 401
     */
    public static function getCurrentUser(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Token inválido');
        }

        $token = substr($authHeader, 7);

        try {
            $payload = Security::decodeToken($token);
        } catch (\Exception $e) {
            Response::unauthorized($e->getMessage());
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            Response::unauthorized('Token inválido');
        }

        $user = (new UserRepository())->findById($userId);

        if (!$user || !$user['is_active']) {
            Response::unauthorized('Usuario no autorizado');
        }

        return $user;
    }

    /**
     * Equivalente a require_roles(allowed_roles: list[str]) en deps.py
     *
     * Uso en controllers:
     *   Deps::requireRoles(['admin'])        → solo admin
     *   Deps::requireRoles(['admin','user']) → admin o user
     *
     * @param  string[] $allowedRoles
     * @return array    Usuario autenticado
     */
    public static function requireRoles(array $allowedRoles): array
    {
        $user = self::getCurrentUser();

        if (!in_array($user['role'], $allowedRoles, true)) {
            Response::forbidden('Permisos insuficientes');
        }

        return $user;
    }
}