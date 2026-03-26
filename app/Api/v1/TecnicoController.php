<?php
// ─────────────────────────────────────────────
//  app/api/v1/TecnicoController.php
//  Espejo EXACTO de app/api/v1/tecnico.py
//
//  CORRECCIONES:
//  1. POST y PUT usan multipart/form-data (Form + File), no JSON
//  2. horarios viene como JSON string en $_POST['horarios']
//  3. imagen sube via Cloudinary (si viene)
//  4. requireRoles en vez de requireAdmin
//  5. DELETE hace soft-delete via TecnicoService
// ─────────────────────────────────────────────

namespace App\Api\V1;

use App\Api\Deps;
use App\Core\Response;
use App\Services\TecnicoService;
use App\Utils\CloudinaryUpload;

class TecnicoController
{
    private TecnicoService $service;

    public function __construct()
    {
        $this->service = new TecnicoService();
    }

    // GET /api/v1/tecnicos
    // Python: require_roles(["admin", "user"])
    public function index(): never
    {
        Deps::requireRoles(['admin', 'user']);
        Response::success($this->service->listar());
    }

    // POST /api/v1/tecnicos
    // Python: multipart Form(...) + File(None) + horarios como JSON string
    // require_roles(["admin"])
    public function store(): never
    {
        Deps::requireRoles(['admin']);

        // Validar campos requeridos del form
        $nombre            = $_POST['nombre']             ?? null;
        $apellido          = $_POST['apellido']           ?? null;
        $duracionTurnoMin  = $_POST['duracion_turno_min'] ?? null;

        if (!$nombre || !$apellido || $duracionTurnoMin === null) {
            Response::validationError([
                'nombre'            => !$nombre           ? ['Requerido'] : [],
                'apellido'          => !$apellido         ? ['Requerido'] : [],
                'duracion_turno_min'=> $duracionTurnoMin === null ? ['Requerido'] : [],
            ]);
        }

        // Subir imagen si viene (igual que Python: upload_image(imagen))
        $imagenUrl = null;
        if (!empty($_FILES['imagen']['tmp_name'])) {
            $imagenUrl = CloudinaryUpload::upload($_FILES['imagen']);
        }

        // Parsear horarios JSON string (igual que Python: json.loads(horarios))
        $horariosList = null;
        if (!empty($_POST['horarios'])) {
            $horariosList = json_decode($_POST['horarios'], true);
        }

        $tecnico = $this->service->crearTecnico([
            'nombre'            => $nombre,
            'apellido'          => $apellido,
            'telefono'          => $_POST['telefono'] ?? null,
            'email'             => $_POST['email']    ?? null,
            'duracion_turno_min'=> (int)$duracionTurnoMin,
            'imagen_url'        => $imagenUrl,
            'horarios'          => $horariosList,
        ]);

        Response::success($tecnico, 201);
    }

    // PUT /api/v1/tecnicos/{id}
    // Python: multipart igual que POST
    public function update(string $id): never
    {
        Deps::requireRoles(['admin']);

        // Subir nueva imagen solo si viene
        $imagenUrl = null;
        if (!empty($_FILES['imagen']['tmp_name'])) {
            $imagenUrl = CloudinaryUpload::upload($_FILES['imagen']);
        }

        // Parsear horarios JSON string
        $horariosList = null;
        if (isset($_POST['horarios'])) {
            $horariosList = json_decode($_POST['horarios'], true);
        }

        $data = [];

        // Solo incluir campos que vienen en el form (igual que exclude_unset=True en Python)
        foreach (['nombre', 'apellido', 'telefono', 'email', 'duracion_turno_min'] as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $field === 'duracion_turno_min'
                    ? (int)$_POST[$field]
                    : $_POST[$field];
            }
        }

        if ($imagenUrl) {
            $data['imagen_url'] = $imagenUrl;
        }

        if ($horariosList !== null) {
            $data['horarios'] = $horariosList;
        }

        $updated = $this->service->actualizar($id, $data);
        Response::success($updated);
    }

    // DELETE /api/v1/tecnicos/{id}
    // Python: soft delete → activo = False
    public function destroy(string $id): never
    {
        Deps::requireRoles(['admin']);
        $result = $this->service->eliminar($id);
        Response::success($result);
    }
}