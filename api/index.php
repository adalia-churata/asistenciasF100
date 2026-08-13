<?php
/**
 * api/index.php — v2.0
 * Router REST unificado (trabajadores + visitantes)
 *
 * VISITANTES:
 *   GET  /api/visitantes              — listado con búsqueda
 *   GET  /api/visitantes/buscar?q=   — autocomplete (≥2 chars)
 *   GET  /api/visitantes/{id}         — detalle + estado comedor hoy
 *   POST /api/visitantes              — crear visitante
 *   POST /api/visitantes/evento       — registrar evento a visitante existente
 *   POST /api/visitantes/crear-y-registrar — nuevo visitante + evento en 1 paso
 *   PUT  /api/visitantes/{id}         — editar
 *   GET  /api/visitantes/{id}/historial
 */

// 1. Configuración de errores para APIs (evita que los warnings contaminen el JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// 2. Cabeceras CORS compatibles con InfinityFree y clientes AJAX
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// Manejo de la petición Preflight de los navegadores
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // 200 es más compatible que 204 en algunos hostings compartidos
    exit();
}

// 3. Carga de dependencias
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/models/EventoPersonal.php';
require_once dirname(__DIR__) . '/models/Visitante.php';

// 4. Captura de parámetros y cuerpo de la petición
$resource = $_GET['action'] ?? '';
$subRes   = $_GET['sub'] ?? '';
$subSub   = $_GET['sub2'] ?? '';
$id       = is_numeric($subRes) ? (int)$subRes : null;

$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

// Helper para Query Parameters
function qp(string $k, mixed $d = null): mixed {
    return isset($_GET[$k]) && $_GET[$k] !== '' ? $_GET[$k] : $d;
}

// ═══════════════════════════════════════════════════════════════
//  POST /api/scan   (trabajador o visitante por QR)
// ═══════════════════════════════════════════════════════════════
if ($resource === 'scan' && $method === 'POST') {
    $qr     = trim($body['qr'] ?? '');
    $modulo = $body['modulo'] ?? 'auto';

    if ($qr === '') Response::error('QR vacío');

    // 1. Limpiamos el texto para verificar si es un DNI (Solo números)
    $dni  = preg_replace('/\D/', '', $qr);
    $trab = null;

    // Si tiene longitud potencial de DNI (7 u 8 dígitos), buscamos primero en trabajadores
    if (strlen($dni) >= 7 && strlen($dni) <= 8) {
        $trab = Database::fetchOne(
            "SELECT t.*, a.nombre_area FROM trabajadores t
             JOIN areas a ON a.id_area = t.id_area
             WHERE t.dni = ? AND t.activo = 1",
            [$dni]
        );
    }

    // ── CASO A: SI ES TRABAJADOR (Prioridad Absoluta) ─────────────────────────
if ($trab) {
    $id_t = (int)$trab['id_trabajador'];

    if ($modulo === 'comedor') {
        // 🟢 1. Detectar tipo de comida actual (DESAYUNO, ALMUERZO, CENA)
        $tipo = EventoPersonal::detectarComida();

        // 🟢 2. VALIDACIÓN ANTI-DUPLICADOS EN COMEDOR
        $yaComio = Database::fetchOne(
            "SELECT DATE_FORMAT(fecha_hora, '%H:%i:%s') as hora_registro 
             FROM eventos_personal
             WHERE id_trabajador = ? 
               AND DATE(fecha_hora) = ? 
               AND tipo_evento = ?",
            [$id_t, date('Y-m-d'), $tipo]
        );

        if ($yaComio) {
            Response::conflict("⚠️ DUPLICADO: Ya se registró tu {$tipo} de hoy a las " . $yaComio['hora_registro']);
        }

        $result = EventoPersonal::registrar($id_t, $tipo);

    } elseif ($modulo === 'laboral') {
        $tipo = EventoPersonal::siguienteEventoLaboral($id_t);
        if (!$tipo) Response::conflict('Jornada laboral ya completada hoy.');
        $result = EventoPersonal::registrar($id_t, $tipo);

    } else {
        // ── MÓDULO AUTOMÁTICO INTELIGENTE (Prioridad Asistencia + Cooldown) ──
        $siguienteLaboral = EventoPersonal::siguienteEventoLaboral($id_t);

        // Si el sistema determina que el siguiente paso lógico es marcar su SALIDA definitiva:
        if ($siguienteLaboral === 'SALIDA_TRABAJO') {
            
            // ⚠️ BLINDAJE DE TIEMPO: Validamos si el REGRESO_BREAK ocurrió hace menos de 5 minutos
            $antiduplicado = Database::fetchOne(
                "SELECT fecha_hora FROM eventos_personal
                 WHERE id_trabajador = ? AND DATE(fecha_hora) = ? AND tipo_evento = 'REGRESO_BREAK'
                   AND fecha_hora >= NOW() - INTERVAL 5 MINUTE
                 ORDER BY fecha_hora DESC LIMIT 1",
                [$id_t, date('Y-m-d')]
            );

            if ($antiduplicado) {
                Response::conflict('⚠️ ¡Escaneo rápido! Acabas de registrar tu Retorno de Break hace menos de 5 minutos. Espera un momento para marcar tu salida.');
            }

            // Si ya pasaron los 5 minutos reglamentarios, forzamos el registro de la Salida de Trabajo
            $tipo   = 'SALIDA_TRABAJO';
            $result = EventoPersonal::registrar($id_t, $tipo);
            
        } else {
            // Si no le toca marcar salida definitiva, procedemos con el flujo normal de comida
            $tipoComedor = EventoPersonal::detectarComida();
            $yaComedor   = Database::fetchOne(
                "SELECT DATE_FORMAT(fecha_hora, '%H:%i:%s') as hora_registro 
                 FROM eventos_personal
                 WHERE id_trabajador=? AND DATE(fecha_hora)=? AND tipo_evento=?",
                [$id_t, date('Y-m-d'), $tipoComedor]
            );

            if (!$yaComedor) {
                $tipo   = $tipoComedor;
                $result = EventoPersonal::registrar($id_t, $tipo);
            } else {
                if (!$siguienteLaboral) {
                    Response::conflict("⚠️ DUPLICADO: Tu {$tipoComedor} ya fue registrado a las " . $yaComedor['hora_registro'] . " y tu jornada laboral ya está completada.");
                }
                $tipo   = $siguienteLaboral;
                $result = EventoPersonal::registrar($id_t, $tipo);
            }
        }
    }

    if (!$result['ok']) Response::conflict($result['error']);

    $horas = null;
    if ($result['tipo'] === 'SALIDA_TRABAJO') {
        $horas = EventoPersonal::calcularHoras($id_t, date('Y-m-d'));
    }

    Response::success([
        'persona'       => $trab['nombre_completo'],
        'tipo'          => 'trabajador',
        'dni'           => $trab['dni'],
        'area'          => $trab['nombre_area'],
        'cargo'         => $trab['cargo'],
        'empresa'       => $trab['empresa'],
        'evento'        => $result['label'],
        'tipo_raw'      => $result['tipo'],
        'hora'          => $result['hora'],
        'horas_resumen' => $horas,
    ], '✅ ' . $result['label'] . ' registrado');
}
    // ── CASO B: SI NO ES TRABAJADOR, EVALUAMOS SI ES VISITANTE (ID corto 1-6 dígitos) ──
    if (preg_match('/^\d{1,6}$/', $qr)) {
        $vis = Visitante::getPorId((int)$qr);
        if (!$vis) Response::notFound('Visitante no encontrado (ID: ' . (int)$qr . ')');

        $tipo   = EventoPersonal::detectarComida();
        $result = Visitante::registrarEvento((int)$qr, $tipo, 'QR');

        if (!$result['ok']) Response::conflict($result['error']);

        Response::success([
            'persona'      => $vis['nombre'],
            'tipo'         => 'visitante',
            'empresa'      => $vis['empresa'],
            'evento'       => $result['label'],
            'tipo_raw'     => $result['tipo'],
            'hora'         => $result['hora'],
            'tuvo_desayuno'=> (bool)$vis['tuvo_desayuno'],
            'tuvo_almuerzo'=> (bool)$vis['tuvo_almuerzo'],
            'tuvo_cena'    => (bool)$vis['tuvo_cena'],
        ], '✅ ' . $result['label'] . ' registrado');
    }

    // Fallback si no cumple ninguna condición
    Response::notFound("Código QR no reconocido o persona no registrada");
}

// ═══════════════════════════════════════════════════════════════
//  GET /api/dashboard
// ═══════════════════════════════════════════════════════════════
if ($resource === 'dashboard' && $method === 'GET') {
    $fecha = qp('fecha', date('Y-m-d'));

    // 1. Contadores del comedor para Trabajadores (eventos_personal)
    $comidaTrab = Database::fetchOne(
        "SELECT 
            SUM(CASE WHEN tipo_evento = 'DESAYUNO' THEN 1 ELSE 0 END) as desayunos,
            SUM(CASE WHEN tipo_evento = 'ALMUERZO' THEN 1 ELSE 0 END) as almuerzos,
            SUM(CASE WHEN tipo_evento = 'CENA' THEN 1 ELSE 0 END) as cenas
         FROM eventos_personal 
         WHERE DATE(fecha_hora) = ?", [$fecha]
    ) ?: ['desayunos' => 0, 'almuerzos' => 0, 'cenas' => 0];

    // 2. Contadores del comedor para Visitantes (consumo_visitantes)
    $comidaVis = Database::fetchOne(
        "SELECT 
            SUM(CASE WHEN tipo_comida = 'DESAYUNO' THEN 1 ELSE 0 END) as desayunos,
            SUM(CASE WHEN tipo_comida = 'ALMUERZO' THEN 1 ELSE 0 END) as almuerzos,
            SUM(CASE WHEN tipo_comida = 'CENA' THEN 1 ELSE 0 END) as cenas
         FROM consumo_visitantes 
         WHERE DATE(fecha_hora) = ?", [$fecha]
    ) ?: ['desayunos' => 0, 'almuerzos' => 0, 'cenas' => 0];

    // 3. Contadores de asistencia laboral (Trabajadores)
    $laboral = Database::fetchOne(
        "SELECT 
            SUM(CASE WHEN tipo_evento = 'INGRESO' THEN 1 ELSE 0 END) as ingresos,
            SUM(CASE WHEN tipo_evento = 'SALIDA_TRABAJO' THEN 1 ELSE 0 END) as salidas
         FROM eventos_personal 
         WHERE DATE(fecha_hora) = ?", [$fecha]
    ) ?: ['ingresos' => 0, 'salidas' => 0];

    // 4. Totales de personas únicas hoy
    $totalTrab = Database::fetchOne("SELECT COUNT(DISTINCT id_trabajador) as total FROM eventos_personal WHERE DATE(fecha_hora) = ?", [$fecha])['total'] ?? 0;
    $totalVis  = Database::fetchOne("SELECT COUNT(DISTINCT id_visitante) as total FROM consumo_visitantes WHERE DATE(fecha_hora) = ?", [$fecha])['total'] ?? 0;

    // 5. Feed: últimas marcaciones de Trabajadores (Ajustado t.nombre_completo a t.nombre si aplica en tu BD)
    $ultimasTrab = Database::fetchAll(
        "SELECT ep.fecha_hora, ep.tipo_evento, 'TRABAJADOR' AS tipo_persona,
                t.nombre_completo AS nombre, t.dni, a.nombre_area AS area
         FROM eventos_personal ep
         JOIN trabajadores t ON t.id_trabajador = ep.id_trabajador
         LEFT JOIN areas a ON a.id_area = t.id_area
         WHERE DATE(ep.fecha_hora) = ?
         ORDER BY ep.fecha_hora DESC LIMIT 20",
        [$fecha]
    );

    // 6. Feed: últimos consumos de Visitantes
    $ultimasVis = Database::fetchAll(
        "SELECT cv.fecha_hora, cv.tipo_comida AS tipo_evento, 'VISITANTE' AS tipo_persona,
                v.nombre, v.empresa AS area, '' AS dni
         FROM consumo_visitantes cv
         JOIN visitantes v ON v.id_visitante = cv.id_visitante
         WHERE DATE(cv.fecha_hora) = ?
         ORDER BY cv.fecha_hora DESC LIMIT 20",
        [$fecha]
    );

    // Unificar y ordenar combinados de más reciente a más antiguo
    $ultimas = array_merge($ultimasTrab, $ultimasVis);
    usort($ultimas, fn($a, $b) => strcmp($b['fecha_hora'], $a['fecha_hora']));
    $ultimas = array_slice($ultimas, 0, 30);

    // 7. CUMPLEAÑEROS DEL MES (Agregado para la sección de cumpleañeros)
    $cumpleaneros_raw = Database::fetchAll(
        "SELECT 
            t.id_trabajador,
            t.nombre_completo AS nombre,
            a.nombre_area AS area,
            t.fecha_nacimiento,
            DAY(t.fecha_nacimiento) as dia_cumple,
            DATE_FORMAT(t.fecha_nacimiento, '%d/%m') as fecha_formateada
         FROM trabajadores t
         LEFT JOIN areas a ON a.id_area = t.id_area
         WHERE MONTH(t.fecha_nacimiento) = MONTH(?)
           AND (t.activo = 1 OR t.activo IS NULL)
         ORDER BY DAY(t.fecha_nacimiento) ASC",
        [$fecha]
    );

    $hoyDia = (int)date('j', strtotime($fecha));
    $cumpleaneros = [];

    foreach ($cumpleaneros_raw as $c) {
        $diaCumple = (int)$c['dia_cumple'];
        $diasFaltantes = $diaCumple - $hoyDia;

        if ($diasFaltantes === 0) {
            $estadoText = '🎉 ¡Feliz cumpleaños!';
            $badgeClass = 'bg-danger text-white animate__animated animate__pulse animate__infinite';
            $isToday = true;
        } elseif ($diasFaltantes > 0) {
            $estadoText = ($diasFaltantes === 1) ? 'Falta 1 día' : "Faltan {$diasFaltantes} días";
            $badgeClass = 'bg-primary-subtle text-primary fw-bold';
            $isToday = false;
        } else {
            $estadoText = 'Ya cumplió';
            $badgeClass = 'bg-light text-muted';
            $isToday = false;
        }

        $c['dias_faltantes'] = $diasFaltantes;
        $c['estado_text']    = $estadoText;
        $c['badge_class']    = $badgeClass;
        $c['is_today']       = $isToday;

        $cumpleaneros[] = $c;
    }

    // Retornamos la respuesta con la estructura completa
    Response::success([
        'fecha'                  => $fecha,
        'desayunos'              => (int)$comidaTrab['desayunos'] + (int)$comidaVis['desayunos'],
        'almuerzos'              => (int)$comidaTrab['almuerzos'] + (int)$comidaVis['almuerzos'],
        'cenas'                  => (int)$comidaTrab['cenas'] + (int)$comidaVis['cenas'],
        'desayunos_trabajadores' => (int)$comidaTrab['desayunos'],
        'desayunos_visitantes'   => (int)$comidaVis['desayunos'],
        'almuerzos_trabajadores' => (int)$comidaTrab['almuerzos'],
        'almuerzos_visitantes'   => (int)$comidaVis['almuerzos'],
        'cenas_trabajadores'     => (int)$comidaTrab['cenas'],
        'cenas_visitantes'       => (int)$comidaVis['cenas'],
        'ingresos_laborales'     => (int)$laboral['ingresos'],
        'salidas_laborales'      => (int)$laboral['salidas'],
        'total_trabajadores'     => (int)$totalTrab,
        'total_visitantes'       => (int)$totalVis,
        'ultimas_marcaciones'    => $ultimas,
        'cumpleaneros'           => $cumpleaneros,
    ]);
}

// ═══════════════════════════════════════════════════════════════
//  VISITANTES — todas las rutas
// ═══════════════════════════════════════════════════════════════
if ($resource === 'visitantes') {

    // ── 0. NORMALIZACIÓN DE VARIABLES (Evita 'Undefined variable') ──
    // Aseguramos que $subResLimpio e $id siempre existan
    $subResLimpio = isset($subRes) ? trim((string)$subRes) : (isset($_GET['sub']) ? trim($_GET['sub']) : null);
    
    // Si $id no está definido previamente, intentamos obtenerlo de $subResLimpio si es numérico
    if (!isset($id) || !$id) {
        if (is_numeric($subResLimpio)) {
            $id = (int)$subResLimpio;
        } else {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        }
    }

    // ── 1. GET /api/visitantes/buscar?q=texto — AUTOCOMPLETE (MÓVIL) ──
    if ($subResLimpio === 'buscar' && $method === 'GET') {
        // Captura 'q' o 'search' de forma indistinta para blindar el teclado del celular
        $q = qp('q', qp('search', ''));
        Response::success(Visitante::buscar($q));
    }

    // ── 2. POST /api/visitantes/evento — registrar comedor a visitante existente ──
    if ($subResLimpio === 'evento' && $method === 'POST') {
        $idVis  = (int)($body['id_visitante'] ?? 0);
        $tipo   = strtoupper(trim($body['tipo_evento'] ?? ''));
        $obs    = trim($body['observacion'] ?? '');

        if (!$idVis) Response::error('id_visitante requerido');
        if (!$tipo)  Response::error('tipo_evento requerido');

        $vis = Visitante::getPorId($idVis);
        if (!$vis) Response::notFound('Visitante no encontrado');

        $result = Visitante::registrarEvento($idVis, $tipo, 'MANUAL', $obs);
        if (!$result['ok']) Response::conflict($result['error']);

        Response::success([
            'id_visitante' => $idVis,
            'nombre'       => $vis['nombre'],
            'empresa'      => $vis['empresa'],
            'evento'       => $result['label'],
            'tipo_raw'     => $result['tipo'],
            'hora'         => $result['hora'],
        ], '✅ ' . $result['label'] . ' registrado — ' . $vis['nombre']);
    }

    // ── 3. POST /api/visitantes/crear-y-registrar — nuevo visitante + evento ──
    if ($subResLimpio === 'crear-y-registrar' && $method === 'POST') {
        $nombre  = trim($body['nombre']    ?? '');
        $empresa = trim($body['empresa']   ?? '');
        $tipo    = strtoupper(trim($body['tipo_evento'] ?? ''));
        $obs     = trim($body['observacion'] ?? '');

        if (!$nombre)  Response::error('El nombre es obligatorio');
        if (!$empresa) Response::error('La empresa es obligatoria');
        if (!$tipo)    Response::error('tipo_evento es obligatorio');

        $result = Visitante::crearYRegistrar($nombre, $empresa, $tipo, null, $obs);
        if (!$result['ok']) Response::conflict($result['error']);

        Response::success($result, '✅ ' . $result['evento'] . ' registrado exitosamente');
    }

    // ── 4. GET /api/visitantes/{id}/historial ──
    $subSub = $subSub ?? ($_GET['subsub'] ?? null);
    if ($id && $subResLimpio !== 'buscar' && $subSub === 'historial' && $method === 'GET') {
        $vis = Visitante::getPorId($id);
        if (!$vis) Response::notFound('Visitante no encontrado');
        Response::success(Visitante::historialEvento($id));
    }

    // ── 5. GET /api/visitantes/{id} ──
    if ($id && $subResLimpio !== 'buscar' && !$subSub && $method === 'GET') {
        $vis = Visitante::getPorId($id);
        if (!$vis) Response::notFound('Visitante no encontrado');
        Response::success($vis);
    }

    // ── 6. GET /api/visitantes — listado general (Pestaña Visitantes) ──
    if (!$subResLimpio && !$id && $method === 'GET') {
        $q = qp('search', qp('q', ''));
        Response::success(Visitante::listar($q));
    }

    // ── 7. POST /api/visitantes — crear sin evento inmediato ──
    if (!$subResLimpio && !$id && $method === 'POST') {
        if (empty($body['nombre']))  Response::error('Nombre requerido');
        if (empty($body['empresa'])) Response::error('Empresa requerida');
        
        $result = Visitante::crear($body['nombre'], $body['empresa']);
        if (!$result['ok']) Response::error($result['error']);
        
        Response::success(['id_visitante' => $result['id']], 'Visitante creado');
    }

    // ── 8. PUT /api/visitantes/{id} — EDITAR / GUARDAR ──
    if ($id && $method === 'PUT') {
        $nombre  = trim($body['nombre'] ?? '');
        $empresa = trim($body['empresa'] ?? '');

        if (empty($nombre))  Response::error('El nombre es obligatorio');
        if (empty($empresa)) Response::error('La empresa es obligatoria');

        $data = [
            'nombre'  => $nombre,
            'empresa' => $empresa
        ];
        
        $result = Visitante::editar($id, $data);
        if (!$result['ok']) Response::error($result['error']);
        Response::success(null, 'Visitante actualizado correctamente');
    }

    if ($id && $method === 'DELETE') {
        $result = Visitante::eliminar($id);
        if (!$result['ok']) Response::error($result['error']);
        Response::success(null, 'Visitante eliminado correctamente');
    }
}

// ═══════════════════════════════════════════════════════════════
//  GET /api/comedor  — historial unificado
// ═══════════════════════════════════════════════════════════════
if ($resource === 'comedor' && $method === 'GET') {
    $desde     = qp('desde', date('Y-m-d'));
    $hasta     = qp('hasta', date('Y-m-d'));
    $area      = qp('area');
    $trabId    = qp('trabajador');
    $tipo      = qp('tipo');
    $persona   = qp('persona', 'todos'); // todos | trabajador | visitante

    $rows = [];

    // ── 1. TRABAJADORES (Leen de eventos_personal) ─────────────────
    if ($persona !== 'visitante') {
        $sql = "SELECT
                  ep.id_evento, 
                  ep.fecha_hora, 
                  ep.tipo_evento,
                  'TRABAJADOR' AS tipo_persona,
                  t.nombre_completo AS nombre, 
                  t.dni, 
                  a.nombre_area, 
                  t.empresa, 
                  t.cargo
                FROM eventos_personal ep
                JOIN trabajadores t ON t.id_trabajador = ep.id_trabajador
                JOIN areas a ON a.id_area = t.id_area
                WHERE ep.tipo_evento IN ('DESAYUNO','ALMUERZO','CENA')
                  AND DATE(ep.fecha_hora) BETWEEN ? AND ?";
        
        $p = [$desde, $hasta];
        if ($area)   { $sql .= " AND t.id_area = ?";        $p[] = $area; }
        if ($trabId) { $sql .= " AND ep.id_trabajador = ?"; $p[] = $trabId; }
        if ($tipo)   { $sql .= " AND ep.tipo_evento = ?";   $p[] = $tipo; }
        
        $rows = array_merge($rows, Database::fetchAll($sql . " LIMIT 500", $p));
    }

    // ── 2. VISITANTES (Leen de consumo_visitantes) ─────────────────
    if ($persona !== 'trabajador') {
        // Al usar '' AS dni, cubrimos la ausencia de la columna en la tabla visitantes
        $sql = "SELECT
                  cv.id_consumo AS id_evento, 
                  cv.fecha_hora, 
                  cv.tipo_comida AS tipo_evento,
                  'VISITANTE' AS tipo_persona,
                  v.nombre, 
                  '' AS dni, 
                  'Visitante' AS nombre_area, 
                  v.empresa, 
                  '' AS cargo
                FROM consumo_visitantes cv
                JOIN visitantes v ON v.id_visitante = cv.id_visitante
                WHERE DATE(cv.fecha_hora) BETWEEN ? AND ?";
        
        $p = [$desde, $hasta];
        if ($tipo) { $sql .= " AND cv.tipo_comida = ?"; $p[] = $tipo; }
        
        $rows = array_merge($rows, Database::fetchAll($sql . " LIMIT 500", $p));
    }

    // Ordenación unificada descendente (de más reciente a más antiguo)
    usort($rows, fn($a,$b) => strcmp($b['fecha_hora'], $a['fecha_hora']));

    Response::success(array_slice($rows, 0, 1000));
}


// ═══════════════════════════════════════════════════════════════
//  GET /api/asistencia
// ═══════════════════════════════════════════════════════════════
if ($resource === 'asistencia' && $method === 'GET') {
    $desde  = qp('desde', date('Y-m-d'));
    $hasta  = qp('hasta', date('Y-m-d'));
    $area   = qp('area');
    $trabId = qp('trabajador');

    // Consulta SQL Base con la incorporación de la columna observacion
    $sql = "SELECT 
                m.fecha,
                t.id_trabajador,
                t.nombre_completo,
                t.dni,
                t.empresa,
                t.cargo,
                a.nombre_area,
                m.hora_ingreso,
                m.hora_salida_break,
                m.hora_ingreso_break,
                m.hora_salida_trabajo,
                m.observacion, -- <--- CAMBIO: Se expone la observación grabada
                ROUND((
                    IFNULL(TIMESTAMPDIFF(MINUTE, m.hora_ingreso, m.hora_salida_trabajo), 0) - 
                    IFNULL(TIMESTAMPDIFF(MINUTE, m.hora_salida_break, m.hora_ingreso_break), 0)
                ) / 60, 2) AS horas_netas
            FROM (
                SELECT 
                    CASE 
                        WHEN e.tipo_evento = 'INGRESO' THEN DATE(e.fecha_hora)
                        WHEN TIME(e.fecha_hora) < '09:00:00' THEN DATE(SUBTIME(e.fecha_hora, '09:00:00'))
                        ELSE DATE(e.fecha_hora)
                    END AS fecha,
                    e.id_trabajador,
                    e.fecha_hora AS hora_ingreso,
                    e.observacion, -- <--- CAMBIO: Se obtiene la observación del evento INGRESO
                    @salida := (
                        SELECT MIN(st.fecha_hora) 
                        FROM eventos_personal st 
                        WHERE st.id_trabajador = e.id_trabajador 
                          AND st.tipo_evento = 'SALIDA_TRABAJO'
                          AND st.fecha_hora > e.fecha_hora
                          AND (
                              (TIME(st.fecha_hora) < '09:00:00' AND DATE(st.fecha_hora) = DATE_ADD(DATE(e.fecha_hora), INTERVAL 1 DAY))
                              OR DATE(st.fecha_hora) = DATE(e.fecha_hora)
                          )
                    ) AS hora_salida_trabajo,
                    (
                        SELECT MIN(sb.fecha_hora) 
                        FROM eventos_personal sb 
                        WHERE sb.id_trabajador = e.id_trabajador 
                          AND sb.tipo_evento = 'SALIDA_BREAK'
                          AND sb.fecha_hora > e.fecha_hora
                          AND (@salida IS NULL OR sb.fecha_hora < @salida)
                    ) AS hora_salida_break,
                    (
                        SELECT MIN(rb.fecha_hora) 
                        FROM eventos_personal rb 
                        WHERE rb.id_trabajador = e.id_trabajador 
                          AND rb.tipo_evento = 'REGRESO_BREAK'
                          AND rb.fecha_hora > e.fecha_hora
                          AND (@salida IS NULL OR rb.fecha_hora < @salida)
                    ) AS hora_ingreso_break
                FROM eventos_personal e
                WHERE e.tipo_evento = 'INGRESO'
            ) m
            JOIN trabajadores t ON t.id_trabajador = m.id_trabajador
            JOIN areas a ON a.id_area = t.id_area
            WHERE m.fecha BETWEEN ?";

    $p = [$desde];
    $sql .= " AND ?";
    $p[] = $hasta;

    if ($area)   { $sql .= " AND t.id_area = ?"; $p[] = $area; }
    if ($trabId) { $sql .= " AND m.id_trabajador = ?"; $p[] = $trabId; }
    
    $sql .= " ORDER BY m.fecha DESC, t.nombre_completo ASC, m.hora_ingreso ASC LIMIT 2000";
              
    $rows = Database::fetchAll($sql, $p);

    $consolidadoDiario = [];
    $turnosProcesados  = [];

    foreach ($rows as &$r) {
        $r['horas_netas'] = (float)$r['horas_netas'];
        
        if (!$r['hora_ingreso'] || !$r['hora_salida_trabajo']) {
            $r['horas_netas'] = 0.00;
        }

        $fechaKey = $r['fecha'];
        $trabajadorId = $r['id_trabajador'];
        $diaTrabajadorKey = "{$fechaKey}_{$trabajadorId}";

        if (!isset($turnosProcesados[$diaTrabajadorKey])) {
            $r['horas_programadas'] = 11.00;
            $turnosProcesados[$diaTrabajadorKey] = true;
        } else {
            $r['horas_programadas'] = 0.00;
        }

        if (!isset($consolidadoDiario[$diaTrabajadorKey])) {
            $consolidadoDiario[$diaTrabajadorKey] = [
                'horas_netas' => 0.00,
                'horas_programadas' => 11.00
            ];
        }
        $consolidadoDiario[$diaTrabajadorKey]['horas_netas'] += $r['horas_netas'];

        $diferencia = $r['horas_netas'] - (float)$r['horas_programadas'];
        $r['diferencia_horas'] = round($diferencia, 2);
        $r['diferencia'] = abs(round($diferencia, 2));
        $r['tipo_diferencia'] = $diferencia >= 0 ? 'extra' : 'deficitaria';

        // DETECCIÓN AUTOMÁTICA DE TURNO NOCHE
        $r['observacion_automatica'] = ''; 
        if ($r['hora_ingreso']) {
            $horaIngreso = (int)substr(explode(' ', $r['hora_ingreso'])[1], 0, 2);
            if ($horaIngreso >= 18 || $horaIngreso < 5) {
                $r['observacion_automatica'] = 'Turno Noche';
            }
        }
    }
    unset($r);

    $totalHorasExtras    = 0.00;
    $totalHorasFaltantes = 0.00;

    foreach ($consolidadoDiario as $dia) {
        $balanceDelDia = $dia['horas_netas'] - $dia['horas_programadas'];
        
        if ($balanceDelDia > 0) {
            $totalHorasExtras += $balanceDelDia;
        } elseif ($balanceDelDia < 0) {
            $totalHorasFaltantes += abs($balanceDelDia);
        }
    }

    $balanceNeto = $totalHorasExtras - $totalHorasFaltantes;

    Response::success([
        'registros' => $rows,
        'resumen' => [
            'total_extras'    => round($totalHorasExtras, 2),
            'total_faltantes' => round($totalHorasFaltantes, 2),
            'balance_neto'    => round($balanceNeto, 2),
            'estado_balance'  => $balanceNeto >= 0 ? 'A favor' : 'En contra'
        ]
    ]);
}

// ═══════════════════════════════════════════════════════════════
//  EDITAR ASISTENCIA POR FUERZA MAYOR (POST /api/asistencia/editar)
// ═══════════════════════════════════════════════════════════════
if ($resource === 'asistencia/editar' && $method === 'POST') {
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $trabId = $input['id_trabajador'] ?? null;
        $fecha  = $input['fecha'] ?? null;
        $obs    = strtoupper(trim($input['observacion'] ?? ''));

        if (!$trabId || !$fecha) {
            if (class_exists('Response') && method_exists('Response', 'error')) {
                Response::error('Faltan datos obligatorios para la edición.');
            } else {
                echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios para la edición.']);
            }
            exit;
        }

        $buildDateTime = function($timeStr) use ($fecha) {
            return !empty($timeStr) ? "{$fecha} {$timeStr}:00" : null;
        };

        $hIngreso = $buildDateTime($input['hora_ingreso'] ?? '');
        $hSBreak  = $buildDateTime($input['hora_salida_break'] ?? '');
        $hIBreak  = $buildDateTime($input['hora_ingreso_break'] ?? '');
        $hSalida  = $buildDateTime($input['hora_salida_trabajo'] ?? '');

        $ejecutarSql = function($sql, $params = []) {
            if (method_exists('Database', 'execute')) {
                return Database::execute($sql, $params);
            } elseif (method_exists('Database', 'nonQuery')) {
                return Database::nonQuery($sql, $params);
            } elseif (method_exists('Database', 'query')) {
                return Database::query($sql, $params);
            } elseif (method_exists('Database', 'fetchAll')) {
                return Database::fetchAll($sql, $params);
            } else {
                throw new Exception("No se encontró un método de ejecución en la clase Database.");
            }
        };

        // 1. Eliminar eventos antiguos del día seleccionado para no duplicar marcas
        $ejecutarSql(
            "DELETE FROM eventos_personal WHERE id_trabajador = ? AND DATE(fecha_hora) = ?", 
            [$trabId, $fecha]
        );

        // 2. Volver a insertar los eventos con la observación
        $eventos = [
            'INGRESO'        => $hIngreso,
            'SALIDA_BREAK'   => $hSBreak,
            'REGRESO_BREAK'  => $hIBreak,
            'SALIDA_TRABAJO' => $hSalida
        ];

        foreach ($eventos as $tipo => $fechaHora) {
            if ($fechaHora) {
                $ejecutarSql(
                    "INSERT INTO eventos_personal (id_trabajador, tipo_evento, fecha_hora, observacion) 
                     VALUES (?, ?, ?, ?)",
                    [$trabId, $tipo, $fechaHora, $obs]
                );
            }
        }

        if (class_exists('Response') && method_exists('Response', 'success')) {
            Response::success([], 'Asistencia actualizada correctamente por fuerza mayor.');
        } else {
            echo json_encode(['success' => true, 'message' => 'Asistencia actualizada correctamente.']);
        }
        exit;

    } catch (Throwable $e) {
        if (class_exists('Response') && method_exists('Response', 'error')) {
            Response::error('Error al guardar la edición: ' . $e->getMessage());
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al guardar la edición: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
//  GET /api/trabajadores
// ═══════════════════════════════════════════════════════════════
if ($resource === 'trabajadores') {
    // ------------------------------------------------------------------
    // GET: LISTAR TRABAJADORES CON FILTROS DE BÚSQUEDA Y ÁREA
    // ------------------------------------------------------------------
    if ($method === 'GET') {
        $q    = qp('q');
        $area = qp('area');

        $sql = "SELECT t.*, a.nombre_area 
                FROM trabajadores t
                LEFT JOIN areas a ON a.id_area = t.id_area 
                WHERE t.activo = 1";
        $p = [];

        if (!empty($q)) { 
            $sql .= " AND (t.nombre_completo LIKE ? OR t.dni LIKE ?)"; 
            $p[] = "%$q%"; 
            $p[] = "%$q%"; 
        }

        if (!empty($area) && $area !== 'TODAS') { 
            $sql .= " AND (t.id_area = ? OR a.nombre_area = ?)"; 
            $p[] = $area; 
            $p[] = $area; 
        }

        $sql .= " ORDER BY t.nombre_completo ASC LIMIT 500";

        Response::success(Database::fetchAll($sql, $p));
        return;
    }

    // ------------------------------------------------------------------
    // POST: CREAR TRABAJADOR
    // ------------------------------------------------------------------
    if ($method === 'POST') {
    // Validar campos obligatorios
    foreach (['nombre_completo', 'condicion'] as $f) {
        if (empty($body[$f])) {
            Response::error("El campo '$f' es requerido.");
            return;
        }
    }

    // Validación de DNI duplicado
    if (!empty($body['dni'])) {
        $existeDni = Database::fetchOne("SELECT 1 FROM trabajadores WHERE dni = ?", [$body['dni']]);
        if ($existeDni) {
            Response::conflict("El DNI {$body['dni']} ya se encuentra registrado.");
            return;
        }
    }

    $sql = "INSERT INTO trabajadores (dni, nombre_completo, fecha_nacimiento, id_area, cargo, empresa, condicion, fecha_ingreso, activo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";

    // 8 valores exactos para los 8 signos '?'
    $params = [
        !empty($body['dni']) ? trim($body['dni']) : null,
        trim($body['nombre_completo']),
        !empty($body['fecha_nacimiento']) ? $body['fecha_nacimiento'] : null,
        !empty($body['id_area']) ? (int)$body['id_area'] : null,
        $body['cargo'] ?? '',
        $body['empresa'] ?? '',
        strtoupper(trim($body['condicion'])), // Toma el valor elegido en el cbo
        !empty($body['fecha_ingreso']) ? $body['fecha_ingreso'] : date('Y-m-d')
    ];

    Database::query($sql, $params);

    Response::success(['id_trabajador' => (int)Database::lastInsertId()], 'Trabajador creado exitosamente');
    return;
}

    // ------------------------------------------------------------------
    // PUT: ACTUALIZAR TRABAJADOR EXISTENTE
    // ------------------------------------------------------------------
    if ($method === 'PUT' && $id) {
        $fields = []; 
        $params = [];

        // Lista de campos actualizables
        $camposPermitidos = ['dni', 'nombre_completo', 'fecha_nacimiento', 'id_area', 'cargo', 'empresa', 'condicion', 'fecha_ingreso', 'activo'];

        foreach ($camposPermitidos as $f) {
            if (array_key_exists($f, $body)) { 
                $fields[] = "$f = ?"; 
                
                if ($f === 'condicion') {
                    $params[] = strtoupper(trim($body[$f]));
                } elseif ($f === 'id_area') {
                    $params[] = !empty($body[$f]) ? (int)$body[$f] : null;
                } else {
                    $params[] = $body[$f];
                }
            }
        }

        if (empty($fields)) {
            Response::error('No se proporcionaron datos para actualizar.');
            return;
        }

        $params[] = $id;
        Database::query("UPDATE trabajadores SET " . implode(', ', $fields) . " WHERE id_trabajador = ?", $params);

        Response::success(null, 'Trabajador actualizado exitosamente');
        return;
    }
}

// ═══════════════════════════════════════════════════════════════
//  GET /api/areas
// ═══════════════════════════════════════════════════════════════
if ($resource === 'areas' && $method === 'GET') {
    // Agrupamos por id_area para traer solo las áreas con personal activo
    $sql = "SELECT DISTINCT a.id_area, a.nombre_area 
            FROM areas a
            INNER JOIN trabajadores t ON t.id_area = a.id_area
            WHERE t.activo = 1 
            ORDER BY a.nombre_area ASC";
            
    Response::success(Database::fetchAll($sql));
}

// ═══════════════════════════════════════════════════════════════
//  POST /api/carga-historica (Transcripción por ID desde Autocomplete)
// ═══════════════════════════════════════════════════════════════
if ($resource === 'carga-historica' && $method === 'POST') {
    $registros = $body['registros'] ?? [];
    
    if (empty($registros)) Response::error('No se enviaron registros.');

    $insertados = 0;

    foreach ($registros as $reg) {
        $tipoPersona = $reg['tipo_persona'];
        $idPersona   = (int)$reg['id_persona'];
        $fechaHora   = $reg['fecha'] . ' ' . $reg['hora'] . ':00';
        $tipoEvento  = $reg['tipo_evento'];

        if ($tipoPersona === 'TRABAJADOR') {
            // Inserción directa limpia en la tabla de personal
            $sql = "INSERT INTO eventos_personal (id_trabajador, fecha_hora, tipo_evento, observacion) 
                    VALUES (?, ?, ?, '')";
            Database::query($sql, [$idPersona, $fechaHora, $tipoEvento]);
            $insertados++;
        } else {
            // Inserción directa limpia en tu tabla real de consumos de visitas
            $sql = "INSERT INTO consumo_visitantes (id_visitante, fecha_hora, tipo_comida) 
                    VALUES (?, ?, ?)";
            Database::query($sql, [$idPersona, $fechaHora, $tipoEvento]);
            $insertados++;
        }
    }

    Response::success(null, "Se transcribieron exitosamente {$insertados} registros a la base de datos.");
}

// ═══════════════════════════════════════════════════════════════
//  GET /api/export/*
// ═══════════════════════════════════════════════════════════════
if ((str_starts_with($resource, 'export') || $resource === 'export') && $method === 'GET') {
    require_once dirname(__DIR__) . '/exports/ExportController.php';
    
    // Si la acción vino junta (ej: "export/comedor"), la separamos por la barra
    $partes = explode('/', trim($resource, '/'));
    
    // El tipo de reporte ('comedor' o 'asistencia') será el segundo segmento,
    // si no existe, intentamos usar la variable $subRes original limpiando barras.
    $tipoReporte = isset($partes[1]) ? $partes[1] : trim($subRes, '/');
    
    // Ejecutamos la exportación de forma segura
    ExportController::export($tipoReporte);
    exit; // ⚠️ Detiene la ejecución aquí para que jamás llegue al Response::error de abajo
}

// ═══════════════════════════════════════════════════════════════
// REGISTRO CRONOGRAMA
// ═══════════════════════════════════════════════════════════════

// 1. OBTENER TODO EL CRONOGRAMA DE UN MES ESPECÍFICO (ORDENADO POR ÁREA)
if ($resource === 'obtener_cronograma_mes' && $method === 'GET') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    $mesAño = qp('mes', date('Y-m'));

    try {
        // Consulta uniendo trabajadores y areas con orden personalizado
        $trabajadores = Database::fetchAll(
            "SELECT 
                t.id_trabajador, 
                t.nombre_completo, 
                t.dni, 
                t.condicion, 
                a.nombre_area AS area 
             FROM trabajadores t
             INNER JOIN areas a ON t.id_area = a.id_area
             WHERE t.activo = 1 
             ORDER BY 
                FIELD(LOWER(a.nombre_area), 
                    'supervisores', 
                    'administracion', 
                    'operaciones', 
                    'flota', 
                    'maquinaria pesada', 
                    'limpieza', 
                    'seguridad', 
                    'comedor'
                ) ASC, 
                t.nombre_completo ASC"
        ) ?? [];

        // Obtener la programación Y observaciones para ese mes
        $programacionRaw = Database::fetchAll(
            "SELECT id_trabajador, fecha, valor_dia, observacion 
             FROM programacion_diaria 
             WHERE DATE_FORMAT(fecha, '%Y-%m') = ?",
            [$mesAño]
        ) ?? [];

        // Mapear programaciones y observaciones
        $mapProgramacion  = [];
        $mapObservaciones = [];

        if (is_array($programacionRaw)) {
            foreach ($programacionRaw as $prog) {
                // Convertimos temporalmente por seguridad si el registro no viene como array asociativo puro
                $p = (array)$prog;

                $idT = $p['id_trabajador'] ?? null;
                $f   = $p['fecha'] ?? null;

                if ($idT && $f) {
                    // Guardar valor del día (turno)
                    if (isset($p['valor_dia']) && $p['valor_dia'] !== '') {
                        $mapProgramacion[$idT][$f] = $p['valor_dia'];
                    }

                    // Guardar observación (si no está vacía)
                    $obsTexto = isset($p['observacion']) ? trim((string)$p['observacion']) : '';
                    if ($obsTexto !== '') {
                        $mapObservaciones[$idT][$f] = $obsTexto;
                    }
                }
            }
        }

        // Unificar respuesta
        $resultado = [];
        if (is_array($trabajadores)) {
            foreach ($trabajadores as $t) {
                $item = (array)$t;
                $id   = $item['id_trabajador'] ?? null;

                if ($id) {
                    $item['programacion']  = $mapProgramacion[$id] ?? (object)[];
                    $item['observaciones'] = $mapObservaciones[$id] ?? (object)[];
                    $resultado[] = $item;
                }
            }
        }

        Response::success($resultado);
        return;

    } catch (Exception $e) {
        Response::error("Error en la consulta: " . $e->getMessage(), 500);
        return;
    }
}

// 2. OBTENER LISTA DE ÁREAS (Para poblar el filtro select)
if ($resource === 'obtener_areas' && $method === 'GET') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    try {
        $areas = Database::fetchAll(
            "SELECT id_area AS id_area, nombre_area AS nombre_area 
             FROM areas 
             ORDER BY id_area ASC"
        ) ?? [];
        
        Response::success($areas);
        return;
    } catch (Exception $e) {
        Response::error("Error al obtener áreas: " . $e->getMessage(), 500);
        return;
    }
}
// 3. GUARDAR CAMBIOS DE PROGRAMACIÓN EN LOTE (BATCH)
if (($resource === 'guardar_cronograma_lote' || (isset($_GET['action']) && $_GET['action'] === 'guardar_cronograma_lote')) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $cambios = $input['cambios'] ?? [];

        if (empty($cambios) || !is_array($cambios)) {
            echo json_encode([
                'success' => false,
                'message' => 'No se recibieron datos para guardar.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // SQL para guardar o actualizar el turno Y la observación al mismo tiempo
        $sql = "INSERT INTO programacion_diaria (id_trabajador, fecha, valor_dia, es_dia_laboral, observacion)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    valor_dia = VALUES(valor_dia),
                    es_dia_laboral = VALUES(es_dia_laboral),
                    observacion = VALUES(observacion)";

        foreach ($cambios as $item) {
            $idTrabajador = (int)($item['id_trabajador'] ?? 0);
            $fecha        = trim($item['fecha'] ?? '');
            $valorDia     = trim($item['valor'] ?? '');
            $observacion  = trim($item['observacion'] ?? '');

            if ($idTrabajador > 0 && !empty($fecha)) {
                // Si el turno empieza por 'L' (Ej: 'L', 'LIBRE'), es día no laboral
                $esDiaLaboral = (strpos($valorDia, 'L') === 0) ? 0 : 1;

                if (class_exists('Database') && method_exists('Database', 'query')) {
                    Database::query($sql, [$idTrabajador, $fecha, $valorDia, $esDiaLaboral, $observacion]);
                } else {
                    $conexion = $db ?? $pdo;
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([$idTrabajador, $fecha, $valorDia, $esDiaLaboral, $observacion]);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Fila del cronograma y observaciones guardadas correctamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error SQL: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// VERIFICAR LA COMIDA
// ═══════════════════════════════════════════════════════════════

if ($resource === 'faltan_comer' && $method === 'GET') {
    try {
        // Obtenemos y limpiamos la fecha recibida
        $fechaFiltro = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d');

        // Si por alguna razón llega vacía, asignamos HOY
        if (empty($fechaFiltro)) {
            $fechaFiltro = date('Y-m-d');
        }

        // Validar formato estricto YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFiltro)) {
            Response::error('Formato de fecha inválido. Utilice YYYY-MM-DD. Valor recibido: ' . var_export($fechaFiltro, true));
            return;
        }

        // Consulta filtrando por la fecha enviada
        $sql = "SELECT 
                    t.id_trabajador,
                    t.dni,
                    t.nombre_completo,
                    COALESCE(a.nombre_area, 'SIN AREA') AS nombre_area,
                    t.condicion,
                    p.valor_dia AS turno_programado,
                    MAX(CASE WHEN c.tipo_evento = 'DESAYUNO' THEN 1 ELSE 0 END) AS desayuno_marcado,
                    MAX(CASE WHEN c.tipo_evento = 'ALMUERZO' THEN 1 ELSE 0 END) AS almuerzo_marcado,
                    MAX(CASE WHEN c.tipo_evento = 'CENA'     THEN 1 ELSE 0 END) AS cena_marcada
                FROM trabajadores t
                INNER JOIN programacion_diaria p 
                        ON t.id_trabajador = p.id_trabajador 
                       AND DATE(p.fecha) = ?
                LEFT JOIN areas a 
                       ON t.id_area = a.id_area
                LEFT JOIN eventos_personal c 
                       ON t.id_trabajador = c.id_trabajador 
                      AND DATE(c.fecha_hora) = ?
                      AND c.tipo_evento IN ('DESAYUNO', 'ALMUERZO', 'CENA')
                WHERE p.es_dia_laboral = 1 
                  AND p.valor_dia NOT LIKE 'L%'
                GROUP BY t.id_trabajador, t.dni, t.nombre_completo, a.nombre_area, t.condicion, p.valor_dia";

        $stmt = Database::query($sql, [$fechaFiltro, $fechaFiltro]);
        $trabajadores = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        Response::success([
            'fecha'            => $fechaFiltro,
            'total_pendientes' => count($trabajadores),
            'trabajadores'     => $trabajadores
        ]);

    } catch (Throwable $e) {
        Response::error('Error SQL: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// MARCACION MANUAL COMIDA
// ═══════════════════════════════════════════════════════════════
if ($resource === 'marcar_comida_manual' || (isset($_GET['action']) && $_GET['action'] === 'marcar_comida_manual')) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $idTrabajador = $data['id_trabajador'] ?? null;
        $tipoEvento   = $data['tipo_evento'] ?? null;
        $fechaEnvio   = !empty($data['fecha']) ? trim($data['fecha']) : date('Y-m-d');

        if (!$idTrabajador || !$tipoEvento) {
            Response::error('Faltan datos obligatorios (trabajador o evento).');
            return;
        }

        // Definimos la hora según si es la fecha de hoy o una fecha pasada
        $horaBase = ($fechaEnvio === date('Y-m-d')) ? date('H:i:s') : '12:00:00';
        $fechaHoraRegistro = $fechaEnvio . ' ' . $horaBase;

        // Si viene "DESAYUNO,ALMUERZO,CENA", convertimos en array
        $eventosArray = array_map('trim', explode(',', $tipoEvento));

        $sqlInsert = "INSERT INTO eventos_personal (id_trabajador, tipo_evento, fecha_hora, observacion)
                      VALUES (?, ?, ?, 'MANUAL')
                      ON DUPLICATE KEY UPDATE fecha_hora = VALUES(fecha_hora)";

        foreach ($eventosArray as $evento) {
            if (!empty($evento)) {
                Database::query($sqlInsert, [$idTrabajador, $evento, $fechaHoraRegistro]);
            }
        }

        Response::success(['message' => 'Marcación(es) registrada(s) correctamente']);

    } catch (Throwable $e) {
        Response::error('Error al guardar marcación: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// ELIMINACION MANUAL COMIDA
// ═══════════════════════════════════════════════════════════════
if ($resource === 'eliminar_comedor' || (isset($_GET['action']) && $_GET['action'] === 'eliminar_comedor')) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $idComedor = $data['id_comedor'] ?? null;

        if (!$idComedor) {
            Response::error('ID de registro no proporcionado.');
            return;
        }

        // Intenta eliminar de eventos_personal
        $sql = "DELETE FROM eventos_personal WHERE id_evento = ? AND tipo_evento IN ('DESAYUNO', 'ALMUERZO', 'CENA')";
        $stmt = Database::query($sql, [$idComedor]);

        // Si no afectó filas en eventos_personal, intentamos en consumo_visitantes
        if ($stmt->rowCount() === 0) {
            $sqlVisitantes = "DELETE FROM consumo_visitantes WHERE id_consumo = ?";
            Database::query($sqlVisitantes, [$idComedor]);
        }

        Response::success(['message' => 'Registro eliminado correctamente.']);

    } catch (Throwable $e) {
        Response::error('Error al eliminar registro: ' . $e->getMessage());
    }
}


// ═══════════════════════════════════════════════════════════════
// ELIMINAR/DESMARCAR COMIDA POR TRABAJADOR, TIPO Y FECHA (Modal)
// ═══════════════════════════════════════════════════════════════
if ($resource === 'eliminar_comida_manual' || (isset($_GET['action']) && $_GET['action'] === 'eliminar_comida_manual')) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $idTrabajador = $data['id_trabajador'] ?? null;
        $tipoEvento   = $data['tipo_evento'] ?? null;
        $fechaEnvio   = !empty($data['fecha']) ? trim($data['fecha']) : date('Y-m-d');

        if (!$idTrabajador || !$tipoEvento) {
            Response::error('Faltan datos obligatorios (trabajador o tipo de comida).');
            return;
        }

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEnvio)) {
            Response::error('Formato de fecha inválido.');
            return;
        }

        $sqlDelete = "DELETE FROM eventos_personal 
                      WHERE id_trabajador = ? 
                        AND tipo_evento = ? 
                        AND DATE(fecha_hora) = ?";

        Database::query($sqlDelete, [$idTrabajador, $tipoEvento, $fechaEnvio]);

        Response::success(['message' => 'Marcación eliminada correctamente.']);

    } catch (Throwable $e) {
        Response::error('Error al eliminar marcación: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// ALERTAS DE CRONOGRAMA Y ASISTENCIA
// ═══════════════════════════════════════════════════════════════
if ($resource === 'alertas_proximas' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $fechaHoy    = date('Y-m-d');
        $fechaLimite = date('Y-m-d', strtotime('+3 days'));

        // Consulta unificada para LLEGADAS (Inicia trabajo T%) y SALIDAS (Inicia días libres / deja de ser T%)
        $sql = "SELECT 
                    t.dni,
                    t.nombre_completo,
                    a.nombre_area,
                    p.valor_dia AS turno,
                    p.fecha,
                    DATEDIFF(p.fecha, CURDATE()) AS dias_faltantes,
                    CASE 
                        -- LLEGADA: El día actual tiene T% pero el día anterior no
                        WHEN p.valor_dia LIKE 'T%' AND (p_ayer.valor_dia IS NULL OR p_ayer.valor_dia NOT LIKE 'T%') THEN 'LLEGADA'
                        
                        -- SALIDA: El día anterior tuvo T% y el día actual es Días Libres (L) o no es T%
                        WHEN p_ayer.valor_dia LIKE 'T%' AND (p.valor_dia IS NULL OR p.valor_dia NOT LIKE 'T%') THEN 'SALIDA'
                        ELSE 'OTRO'
                    END AS tipo_alerta
                FROM programacion_diaria p
                INNER JOIN trabajadores t ON t.id_trabajador = p.id_trabajador
                LEFT JOIN areas a ON a.id_area = t.id_area
                LEFT JOIN programacion_diaria p_ayer 
                       ON p_ayer.id_trabajador = p.id_trabajador 
                      AND p_ayer.fecha = DATE_SUB(p.fecha, INTERVAL 1 DAY)
                WHERE p.fecha BETWEEN ? AND ?
                  AND t.activo = 1
                  AND (
                      -- Condición LLEGADA
                      (p.valor_dia LIKE 'T%' AND (p_ayer.valor_dia IS NULL OR p_ayer.valor_dia NOT LIKE 'T%'))
                      OR
                      -- Condición SALIDA (Hoy/próximos días es descanso u otro valor que no sea T%, habiendo trabajado el día anterior)
                      (p_ayer.valor_dia LIKE 'T%' AND (p.valor_dia IS NULL OR p.valor_dia NOT LIKE 'T%'))
                  )
                ORDER BY p.fecha ASC, tipo_alerta DESC, a.nombre_area, t.nombre_completo";

        if (class_exists('Database') && method_exists('Database', 'fetchAll')) {
            $trabajadores = Database::fetchAll($sql, [$fechaHoy, $fechaLimite]);
        } else {
            $conexion = $db ?? $pdo;
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$fechaHoy, $fechaLimite]);
            $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success'      => true,
            'total'        => count($trabajadores),
            'trabajadores' => $trabajadores
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error SQL: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// OBSERVACIONES EN CRONOGRAMA Y ASISTENCIA
// ═══════════════════════════════════════════════════════════════


if (($resource === 'guardar_observacion_cronograma' || (isset($_GET['action']) && $_GET['action'] === 'guardar_observacion_cronograma')) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $idTrabajador = (int)($input['id_trabajador'] ?? 0);
        $fecha        = trim($input['fecha'] ?? '');
        $observacion  = trim($input['observacion'] ?? '');

        if ($idTrabajador <= 0 || empty($fecha)) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan parámetros obligatorios (id_trabajador o fecha).'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // UPDATE directo sobre el registro existente en la programación diaria
        $sql = "UPDATE programacion_diaria 
                SET observacion = ? 
                WHERE id_trabajador = ? AND fecha = ?";

        if (class_exists('Database') && method_exists('Database', 'query')) {
            Database::query($sql, [$observacion, $idTrabajador, $fecha]);
        } else {
            $conexion = $db ?? $pdo;
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$observacion, $idTrabajador, $fecha]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Observación actualizada correctamente en el cronograma.'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error SQL: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($resource === 'obtener_cronograma_mes' || (isset($_GET['action']) && $_GET['action'] === 'obtener_cronograma_mes'))) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $mesAno = $_GET['mes'] ?? date('Y-m'); // Formato: YYYY-MM

        // 1. Obtener Trabajadores Activos
        $sqlTrabajadores = "SELECT id_trabajador, nombre_completo, dni, area, condicion 
                            FROM trabajadores 
                            WHERE estado = 'ACTIVO' 
                            ORDER BY area, nombre_completo";
        
        $trabajadores = class_exists('Database') && method_exists('Database', 'query') 
            ? Database::query($sqlTrabajadores) 
            : $conexion->query($sqlTrabajadores)->fetchAll(PDO::FETCH_ASSOC);

        // Convertir a array asociativo en caso de que Database::query devuelva objetos
        $trabajadores = json_decode(json_encode($trabajadores), true) ?? [];

        // 2. Obtener Programación y Observaciones del mes
        $sqlProg = "SELECT id_trabajador, fecha, valor_dia, observacion 
                    FROM programacion_diaria 
                    WHERE fecha LIKE ?";
        
        $paramsProg = ["{$mesAno}-%"];
        
        if (class_exists('Database') && method_exists('Database', 'query')) {
            $programacionRaw = Database::query($sqlProg, $paramsProg);
        } else {
            $stmt = $conexion->prepare($sqlProg);
            $stmt->execute($paramsProg);
            $programacionRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Convertir a array asociativo plano
        $programacionRaw = json_decode(json_encode($programacionRaw), true) ?? [];

        // 3. Crear los mapas de datos
        $progMap = [];
        $obsMap  = [];

        foreach ($programacionRaw as $row) {
            $idT = (int)$row['id_trabajador'];
            $f   = trim($row['fecha']); // Formato YYYY-MM-DD
            
            // Guardar turno
            if (isset($row['valor_dia']) && $row['valor_dia'] !== '') {
                $progMap[$idT][$f] = $row['valor_dia'];
            }
            
            // Guardar observación (Incluso si tiene espacios, hacemos trim)
            $obsTexto = isset($row['observacion']) ? trim($row['observacion']) : '';
            if ($obsTexto !== '') {
                $obsMap[$idT][$f] = $obsTexto;
            }
        }

        // 4. Estructurar la respuesta final para el JavaScript
        $resultado = [];
        foreach ($trabajadores as $t) {
            $idT = (int)$t['id_trabajador'];
            
            $resultado[] = [
                'id_trabajador'   => $idT,
                'nombre_completo' => $t['nombre_completo'],
                'dni'             => $t['dni'],
                'area'            => $t['area'],
                'condicion'       => $t['condicion'],
                'programacion'    => isset($progMap[$idT]) ? $progMap[$idT] : (object)[],
                'observaciones'   => isset($obsMap[$idT]) ? $obsMap[$idT] : (object)[]
            ];
        }

        echo json_encode([
            'success' => true,
            'data'    => $resultado
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error SQL: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

Response::error("Ruta no encontrada: /$resource/", 404);
