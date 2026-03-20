<?php
// ─────────────────────────────────────────────
//  app/api/v1/TurnoController.php
//  Espejo EXACTO de app/api/v1/turno.py
//
//  ENDPOINTS NUEVOS vs versión anterior:
//  - GET /turnos/menu
//  - GET /turnos/disponibilidad?tecnico_id=&fecha=
//  - GET /turnos/sugerencias?tecnico_id=
//
//  CORRECCIONES:
//  - Estados con mayúscula: "Cancelado", "Abierto", etc.
//  - requireRoles en vez de requireAdmin
//  - cancelar usa TurnoService->eliminar() (soft delete)
// ─────────────────────────────────────────────

namespace App\Api\V1;

use App\Api\Deps;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\TurnoRepository;
use App\Repositories\TecnicoRepository;
use App\Services\TurnoService;

class TurnoController
{
    private TurnoRepository   $turnoRepo;
    private TecnicoRepository $tecnicoRepo;
    private TurnoService      $service;

    public function __construct()
    {
        $this->turnoRepo   = new TurnoRepository();
        $this->tecnicoRepo = new TecnicoRepository();
        $this->service     = new TurnoService();
    }

    // GET /api/v1/turnos/menu
    // Sin autenticación (igual que Python — no tiene Depends)
    public function menu(): never
    {
        $tecnicos = $this->tecnicoRepo->findAllActivosConHorarios();

        $tecnicosConSlots = [];
        foreach ($tecnicos as $tecnico) {
            $sugerencias = $this->service->obtenerSugerencias((string)$tecnico['id'], 3);
            $tecnicosConSlots[] = [
                'tecnico_id'              => $tecnico['id'],
                'tecnico_nombre'          => $tecnico['nombre'],
                'tecnico_apellido'        => $tecnico['apellido'],
                'proximas_disponibilidades' => $sugerencias,
            ];
        }

        Response::success([
            'opciones' => [
                [
                    'id'          => 1,
                    'accion'      => 'verificar_horario',
                    'descripcion' => 'Verificar disponibilidad en una fecha puntual',
                ],
                [
                    'id'          => 2,
                    'accion'      => 'ver_sugerencias',
                    'descripcion' => 'Ver próximas fechas disponibles automáticamente',
                ],
                [
                    'id'          => 3,
                    'accion'      => 'crear_turno',
                    'descripcion' => 'Grabar una visita (requiere token de admin)',
                    'endpoint'    => 'POST /api/v1/turnos/',
                    'body_ejemplo' => [
                        'numero_ticket' => 'TK-00001',
                        'cliente_id'    => 'uuid-del-cliente',
                        'tecnico_id'    => 'uuid-del-tecnico',
                        'tipo_turno'    => 1,
                        'rango_horario' => 'M',
                        'fecha'         => date('Y-m-d'),
                        'hora_inicio'   => '09:00',
                        'hora_fin'      => '09:30',
                        'estado'        => 'Abierto',
                    ],
                ],
            ],
            'tecnicos_activos' => $tecnicosConSlots,
        ]);
    }

    // GET /api/v1/turnos?fecha=YYYY-MM-DD
    // Sin auth en Python (no tiene Depends en este endpoint)
    public function index(): never
    {
        $filters = [];
        if (!empty($_GET['fecha'])) {
            $filters['fecha'] = $_GET['fecha'];
        }

        $skip  = max(0, (int)($_GET['skip']  ?? 0));
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));

        Response::success($this->turnoRepo->findAll($filters, $skip, $limit));
    }

    // GET /api/v1/turnos/{id}
    public function show(string $id): never
    {
        Deps::getCurrentUser();
        $turno = $this->turnoRepo->findById($id);
        if (!$turno) Response::notFound('Turno no encontrado');
        Response::success($turno);
    }

    // POST /api/v1/turnos
    // Python: require_roles(["admin"])
    public function store(): never
    {
        Deps::requireRoles(['admin']);

        $data = Validator::fromBody()
            ->required('numero_ticket')
            ->required('cliente_id')
            ->required('tecnico_id')
            ->required('fecha')
            ->required('hora_inicio')
            ->required('hora_fin')
            ->required('tipo_turno')
            ->required('rango_horario')
            ->uuid('cliente_id')
            ->uuid('tecnico_id')
            ->date('fecha')
            ->time('hora_inicio')
            ->time('hora_fin')
            ->integer('tipo_turno')
            ->validate();

        // Validar técnico existe y está activo (igual que Python)
        $tecnico = $this->tecnicoRepo->findById($data['tecnico_id']);
        if (!$tecnico) Response::notFound('Técnico no encontrado');
        if (!$tecnico['activo']) Response::error('El técnico no está activo', 400);

        $turno = $this->service->crear($data);
        Response::success($turno, 201);
    }

    // PATCH /api/v1/turnos/{id}/estado
    // Python: require_roles(["admin"])
    public function updateEstado(string $id): never
    {
        Deps::requireRoles(['admin']);

        $estados = ['Abierto', 'Cerrado', 'Reprogramación', 'Cancelado'];

        $data = Validator::fromBody()
            ->required('estado')
            ->inList('estado', $estados)
            ->validate();

        $turno = $this->turnoRepo->findById($id);
        if (!$turno) Response::notFound('Turno no encontrado');

        $updated = $this->turnoRepo->updateEstado($id, $data['estado']);
        Response::success($updated);
    }

    // PATCH /api/v1/turnos/{id}/cancelar
    // Python: require_roles(["admin"]) + TurnoService.eliminar() → soft delete
    public function cancelar(string $id): never
    {
        Deps::requireRoles(['admin']);

        $this->service->eliminar($id); // lanza 404 si no existe
        Response::success(['message' => 'Turno cancelado']);
    }

    // GET /api/v1/turnos/disponibilidad?tecnico_id=UUID&fecha=YYYY-MM-DD
    // Sin auth (igual que Python — no tiene Depends)
    public function disponibilidad(): never
    {
        $tecnicoId = $_GET['tecnico_id'] ?? null;
        $fecha     = $_GET['fecha']      ?? null;

        if (!$tecnicoId || !$fecha) {
            Response::error('Se requieren tecnico_id y fecha', 422);
        }

        $tecnico = $this->tecnicoRepo->findById($tecnicoId);
        if (!$tecnico) Response::notFound('Técnico no encontrado');
        if (!$tecnico['activo']) Response::error('El técnico no está activo', 400);

        $slots = $this->service->obtenerDisponibilidad($tecnicoId, $fecha);

        Response::success([
            'tecnico_id'     => $tecnicoId,
            'tecnico_nombre' => $tecnico['nombre'],
            'fecha'          => $fecha,
            'slots_disponibles' => $slots,
            'total_slots'    => count($slots),
            'siguiente_paso' => [
                'accion'      => 'Elegí un slot y usá el endpoint de creación',
                'endpoint'    => 'POST /api/v1/turnos/',
                'body_ejemplo' => [
                    'numero_ticket' => 'TK-00001',
                    'cliente_id'    => 'uuid-del-cliente',
                    'tecnico_id'    => $tecnicoId,
                    'tipo_turno'    => 1,
                    'rango_horario' => 'M',
                    'fecha'         => $fecha,
                    'hora_inicio'   => $slots[0] ?? '09:00',
                    'hora_fin'      => '09:30',
                    'estado'        => 'Abierto',
                ],
            ],
        ]);
    }

    // GET /api/v1/turnos/sugerencias?tecnico_id=UUID
    // Sin auth (igual que Python)
    public function sugerencias(): never
    {
        $tecnicoId = $_GET['tecnico_id'] ?? null;

        if (!$tecnicoId) {
            Response::error('Se requiere tecnico_id', 422);
        }

        $sugerencias = $this->service->obtenerSugerencias($tecnicoId);

        Response::success([
            'tecnico_id'  => $tecnicoId,
            'sugerencias' => $sugerencias,
        ]);
    }
}