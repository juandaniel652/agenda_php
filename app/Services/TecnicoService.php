<?php
// ─────────────────────────────────────────────
//  app/services/TecnicoService.php
//  Espejo EXACTO de app/services/tecnico_service.py
//
//  CORRECCIONES vs versión anterior:
//  1. crear_tecnico() acepta horarios[] opcionales en el mismo request
//  2. actualizar() REEMPLAZA horarios (borra y recrea), no agrega
//  3. eliminar() es SOFT DELETE: activo = false, no DELETE real
// ─────────────────────────────────────────────

namespace App\Services;

use App\Repositories\TecnicoRepository;
use App\Core\Response;

class TecnicoService
{
    private TecnicoRepository $repo;

    public function __construct()
    {
        $this->repo = new TecnicoRepository();
    }

    /**
     * Equivalente a crear_tecnico(data: dict) en tecnico_service.py
     *
     * Crea el técnico y sus horarios en una sola operación atómica.
     * $data['horarios'] es opcional: array de {dia_semana, hora_inicio, hora_fin}
     */
    public function crearTecnico(array $data): array
    {
        // Separar horarios del resto (igual que Python: data.pop("horarios", None))
        $horarios = $data['horarios'] ?? null;
        unset($data['horarios']);

        return $this->repo->createConHorarios($data, $horarios);
    }

    /**
     * Equivalente a listar() en tecnico_service.py
     * Solo devuelve técnicos activos con sus horarios (JOIN)
     */
    public function listar(): array
    {
        return $this->repo->findAllActivosConHorarios();
    }

    /**
     * Equivalente a actualizar(id, data: TecnicoUpdate) en tecnico_service.py
     *
     * Si vienen horarios → BORRA todos los existentes y los recrea.
     * Si no vienen horarios → solo actualiza campos básicos.
     */
    public function actualizar(string $id, array $data): array
    {
        $tecnico = $this->repo->findById($id);
        if (!$tecnico) {
            Response::notFound('Técnico no encontrado');
        }

        $horarios = $data['horarios'] ?? null; // null = no tocar horarios
        unset($data['horarios']);

        return $this->repo->updateConHorarios($id, $data, $horarios);
    }

    /**
     * Equivalente a eliminar(id) en tecnico_service.py
     *
     * SOFT DELETE: setea activo = False, no borra el registro.
     */
    public function eliminar(string $id): array
    {
        $tecnico = $this->repo->findById($id);
        if (!$tecnico) {
            Response::notFound('Técnico no encontrado');
        }

        $this->repo->softDelete($id);

        return ['message' => 'Técnico desactivado'];
    }
}