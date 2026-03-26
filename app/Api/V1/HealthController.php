<?php
// ─────────────────────────────────────────────
//  app/api/v1/HealthController.php
//  Espejo EXACTO de app/api/v1/health.py
//
//  Python:
//    @router.get("")
//    def health_check():
//        return {"status": "ok"}
//
//  Sin verificación de DB, sin auth, respuesta mínima.
// ─────────────────────────────────────────────

namespace App\Api\V1;

use App\Core\Response;

class HealthController
{
    // GET /api/v1/health
    public function check(): never
    {
        Response::success(['status' => 'ok']);
    }
}