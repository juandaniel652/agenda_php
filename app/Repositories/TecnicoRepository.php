<?php
// ─────────────────────────────────────────────
//  app/repositories/TecnicoRepository.php
//
//  Métodos nuevos respecto a versión anterior:
//  - createConHorarios()       → atómico con horarios opcionales
//  - updateConHorarios()       → reemplaza horarios si vienen
//  - softDelete()              → activo = 0, no DELETE
//  - findAllActivosConHorarios() → JOIN con disponibilidad
//  - getDisponibilidadPorDia() → filtra por dia_semana (convención JS)
// ─────────────────────────────────────────────

namespace App\Repositories;

use App\Db\Session;
use PDO;

class TecnicoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Session::getConnection();
    }

    // ── Lectura ───────────────────────────────

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tecnicos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Equivalente a listar() en TecnicoService:
     * Solo activos, con sus horarios cargados.
     * Python usaba joinedload(Tecnico.horarios)
     */
    public function findAllActivosConHorarios(): array
    {
        // Traer todos los técnicos activos
        $stmt = $this->db->prepare(
            'SELECT * FROM tecnicos WHERE activo = 1 ORDER BY apellido, nombre'
        );
        $stmt->execute();
        $tecnicos = $stmt->fetchAll();

        if (empty($tecnicos)) {
            return [];
        }

        // Traer todos sus horarios en una sola query
        $ids         = array_column($tecnicos, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt2       = $this->db->prepare(
            "SELECT * FROM tecnico_disponibilidad WHERE tecnico_id IN ({$placeholders}) ORDER BY dia_semana, hora_inicio"
        );
        $stmt2->execute($ids);
        $horarios = $stmt2->fetchAll();

        // Agrupar horarios por tecnico_id
        $horariosPorTecnico = [];
        foreach ($horarios as $h) {
            $horariosPorTecnico[$h['tecnico_id']][] = $h;
        }

        // Inyectar horarios en cada técnico
        foreach ($tecnicos as &$t) {
            $t['horarios'] = $horariosPorTecnico[$t['id']] ?? [];
        }

        return $tecnicos;
    }

    /**
     * Disponibilidad de un técnico filtrada por día de la semana.
     * dia_semana usa convención JS: 0=dom, 1=lun … 6=sab
     */
    public function getDisponibilidadPorDia(string $tecnicoId, int $diaSemana): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tecnico_disponibilidad
             WHERE tecnico_id = ? AND dia_semana = ?
             ORDER BY hora_inicio'
        );
        $stmt->execute([$tecnicoId, $diaSemana]);
        return $stmt->fetchAll();
    }

    /** Toda la disponibilidad de un técnico (sin filtrar día) */
    public function getDisponibilidad(string $tecnicoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tecnico_disponibilidad WHERE tecnico_id = ? ORDER BY dia_semana, hora_inicio'
        );
        $stmt->execute([$tecnicoId]);
        return $stmt->fetchAll();
    }

    // ── Escritura ─────────────────────────────

    /**
     * Equivalente a crear_tecnico() en TecnicoService:
     * Crea técnico + horarios en una sola transacción.
     * $horarios es null (sin horarios) o array de {dia_semana, hora_inicio, hora_fin}
     */
    public function createConHorarios(array $data, ?array $horarios): array
    {
        $this->db->beginTransaction();

        try {
            $id  = $this->generateUuid();
            $now = date('Y-m-d H:i:s');

            $stmt = $this->db->prepare('
                INSERT INTO tecnicos (id, nombre, apellido, email, telefono, imagen_url, activo, duracion_turno_min, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $id,
                $data['nombre'],
                $data['apellido'],
                strtolower(trim($data['email'] ?? '')),
                $data['telefono']           ?? null,
                $data['imagen_url']         ?? null,
                1,
                $data['duracion_turno_min'] ?? 30,
                $now,
            ]);

            // Insertar horarios opcionales (db.flush() + loop en Python)
            if (!empty($horarios)) {
                $this->insertHorarios($id, $horarios);
            }

            $this->db->commit();

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->findById($id);
    }

    /**
     * Equivalente a actualizar() en TecnicoService:
     * Actualiza campos básicos. Si $horarios !== null → BORRA y recrea.
     */
    public function updateConHorarios(string $id, array $data, ?array $horarios): array
    {
        $this->db->beginTransaction();

        try {
            // Actualizar campos básicos si vienen
            if (!empty($data)) {
                $fields  = [];
                $values  = [];
                $allowed = ['nombre', 'apellido', 'email', 'telefono', 'imagen_url', 'activo', 'duracion_turno_min'];

                foreach ($allowed as $field) {
                    if (array_key_exists($field, $data) && $data[$field] !== null) {
                        $fields[] = "{$field} = ?";
                        $values[] = $field === 'email' ? strtolower(trim($data[$field])) : $data[$field];
                    }
                }

                if (!empty($fields)) {
                    $values[] = $id;
                    $this->db->prepare('UPDATE tecnicos SET ' . implode(', ', $fields) . ' WHERE id = ?')
                             ->execute($values);
                }
            }

            // Si vienen horarios → REEMPLAZAR (igual que Python: delete + insert)
            if ($horarios !== null) {
                $this->db->prepare('DELETE FROM tecnico_disponibilidad WHERE tecnico_id = ?')
                         ->execute([$id]);

                if (!empty($horarios)) {
                    $this->insertHorarios($id, $horarios);
                }
            }

            $this->db->commit();

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->findById($id);
    }

    /**
     * Equivalente a eliminar() en TecnicoService:
     * SOFT DELETE — activo = 0
     */
    public function softDelete(string $id): void
    {
        $this->db->prepare('UPDATE tecnicos SET activo = 0 WHERE id = ?')
                 ->execute([$id]);
    }

    // ── Helpers privados ──────────────────────

    private function insertHorarios(string $tecnicoId, array $horarios): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO tecnico_disponibilidad (id, tecnico_id, dia_semana, hora_inicio, hora_fin)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($horarios as $h) {
            $stmt->execute([
                $this->generateUuid(),
                $tecnicoId,
                $h['dia_semana'],
                $h['hora_inicio'],
                $h['hora_fin'],
            ]);
        }
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}