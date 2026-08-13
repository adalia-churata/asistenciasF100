<?php
/**
 * exports/ExportController.php
 * Genera reportes CSV asociando las jornadas estrictamente a la fecha de su INGRESO
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/Database.php';

class ExportController
{
    public static function export(string $tipo): never
    {
        match($tipo) {
            'comedor'    => self::exportComedor(),
            'asistencia' => self::exportAsistencia(),
            default      => Response::error("Tipo de exportación no válido: $tipo"),
        };
    }

    private static function csvHeaders(string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        // BOM para que Excel reconozca codificación UTF-8
        echo "\xEF\xBB\xBF";
    }

    private static function exportComedor(): never
    {
        $desde   = $_GET['desde']      ?? date('Y-m-d');
        $hasta   = $_GET['hasta']      ?? date('Y-m-d');
        $area    = $_GET['area']       ?? null;
        $trabId  = $_GET['trabajador'] ?? null;
        $tipo    = $_GET['tipo']       ?? null;
        $persona = $_GET['persona']    ?? 'todos';

        $rows = [];

        if ($persona !== 'visitante') {
            $sql = "SELECT DATE(ep.fecha_hora) AS fecha,
                           ep.tipo_evento, 'TRABAJADOR' AS tipo_persona,
                           t.nombre_completo AS nombre, t.dni,
                           a.nombre_area, t.empresa
                    FROM eventos_personal ep
                    JOIN trabajadores t ON t.id_trabajador = ep.id_trabajador
                    JOIN areas a ON a.id_area = t.id_area
                    WHERE ep.tipo_evento IN ('DESAYUNO','ALMUERZO','CENA')
                      AND DATE(ep.fecha_hora) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if ($area)   { $sql .= " AND t.id_area = ?";        $params[] = $area; }
            if ($trabId) { $sql .= " AND ep.id_trabajador = ?"; $params[] = $trabId; }
            if ($tipo)   { $sql .= " AND ep.tipo_evento = ?";   $params[] = $tipo; }
            $rows = array_merge($rows, Database::fetchAll($sql, $params));
        }

        if ($persona !== 'trabajador') {
            $sql = "SELECT DATE(cv.fecha_hora) AS fecha,
                           cv.tipo_comida AS tipo_evento, 'VISITANTE' AS tipo_persona,
                           v.nombre, '' AS dni,
                           'Visitante' AS nombre_area, v.empresa
                    FROM consumo_visitantes cv
                    JOIN visitantes v ON v.id_visitante = cv.id_visitante
                    WHERE DATE(cv.fecha_hora) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if ($tipo) { $sql .= " AND cv.tipo_comida = ?"; $params[] = $tipo; }
            $rows = array_merge($rows, Database::fetchAll($sql, $params));
        }

        // Ordenamiento: 1º Fecha -> 2º Área -> 3º Nombre
        usort($rows, function($a, $b) {
            $compFecha = strcmp($a['fecha'], $b['fecha']);
            if ($compFecha !== 0) {
                return $compFecha;
            }
            
            $compArea = strcmp($a['nombre_area'], $b['nombre_area']);
            if ($compArea !== 0) {
                return $compArea;
            }

            return strcmp($a['nombre'], $b['nombre']);
        });

        self::csvHeaders("comedor_{$desde}_{$hasta}.csv");

        $out = fopen('php://output', 'w');
        
        // Encabezado
        fputcsv($out, ['Fecha', 'Área', 'Nombre', 'Tipo Persona', 'Tipo Comida', 'Empresa'], ';');
        
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['fecha'],
                $r['nombre_area'],
                $r['nombre'],
                $r['tipo_persona'],
                $r['tipo_evento'],
                $r['empresa'],
            ], ';');
        }
        
        fclose($out);
        exit;
    }

    public static function exportAsistencia(): never
    {
        $desde  = $_GET['desde'] ?? date('Y-m-d');
        $hasta  = $_GET['hasta'] ?? date('Y-m-d');
        $area   = $_GET['area']   ?? null;
        $trabId = $_GET['trabajador'] ?? null;

        // Ampliamos 1 día hacia adelante para no cortar la salida de la mañana siguiente
        $hastaMasUnDia = date('Y-m-d', strtotime($hasta . ' +1 day'));

        $sql = "SELECT 
                    ep.fecha_hora,
                    ep.tipo_evento,
                    ep.observacion,
                    t.id_trabajador,
                    t.nombre_completo,
                    t.dni,
                    a.nombre_area,
                    t.cargo,
                    t.empresa
                FROM eventos_personal ep
                JOIN trabajadores t ON t.id_trabajador = ep.id_trabajador
                JOIN areas a ON a.id_area = t.id_area
                WHERE ep.tipo_evento IN ('INGRESO', 'SALIDA_BREAK', 'REGRESO_BREAK', 'SALIDA_TRABAJO')
                  AND DATE(ep.fecha_hora) BETWEEN ? AND ?";
                
        $params = [$desde, $hastaMasUnDia];
        if ($area)   { $sql .= " AND t.id_area = ?"; $params[] = $area; }
        if ($trabId) { $sql .= " AND ep.id_trabajador = ?"; $params[] = $trabId; }
        
        $sql .= " ORDER BY t.id_trabajador ASC, ep.fecha_hora ASC";
        $eventos = Database::fetchAll($sql, $params);

        // Agrupación de eventos
        $jornadas = self::procesarEventosEnJornadas($eventos, $desde, $hasta);

        self::csvHeaders("asistencia_{$desde}_{$hasta}.csv");
        $out = fopen('php://output', 'w');
        
        fputcsv($out, [
            'Fecha', 'Turno', 'Trabajador', 'DNI', 'Área', 'Cargo', 'Empresa',
            'Hora Ingreso', 'Salida Break', 'Ingreso Break', 'Salida Trabajo',
            'Horas Netas', 'Diferencia', 'Observaciones'
        ], ';');

        foreach ($jornadas as $j) {
            fputcsv($out, [
                $j['fecha'],
                $j['turno'],
                $j['nombre_completo'],
                $j['dni'],
                $j['nombre_area'],
                $j['cargo'],
                $j['empresa'],
                $j['hora_ingreso'],
                $j['salida_break'],
                $j['ingreso_break'],
                $j['salida_trabajo'],
                $j['horas_netas'],
                $j['diferencia'],
                $j['observacion']
            ], ';');
        }

        fclose($out);
        exit;
    }

    public static function procesarEventosEnJornadas(array $eventos, string $desde, string $hasta): array
    {
        $jornadasProcesadas = [];
        $jornadaActual = null;

        foreach ($eventos as $ev) {
            $idTrab    = $ev['id_trabajador'];
            $tipo      = $ev['tipo_evento'];
            $timestamp = strtotime($ev['fecha_hora']);
            $fechaEv   = date('Y-m-d', $timestamp);

            $esOtroTrabajador = ($jornadaActual && $jornadaActual['id_trabajador'] !== $idTrab);
            $excedeTiempo     = ($jornadaActual && ($timestamp - $jornadaActual['inicio_ts']) > 57600); // 16 hrs max por turno

            // Si es un INGRESO, siempre abre una jornada formal ligada a ESTA fecha de ingreso
            if ($tipo === 'INGRESO') {
                if ($jornadaActual) {
                    $jornadasProcesadas[] = self::calcularTotalesJornada($jornadaActual);
                }
                
                $jornadaActual = [
                    'id_trabajador'   => $idTrab,
                    'fecha'           => $fechaEv, // La fecha de la jornada será la del INGRESO
                    'nombre_completo' => $ev['nombre_completo'],
                    'dni'             => $ev['dni'],
                    'nombre_area'     => $ev['nombre_area'],
                    'cargo'           => $ev['cargo'],
                    'empresa'         => $ev['empresa'],
                    'hora_ingreso'    => date('H:i:s', $timestamp),
                    'salida_break'    => '—',
                    'ingreso_break'   => '—',
                    'salida_trabajo'  => '—',
                    'observacion'     => $ev['observacion'] ?? '',
                    'inicio_ts'       => $timestamp,
                    'INGRESO_dt'      => $ev['fecha_hora'],
                    'SALIDA_TRABAJO_dt'=> null,
                    'SALIDA_BREAK_dt' => null,
                    'REGRESO_BREAK_dt' => null
                ];
                continue;
            }

            // Si NO hay jornada activa (ej. marcas huérfanas de salidas anteriores) o cambió de trabajador/superó tiempo, ignoramos esas marcas sueltas
            if (!$jornadaActual || $esOtroTrabajador || $excedeTiempo) {
                if ($jornadaActual && ($esOtroTrabajador || $excedeTiempo)) {
                    $jornadasProcesadas[] = self::calcularTotalesJornada($jornadaActual);
                    $jornadaActual = null;
                }
                continue;
            }

            // Si llegamos aquí, asignamos la marca (Salida/Break) a la jornada iniciada previamente
            $jornadaActual[$tipo . '_dt'] = $ev['fecha_hora'];
            if (!empty($ev['observacion'])) {
                $jornadaActual['observacion'] = $ev['observacion'];
            }

            $horaCorta = date('H:i:s', $timestamp);
            
            match($tipo) {
                'SALIDA_BREAK'   => $jornadaActual['salida_break']   = $horaCorta,
                'REGRESO_BREAK'  => $jornadaActual['ingreso_break']  = $horaCorta,
                'SALIDA_TRABAJO' => $jornadaActual['salida_trabajo'] = $horaCorta,
                default          => null
            };
        }

        if ($jornadaActual) {
            $jornadasProcesadas[] = self::calcularTotalesJornada($jornadaActual);
        }

        // Filtramos para devolver únicamente las jornadas CUYO INGRESO PERTENECE AL RANGO $desde / $hasta
        return array_values(array_filter($jornadasProcesadas, function($j) use ($desde, $hasta) {
            return $j['fecha'] >= $desde && $j['fecha'] <= $hasta;
        }));
    }

    private static function calcularTotalesJornada(array $j): array
    {
        $horasNetas = 0.00;
        $horasProgramadas = 11.00;

        if ($j['INGRESO_dt'] && $j['SALIDA_TRABAJO_dt']) {
            $minutosTotales = (strtotime($j['SALIDA_TRABAJO_dt']) - strtotime($j['INGRESO_dt'])) / 60;
            $minutosBreak = 0;
            if ($j['SALIDA_BREAK_dt'] && $j['REGRESO_BREAK_dt']) {
                $minutosBreak = (strtotime($j['REGRESO_BREAK_dt']) - strtotime($j['SALIDA_BREAK_dt'])) / 60;
            }
            $horasNetas = round(max(0, $minutosTotales - $minutosBreak) / 60, 2);
        }

        $diferencia = round($horasNetas - $horasProgramadas, 2);
        
        // Determinar Turno según hora de ingreso
        $turno = 'Turno Día';
        if ($j['hora_ingreso'] !== '—') {
            $horaH = (int)explode(':', $j['hora_ingreso'])[0];
            if ($horaH >= 17 || $horaH < 4) {
                $turno = 'Turno Noche';
            }
        }

        $j['turno']       = $turno;
        $j['horas_netas'] = $horasNetas;
        $j['diferencia']  = $diferencia;

        return $j;
    }
}