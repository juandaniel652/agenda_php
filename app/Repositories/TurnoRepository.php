<?php
// ─────────────────────────────────────────────
//  app/repositories/TurnoRepository.php
//
//  CORRECCIONES:
//  - Estado usa mayúscula: "Cancelado", "Abierto"  (igual que EstadoTurnoEnum en Python)
//  - cancelar() hace soft-delete con cancelado_en  (igual que TurnoService.eliminar())
//  - create() incluye numero_ticket, tipo_turno, rango_horario (campos del TurnoCreate)
// ─────────────────────────────────────────────

namespace App\Repositories;

use App\Db\Session;
use PDO;

class TurnoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Session::getConnection();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT t.*,
                   c.numero_cliente AS cliente_numero_cliente,
                   c.nombre         AS cliente_nombre,
                   tc.nombre        AS tecnico_nombre,
                   tc.activo        AS tecnico_activo
            FROM turnos t
            LEFT JOIN clientes  c  ON t.cliente_id = c.id
            LEFT JOIN tecnicos  tc ON t.tecnico_id  = tc.id
            WHERE t.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->formatRow($row) : null;
    }

    public function findAll(array $filters = [], int $skip = 0, int $limit = 100): array
    {
        $sql = '
            SELECT t.*,
                   c.numero_cliente AS cliente_numero_cliente,
                   c.nombre         AS cliente_nombre,
                   tc.nombre        AS tecnico_nombre,
                   tc.activo        AS tecnico_activo
            FROM turnos t
            LEFT JOIN clientes  c  ON t.cliente_id = c.id
            LEFT JOIN tecnicos  tc ON t.tecnico_id  = tc.id
            -- Solo técnicos activos (igual que Python: .where(TecnicoModel.activo == True))
            WHERE tc.activo = 1
        ';
        $values = [];

        if (!empty($filters['fecha'])) {
            $sql      .= ' AND t.fecha = ?';
            $values[]  = $filters['fecha'];
        }

        // Excluir cancelados por defecto (igual que Python: estado != "Cancelado")
        $sql     .= ' AND t.estado != ?';
        $values[] = 'Cancelado';

        $sql     .= ' ORDER BY t.fecha DESC, t.hora_inicio DESC LIMIT ? OFFSET ?';
        $values[] = $limit;
        $values[] = $skip;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return array_map([$this, 'formatRow'], $stmt->fetchAll());
    }

    /**
     * Turnos de un técnico en una fecha, excluyendo Cancelados.
     * Usado por TurnoService para detectar conflictos.
     */
    public function findByTecnicoAndFecha(string $tecnicoId, string $fecha): array
    {
        $stmt = $this->db->prepare('
            SELECT hora_inicio, hora_fin FROM turnos
            WHERE tecnico_id = ? AND fecha = ? AND estado != ?
            ORDER BY hora_inicio
        ');
        $stmt->execute([$tecnicoId, $fecha, 'Cancelado']);
        return $stmt->fetchAll();
    }

    /**
     * Verifica solapamiento de horario.
     * Python: hora_inicio < turno_data.hora_fin AND hora_fin > turno_data.hora_inicio
     */
    public function existeSolapamiento(
        string  $tecnicoId,
        string  $fecha,
        string  $horaInicio,
        string  $horaFin,
        ?string $excludeId = null
    ): bool {
        $sql  = '
            SELECT COUNT(*) FROM turnos
            WHERE tecnico_id = ? AND fecha = ? AND estado != ?
              AND hora_inicio < ? AND hora_fin > ?
        ';
        $args = [$tecnicoId, $fecha, 'Cancelado', $horaFin, $horaInicio];

        if ($excludeId) {
            $sql   .= ' AND id != ?';
            $args[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crear turno.
     * Incluye todos los campos de TurnoCreate: numero_ticket, tipo_turno, rango_horario, estado
     */
    public function create(array $data): array
    {
        $id  = $this->generateUuid();
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO turnos
                (id, numero_ticket, cliente_id, tecnico_id, tipo_turno, rango_horario,
                 estado, fecha, hora_inicio, hora_fin, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $id,
            $data['numero_ticket']  ?? $this->generateTicket(),
            $data['cliente_id'],
            $data['tecnico_id'],
            $data['tipo_turno']     ?? 1,
            $data['rango_horario']  ?? null,
            $data['estado']         ?? 'Abierto',   // ← mayúscula, igual que EstadoTurnoEnum
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin'],
            $now,
        ]);

        return $this->findById($id);
    }

    /**
     * Soft delete: estado = "Cancelado" + cancelado_en = now()
     * Equivalente a TurnoService.eliminar() en Python
     */
    public function cancelar(string $id): array
    {
        $stmt = $this->db->prepare('
            UPDATE turnos SET estado = ?, cancelado_en = ? WHERE id = ?
        ');
        $stmt->execute(['Cancelado', date('Y-m-d H:i:s'), $id]);
        return $this->findById($id);
    }

    /**
     * Actualizar estado manualmente (endpoint PATCH /{id}/estado)
     */
    public function updateEstado(string $id, string $estado): array
    {
        $stmt = $this->db->prepare('UPDATE turnos SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);
        return $this->findById($id);
    }

    // ── Helpers ───────────────────────────────

    /**
     * Formatea una fila plana en la estructura anidada que devuelve Python:
     * { ...turno, cliente: {id, numero_cliente, nombre}, tecnico: {id, nombre, activo} }
     */
    private function formatRow(array $row): array
    {
        return [
            'id'            => $row['id'],
            'numero_ticket' => $row['numero_ticket'],
            'tipo_turno'    => (int)$row['tipo_turno'],
            'rango_horario' => $row['rango_horario'],
            'estado'        => $row['estado'],
            'fecha'         => $row['fecha'],
            'hora_inicio'   => $row['hora_inicio'],
            'hora_fin'      => $row['hora_fin'],
            'cancelado_en'  => $row['cancelado_en']  ?? null,
            'created_at'    => $row['created_at'],
            'cliente' => [
                'id'             => $row['cliente_id'],
                'numero_cliente' => $row['cliente_numero_cliente'] ?? null,
                'nombre'         => $row['cliente_nombre']         ?? null,
            ],
            'tecnico' => [
                'id'     => $row['tecnico_id'],
                'nombre' => $row['tecnico_nombre'] ?? null,
                'activo' => (bool)($row['tecnico_activo'] ?? true),
            ],
        ];
    }

    private function generateTicket(): string
    {
        return 'TK-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}