<?php
// ─────────────────────────────────────────────
//  app/repositories/ClienteRepository.php
// ─────────────────────────────────────────────

namespace App\Repositories;

use App\Db\Session;
use PDO;

class ClienteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Session::getConnection();
    }

    public function findAll(int $skip = 0, int $limit = 100): array
    {
        // Añadimos: WHERE activo = 1
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE activo = 1 ORDER BY apellido, nombre LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $skip]);
        return $stmt->fetchAll();
    }

    public function findById(string $id): ?array
    {
        // Añadimos: AND activo = 1
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function findByNumeroCliente(string $numero): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE numero_cliente = ? LIMIT 1');
        $stmt->execute([$numero]);
        return $stmt->fetch() ?: null;
    }

    public function search(string $query): array
    {
        $q    = '%' . $query . '%';
        $stmt = $this->db->prepare('
            SELECT * FROM clientes
            WHERE (nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR numero_cliente LIKE ? OR telefono LIKE ?)
            AND activo = 1
            ORDER BY apellido, nombre
            LIMIT 50
        ');
        $stmt->execute([$q, $q, $q, $q, $q]);
        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $id = $this->generateUuid();

        $stmt = $this->db->prepare('
            INSERT INTO clientes (id, nombre, apellido, email, telefono, domicilio, numero_domicilio, numero_cliente)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $id,
            $data['nombre'],
            $data['apellido'],
            strtolower(trim($data['email'] ?? '')),
            $data['telefono']         ?? null,
            $data['domicilio']        ?? null,
            $data['numero_domicilio'] ?? null,
            $data['numero_cliente']   ?? null,
        ]);

        return $this->findById($id);
    }

    public function update(string $id, array $data): ?array
    {
        $fields = [];
        $values = [];

        $allowed = ['nombre', 'apellido', 'email', 'telefono', 'domicilio', 'numero_domicilio', 'numero_cliente'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $field === 'email' ? strtolower(trim($data[$field])) : $data[$field];
            }
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $values[] = $id;
        $sql  = 'UPDATE clientes SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return $this->findById($id);
    }

    public function delete($id) {
        // Solo cambiamos el estado, no borramos la fila
        $stmt = $this->db->prepare("UPDATE clientes SET activo = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
