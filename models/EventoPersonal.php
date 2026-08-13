<?php
/**
 * models/EventoPersonal.php
 * Toda la lógica de negocio de marcaciones (Estructura Real de BD Optimizada)
 */

class EventoPersonal
{
    // ── Tipos ──────────────────────────────────────────────────
    const TIPOS_LABORALES = ['INGRESO', 'SALIDA_BREAK', 'REGRESO_BREAK', 'SALIDA_TRABAJO'];
    const TIPOS_COMEDOR   = ['DESAYUNO', 'ALMUERZO', 'CENA'];

    /**
     * Detecta el tipo de comida según la hora actual
     */
    public static function detectarComida(string $hora = null): string
    {
        $h = $hora ?? date('H:i');
        if ($h >= '05:00' && $h <= '09:59') return 'DESAYUNO';
        if ($h >= '10:00' && $h <= '15:59') return 'ALMUERZO';
        return 'CENA';
    }

    /**
     * Determina el siguiente tipo de evento laboral basándose de forma dinámica 
     */
    public static function siguienteEventoLaboral(int $idTrabajador): string|null
    {
        $listaTipos = self::TIPOS_LABORALES;
        $tiposQuery = "'" . implode("','", $listaTipos) . "'";

        $sql = "SELECT tipo_evento, fecha_hora FROM eventos_personal
                WHERE id_trabajador = ? 
                  AND tipo_evento IN ($tiposQuery)
                ORDER BY fecha_hora DESC LIMIT 1";
                
        $ultimoEvento = Database::fetchOne($sql, array($idTrabajador));

        if (!$ultimoEvento) {
            return 'INGRESO';
        }

        $timeUltimo = strtotime($ultimoEvento['fecha_hora']);
        $horasDesdeUltimo = (time() - $timeUltimo) / 3600;

        // CASO ESPECIAL: Turno Día Corto + Turno Noche el mismo día
        if ($ultimoEvento['tipo_evento'] === 'SALIDA_TRABAJO' && $horasDesdeUltimo > 3) {
            return 'INGRESO';
        }

        // REINICIO GENERAL POR AUSENCIA LARGA
        if ($horasDesdeUltimo > 9) {
            return 'INGRESO';
        }

        // FLEXIBILIDAD EN TURNO NOCHE
        $horaActual = (int)date('H');
        $esHorarioCenaNocturna = ($horaActual >= 23 || $horaActual <= 4);

        if ($esHorarioCenaNocturna) {
            if ($ultimoEvento['tipo_evento'] === 'INGRESO') {
                return 'SALIDA_BREAK';
            }
            if ($ultimoEvento['tipo_evento'] === 'SALIDA_BREAK') {
                return 'REGRESO_BREAK';
            }
        }

        // SECUENCIA ESTÁNDAR COMPATIBLE
        return match($ultimoEvento['tipo_evento']) {
            'INGRESO'        => 'SALIDA_BREAK',
            'SALIDA_BREAK'   => 'REGRESO_BREAK',
            'REGRESO_BREAK'  => 'SALIDA_TRABAJO',
            'SALIDA_TRABAJO' => 'INGRESO',
            default          => 'INGRESO',
        };
    }

    /**
     * Registra un evento para un trabajador
     */
    public static function registrar(int $idTrabajador, string $tipoEvento, string $observacion = ''): array 
    {
        $fechaHora = date('Y-m-d H:i:s');
        $fecha     = date('Y-m-d');

        // 1. Validaciones exclusivas para TIPOS LABORALES
        if (in_array($tipoEvento, self::TIPOS_LABORALES)) {
            
            // 🛑 SHIELD ANTI-DOBLE ESCANEO RÁPIDO (Corregido usando la hora de PHP)
            $listaTipos = self::TIPOS_LABORALES;
            $tiposQuery = "'" . implode("','", $listaTipos) . "'";
            
            $ultimoMovimientoRapido = Database::fetchOne(
                "SELECT tipo_evento, fecha_hora 
                 FROM eventos_personal
                 WHERE id_trabajador = ? 
                   AND tipo_evento IN ($tiposQuery)
                   AND fecha_hora >= ? - INTERVAL 1 MINUTE
                 ORDER BY fecha_hora DESC LIMIT 1",
                [$idTrabajador, $fechaHora]
            );

            if ($ultimoMovimientoRapido) {
                return [
                    'ok'    => false,
                    'error' => "⚠️ ¡Doble escaneo detectado! Procesaste un evento laboral hace unos segundos."
                ];
            }
            
            // ── Validación de secuencia lógica ──
            $esperado = self::siguienteEventoLaboral($idTrabajador);
            if ($esperado !== $tipoEvento) {
                return [
                    'ok'    => false,
                    'error' => 'Secuencia inválida. Se esperaba: ' . self::labelTipo($esperado),
                ];
            }

            // Si se espera un INGRESO, no bloqueamos por "duplicado de fecha calendario" para permitir el turno noche consecutivo
            if ($tipoEvento !== 'INGRESO') {
                $existe = Database::fetchOne(
                    "SELECT id_evento FROM eventos_personal
                     WHERE id_trabajador = ? AND DATE(fecha_hora) = ? AND tipo_evento = ?",
                    [$idTrabajador, $fecha, $tipoEvento]
                );
                if ($existe) {
                    $label = self::labelTipo($tipoEvento);
                    return ['ok' => false, 'error' => "Ya registró $label hoy."];
                }
            }
        }

        // 2. Inserción en la Base de Datos
        Database::query(
            "INSERT INTO eventos_personal (id_trabajador, fecha_hora, tipo_evento, observacion)
             VALUES (?, ?, ?, ?)",
            [$idTrabajador, $fechaHora, $tipoEvento, $observacion ?: null]
        );

        return [
            'ok'    => true,
            'tipo'  => $tipoEvento,
            'label' => self::labelTipo($tipoEvento),
            'hora'  => date('H:i:s'),
        ];
    }

    /**
     * Registra evento de comedor para un visitante
     */
    public static function registrarVisitante(int $idVisitante, string $tipoEvento): array
    {
        $fecha = date('Y-m-d');

        $existe = Database::fetchOne(
            "SELECT id_consumo FROM consumo_visitantes
             WHERE id_visitante = ? AND DATE(fecha_hora) = ? AND tipo_comida = ?",
            [$idVisitante, $fecha, $tipoEvento]
        );
        if ($existe) {
            return ['ok' => false, 'error' => "Visitante ya registró " . self::labelTipo($tipoEvento) . " hoy."];
        }

        Database::query(
            "INSERT INTO consumo_visitantes (id_visitante, fecha_hora, tipo_comida) VALUES (?, NOW(), ?)",
            [$idVisitante, $tipoEvento]
        );

        return ['ok' => true, 'tipo' => $tipoEvento, 'label' => self::labelTipo($tipoEvento), 'hora' => date('H:i:s')];
    }

    /**
     * Calcula horas trabajadas de forma segura (Compatible con SQL estándar si las marcas son limpias)
     */
    public static function calcularHoras(int $idTrabajador, string $fecha): array
    {
        if (!defined('HORAS_PROGRAMADAS_DEFAULT')) {
            define('HORAS_PROGRAMADAS_DEFAULT', 8.0);
        }
        $horasProg = HORAS_PROGRAMADAS_DEFAULT;

        // NOTA: Esta query asume marcas en el mismo día. Para turno noche real cruzado, 
        // se debe buscar por el ID del bloque de jornada.
        $row = Database::fetchOne(
            "SELECT 
                ROUND((
                    IFNULL(TIMESTAMPDIFF(MINUTE, MIN(CASE WHEN tipo_evento = 'INGRESO' THEN fecha_hora END), MAX(CASE WHEN tipo_evento = 'SALIDA_TRABAJO' THEN fecha_hora END)), 0) - 
                    IFNULL(TIMESTAMPDIFF(MINUTE, MIN(CASE WHEN tipo_evento = 'SALIDA_BREAK' THEN fecha_hora END), MAX(CASE WHEN tipo_evento = 'REGRESO_BREAK' THEN fecha_hora END)), 0)
                ) / 60, 2) AS horas_netas
             FROM eventos_personal 
             WHERE id_trabajador = ? AND DATE(fecha_hora) = ?",
            [$idTrabajador, $fecha]
        );

        if (!$row || $row['horas_netas'] === null || $row['horas_netas'] == 0) {
            return [
                'horas_trabajadas' => null,
                'horas_programadas' => $horasProg,
                'diferencia' => null,
                'tipo_diferencia' => null,
            ];
        }

        $netasH     = (float)$row['horas_netas'];
        $diferencia = $netasH - $horasProg;

        return [
            'horas_trabajadas'  => $netasH,
            'horas_programadas' => $horasProg,
            'diferencia'        => abs(round($diferencia, 2)),
            'tipo_diferencia'   => $diferencia >= 0 ? 'extra' : 'deficitaria',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────
    public static function labelTipo(string $tipo): string
    {
        return match($tipo) {
            'INGRESO'        => 'Ingreso',
            'SALIDA_BREAK'   => 'Salida a break',
            'REGRESO_BREAK'  => 'Retorno de break',
            'SALIDA_TRABAJO' => 'Salida de trabajo',
            'DESAYUNO'       => 'Desayuno',
            'ALMUERZO'       => 'Almuerzo',
            'CENA'           => 'Cena',
            default          => $tipo,
        };
    }

    public static function iconoTipo(string $tipo): string
    {
        return match($tipo) {
            'INGRESO'        => '🟢',
            'SALIDA_BREAK'   => '🟡',
            'REGRESO_BREAK'  => '🔵',
            'SALIDA_TRABAJO' => '🔴',
            'DESAYUNO'       => '☕',
            'ALMUERZO'       => '🍽️',
            'CENA'           => '🌙',
            default          => '📌',
        };
    }
}