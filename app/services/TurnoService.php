<?php
// ─────────────────────────────────────────────
//  app/services/TurnoService.php
//  Espejo EXACTO de app/services/turno_service.py
//
//  CORRECCIONES vs versión anterior:
//  1. Estado usa mayúscula: "Cancelado", "Abierto"
//  2. obtener_disponibilidad: conversión dia_semana Python→JS igual que Python
//  3. +obtener_sugerencias()
//  4. eliminar() hace soft-delete: estado="Cancelado" + cancelado_en
// ─────────────────────────────────────────────

namespace App\Services;

use App\Repositories\TurnoRepository;
use App\Repositories\TecnicoRepository;
use App\Core\Response;

class TurnoService
{
    private TurnoRepository   $turnoRepo;
    private TecnicoRepository $tecnicoRepo;

    public function __construct()
    {
        $this->turnoRepo   = new TurnoRepository();
        $this->tecnicoRepo = new TecnicoRepository();
    }

    /**
     * Equivalente a crear(db, turno_data) en turno_service.py
     *
     * Valida conflicto de solapamiento antes de insertar.
     * El Python filtra: estado != "Cancelado"  (con mayúscula)
     */
    public function crear(array $data): array
    {
        $conflicto = $this->turnoRepo->existeSolapamiento(
            $data['tecnico_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin']
        );

        if ($conflicto) {
            Response::error('El técnico ya tiene un turno en ese horario', 400);
        }

        return $this->turnoRepo->create($data);
    }

    /**
     * Equivalente a obtener_disponibilidad(db, tecnico_id, fecha, t=1)
     *
     * Conversión dia_semana igual que Python:
     *   Python weekday(): 0=lun … 6=dom
     *   Frontend JS:      0=dom, 1=lun … 6=sab
     *   → dia_front = (dia_python + 1) % 7
     *
     * El campo dia_semana en DB está guardado con convención JS (front).
     */
    public function obtenerDisponibilidad(string $tecnicoId, string $fecha, int $t = 1): array
    {
        $tecnico = $this->tecnicoRepo->findById($tecnicoId);
        if (!$tecnico || !$tecnico['activo']) {
            return [];
        }

        // Misma conversión que Python:
        // fecha->weekday() equivale a PHP (N)-1 donde N: 1=Lun,7=Dom
        $diaPython = (int)(new \DateTime($fecha))->format('N') - 1; // 0=lun…6=dom
        $diaFront  = ($diaPython + 1) % 7;                          // 0=dom,1=lun…6=sab

        $disponibilidades = $this->tecnicoRepo->getDisponibilidadPorDia($tecnicoId, $diaFront);

        if (empty($disponibilidades)) {
            return [];
        }

        $duracion      = (int)$tecnico['duracion_turno_min'];
        $turnosOcupados = $this->turnoRepo->findByTecnicoAndFecha($tecnicoId, $fecha);

        $slots = [];

        foreach ($disponibilidades as $disp) {
            $inicio = new \DateTime($fecha . ' ' . $disp['hora_inicio']);
            $fin    = new \DateTime($fecha . ' ' . $disp['hora_fin']);

            while (true) {
                $slotFin = clone $inicio;
                $slotFin->modify("+{$duracion} minutes");

                if ($slotFin > $fin) break;

                $slotIniTime = $inicio->format('H:i:s');
                $slotFinTime = $slotFin->format('H:i:s');

                // Misma lógica que Python:
                // conflicto = any(t.hora_inicio < slot_fin and t.hora_fin > slot_inicio)
                $conflicto = false;
                foreach ($turnosOcupados as $turno) {
                    if ($turno['hora_inicio'] < $slotFinTime && $turno['hora_fin'] > $slotIniTime) {
                        $conflicto = true;
                        break;
                    }
                }

                if (!$conflicto) {
                    // Python devuelve: slot_inicio.strftime("%H:%M")
                    $slots[] = $inicio->format('H:i');
                }

                $inicio = $slotFin;
            }
        }

        // Filtro de slots consecutivos (para tipo_turno t > 1)
        // Equivalente al bucle slots_consecutivos en Python
        if ($t <= 1) {
            return $slots;
        }

        $slotsConsecutivos = [];
        for ($i = 0; $i <= count($slots) - $t; $i++) {
            $secuencia    = array_slice($slots, $i, $t);
            $consecutivos = true;

            for ($j = 1; $j < $t; $j++) {
                [$h1, $m1] = explode(':', $secuencia[$j - 1]);
                [$h2, $m2] = explode(':', $secuencia[$j]);
                $diff = ((int)$h2 * 60 + (int)$m2) - ((int)$h1 * 60 + (int)$m1);
                if ($diff !== $duracion) {
                    $consecutivos = false;
                    break;
                }
            }

            if ($consecutivos) {
                $slotsConsecutivos[] = $secuencia[0];
            }
        }

        return $slotsConsecutivos;
    }

    /**
     * Equivalente a obtener_sugerencias(db, tecnico_id, dias=3, t=1)
     *
     * Busca los próximos $dias días (hasta 30) con slots disponibles.
     */
    public function obtenerSugerencias(string $tecnicoId, int $dias = 3, int $t = 1): array
    {
        $tecnico = $this->tecnicoRepo->findById($tecnicoId);
        if (!$tecnico) {
            return [];
        }

        $hoy       = new \DateTime('today');
        $resultados = [];

        for ($i = 1; $i <= 30; $i++) {
            $fecha = clone $hoy;
            $fecha->modify("+{$i} days");
            $fechaStr = $fecha->format('Y-m-d');

            $slots = $this->obtenerDisponibilidad($tecnicoId, $fechaStr, $t);

            if (!empty($slots)) {
                $resultados[] = [
                    'fecha' => $fechaStr,
                    'slots' => $slots,
                ];
            }

            if (count($resultados) === $dias) {
                break;
            }
        }

        return $resultados;
    }

    /**
     * Equivalente a eliminar(db, turno_id) en turno_service.py
     *
     * SOFT DELETE: estado = "Cancelado" + cancelado_en = now()
     * No borra el registro de la DB.
     */
    public function eliminar(string $turnoId): array
    {
        $turno = $this->turnoRepo->findById($turnoId);

        if (!$turno) {
            Response::notFound('Turno no encontrado');
        }

        return $this->turnoRepo->cancelar($turnoId);
    }
}