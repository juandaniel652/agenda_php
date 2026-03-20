<?php
// ─────────────────────────────────────────────
//  app/core/Security.php  –  JWT + hashing
//  Espejo EXACTO de app/core/security.py
//
//  CORRECCIÓN: Python usa Argon2 (passlib), no bcrypt
//  PHP usa PASSWORD_ARGON2ID (equivalente directo)
// ─────────────────────────────────────────────

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Exception;

class Security
{
    private static array $config;

    private static function cfg(): array
    {
        if (!isset(self::$config)) {
            $env          = require dirname(__DIR__, 2) . '/config/env.php';
            self::$config = $env['jwt'];
        }
        return self::$config;
    }

    // ── Passwords ─────────────────────────────

    /**
     * Hashea una contraseña con bcrypt (equivalente a passlib CryptContext)
     */
    // ── Passwords ─────────────────────────────
    // Python: CryptContext(schemes=["argon2"])
    // PHP:    PASSWORD_ARGON2ID  (PHP 7.3+, equivalente directo)

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64MB — similar a defaults de passlib argon2
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }

    /**
     * Verifica contraseña contra hash almacenado
     */
    public static function verifyPassword(string $plain, string $hashed): bool
    {
        return password_verify($plain, $hashed);
    }

    // ── JWT ───────────────────────────────────

    /**
     * Crea un access token JWT.
     * Equivalente a create_access_token() en security.py
     *
     * @param array $data  Payload (ej: ['sub' => $userId, 'role' => 'admin'])
     */
    public static function createAccessToken(array $data): string
    {
        $cfg = self::cfg();
        $now = time();

        $payload = array_merge($data, [
            'iat' => $now,
            'exp' => $now + $cfg['expires_in'],
            'nbf' => $now,
        ]);

        return JWT::encode($payload, $cfg['secret'], $cfg['algorithm']);
    }

    /**
     * Decodifica y valida un JWT.
     * Lanza excepción si expiró o es inválido.
     *
     * @return array  Payload decodificado
     * @throws Exception
     */
    public static function decodeToken(string $token): array
    {
        $cfg = self::cfg();

        try {
            $decoded = JWT::decode($token, new Key($cfg['secret'], $cfg['algorithm']));
            return (array) $decoded;
        } catch (ExpiredException) {
            throw new Exception('Token inválido o expirado', 401);
        } catch (SignatureInvalidException) {
            throw new Exception('Token inválido o expirado', 401);
        } catch (Exception) {
            throw new Exception('Token inválido o expirado', 401);
        }
    }

    /**
     * Equivalente a secrets.token_urlsafe(32) en Python
     */
    public static function generateResetToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}