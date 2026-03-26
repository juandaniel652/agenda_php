<?php
// ─────────────────────────────────────────────
//  app/db/session.php  –  PDO connection manager
//  Espejo de app/db/session.py (SQLAlchemy → PDO)
// ─────────────────────────────────────────────

namespace App\Db;

use PDO;
use PDOException;

class Session
{
    private static ?PDO $instance = null;

    /**
     * Devuelve la instancia singleton de PDO.
     * Soporta MySQL (producción) y PostgreSQL (neon / dev).
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // dirname(__DIR__, 2) sube dos niveles: de 'app/Core' a la raíz 'api/'
        $config = require '/home2/androsnet/public_html/api/config/env.php';
        $db = $config['database'];
        // Host suele ser 'localhost' en cPanel, pero el user/pass llevan prefijo
        $dbName = $db['dbname'] ?? $db['name'] ?? null;
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";

        try {
            self::$instance = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // En producción no exponer detalles del error
            $appConfig = $config['app'];
            $message   = $appConfig['debug'] ? $e->getMessage() : 'Database connection failed';
            http_response_code(500);
            echo json_encode(['detail' => $message]);
            exit;
        }

        return self::$instance;
    }

    /**
     * Cierra la conexión (útil en tests o scripts CLI)
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
