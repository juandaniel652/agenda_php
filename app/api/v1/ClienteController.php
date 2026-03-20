<?php
// ─────────────────────────────────────────────
//  app/api/v1/ClienteController.php
//  Espejo de app/api/v1/cliente.py
// ─────────────────────────────────────────────

namespace App\Api\V1;

use App\Api\Deps;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\ClienteRepository;

class ClienteController
{
    private ClienteRepository $repo;

    public function __construct()
    {
        $this->repo = new ClienteRepository();
    }

    // GET /api/v1/clientes
    public function index(): never
    {
        Deps::getCurrentUser();

        $skip  = max(0, (int)($_GET['skip']  ?? 0));
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));

        Response::success($this->repo->findAll($skip, $limit));
    }

    // GET /api/v1/clientes/search?q=...
    public function search(): never
    {
        Deps::getCurrentUser();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            Response::error("El parámetro 'q' debe tener al menos 2 caracteres", 422);
        }

        Response::success($this->repo->search($q));
    }

    // GET /api/v1/clientes/{id}
    public function show(string $id): never
    {
        Deps::getCurrentUser();

        $cliente = $this->repo->findById($id);
        if (!$cliente) {
            Response::notFound('Cliente no encontrado');
        }

        Response::success($cliente);
    }

    // POST /api/v1/clientes
    public function store(): never
    {
        Deps::getCurrentUser();

        // Todos los campos son nullable=False en el modelo Python (cliente.py)
        $data = Validator::fromBody()
            ->required('numero_cliente')
            ->required('nombre')
            ->required('apellido')
            ->required('telefono')
            ->required('domicilio')
            ->required('numero_domicilio')
            ->required('email')
            ->string('numero_cliente', 1, 50)
            ->string('nombre', 1, 100)
            ->string('apellido', 1, 100)
            ->string('telefono', 1, 50)
            ->string('domicilio', 1, 255)
            ->integer('numero_domicilio')
            ->email('email')
            ->validate();

        $cliente = $this->repo->create($data);
        Response::success($cliente, 201);
    }

    // PUT /api/v1/clientes/{id}
    public function update(string $id): never
    {
        Deps::getCurrentUser();

        $cliente = $this->repo->findById($id);
        if (!$cliente) {
            Response::notFound('Cliente no encontrado');
        }

        $raw  = json_decode(file_get_contents('php://input'), true) ?? [];
        $v    = new Validator($raw);

        if (isset($raw['email'])) {
            $v->email('email');
        }
        if (isset($raw['nombre'])) {
            $v->string('nombre', 2, 100);
        }
        if (isset($raw['apellido'])) {
            $v->string('apellido', 2, 100);
        }

        $data = $v->validate();

        // Verificar unicidad de email si cambió
        if (!empty($data['email']) && $data['email'] !== $cliente['email']) {
            if ($this->repo->findByEmail($data['email'])) {
                Response::error('El email ya está registrado en otro cliente', 409);
            }
        }

        $updated = $this->repo->update($id, $data);
        Response::success($updated);
    }

    // DELETE /api/v1/clientes/{id}
    public function destroy(string $id): never
    {
        Deps::requireAdmin();

        if (!$this->repo->findById($id)) {
            Response::notFound('Cliente no encontrado');
        }

        $this->repo->delete($id);
        Response::success(['message' => 'Cliente eliminado'], 200);
    }
}