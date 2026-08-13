<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once ROOT_PATH . 'views/layouts/header.php';
?>

<div id="dash-content">

  <!-- BANNER DE CUMPLEAÑEROS DEL MES -->
  <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center gap-2" style="border-bottom: 1px solid #f0f0f0;">
      <span class="fs-5">🎂</span>
      <h6 class="fw-700 mb-0 text-dark">Cumpleañeros del mes</h6>
      <span class="badge bg-secondary-subtle text-secondary ms-auto" id="cumple-total">0 en total</span>
    </div>
    
    <div class="card-body p-3">
      <div id="cumple-container" class="d-flex gap-3 overflow-x-auto pb-2" style="scrollbar-width: thin;">
        <div class="text-center py-3 text-muted w-100" style="font-size: 0.9rem;">
          Cargando cumpleañeros...
        </div>
      </div>
    </div>
  </div>

  <!-- Stat cards row -->
  <div class="row g-3 mb-4" id="stat-row">
    <?php
    $stats = [
      ['id'=>'s-desayunos',    'icon'=>'☕', 'color'=>'#fffbeb','bcolor'=>'#d97706','label'=>'Desayunos hoy'],
      ['id'=>'s-almuerzos',    'icon'=>'🍽️','color'=>'#f5f3ff','bcolor'=>'#7c3aed','label'=>'Almuerzos hoy'],
      ['id'=>'s-cenas',        'icon'=>'🌙', 'color'=>'#eff6ff','bcolor'=>'#1d4ed8','label'=>'Cenas hoy'],
      ['id'=>'s-trabajadores', 'icon'=>'👷', 'color'=>'#f0fdf4','bcolor'=>'#15803d','label'=>'Trabajadores atendidos'],
      ['id'=>'s-visitantes',   'icon'=>'🪪', 'color'=>'#fef2f2','bcolor'=>'#dc2626','label'=>'Visitantes hoy'],
      ['id'=>'s-ingresos',     'icon'=>'🟢', 'color'=>'#f0fdf4','bcolor'=>'#059669','label'=>'Ingresos laborales'],
      ['id'=>'s-salidas',      'icon'=>'🔴', 'color'=>'#fef2f2','bcolor'=>'#dc2626','label'=>'Salidas laborales'],
    ];
    foreach ($stats as $s): ?>
      <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:<?= $s['color'] ?>">
            <?= $s['icon'] ?>
          </div>
          <div class="stat-value" id="<?= $s['id'] ?>">—</div>
          <div class="stat-label"><?= $s['label'] ?></div>
        </div>
      </div>
    <?php endforeach ?>
  </div>

  <div class="row g-3">
    <!-- Últimas marcaciones -->
    <div class="col-12 col-xl-8">
      <div class="card">
        <div class="card-header d-flex align-items-center gap-2 bg-white py-3 px-4" style="border-radius:12px 12px 0 0">
          <i class="bi bi-activity text-primary"></i>
          <span class="fw-600">Últimas marcaciones de hoy</span>
          <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="loadDashboard()">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th class="ps-4">Hora</th>
                  <th>Trabajador</th>
                  <th>Área</th>
                  <th>Evento</th>
                </tr>
              </thead>
              <tbody id="recent-tbody">
                <tr><td colspan="4" class="text-center py-4 text-muted">Cargando...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Acceso rápido -->
    <div class="col-12 col-xl-4">
      <div class="card p-4 d-flex flex-column gap-3">
        <h6 class="fw-600 mb-1">Acceso rápido</h6>
        <a href="<?= BASE_URL ?>/views/scanner/index.php?modulo=comedor" class="btn btn-warning d-flex align-items-center gap-2 fw-600">
          <i class="bi bi-qr-code-scan fs-5"></i> Escanear Comedor
        </a>
        <a href="<?= BASE_URL ?>/views/scanner/index.php?modulo=laboral" class="btn btn-primary d-flex align-items-center gap-2 fw-600">
          <i class="bi bi-qr-code-scan fs-5"></i> Escanear Asistencia
        </a>
        <hr class="my-1">
        <a href="<?= BASE_URL ?>/views/reportes/index.php" class="btn btn-outline-success d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-spreadsheet"></i> Exportar a Excel
        </a>
        <a href="<?= BASE_URL ?>/views/trabajadores/index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
          <i class="bi bi-people"></i> Gestionar Trabajadores
        </a>

        <!-- Comida activa -->
        <div class="mt-2 p-3 rounded-3" style="background:var(--primary-light)">
          <div style="font-size:.72rem;font-weight:600;color:var(--primary);text-transform:uppercase;letter-spacing:.06em">Turno activo ahora</div>
          <div id="turno-activo" class="fw-700 mt-1" style="color:var(--primary);font-size:1.1rem">—</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const TIPOS = {
  INGRESO:'badge-INGRESO',SALIDA_BREAK:'badge-SALIDA_BREAK',
  INGRESO_BREAK:'badge-INGRESO_BREAK',SALIDA_TRABAJO:'badge-SALIDA_TRABAJO',
  DESAYUNO:'badge-DESAYUNO',ALMUERZO:'badge-ALMUERZO',CENA:'badge-CENA',
};
const LABELS = {
  INGRESO:'Ingreso',SALIDA_BREAK:'Salida break',INGRESO_BREAK:'Regreso break',
  SALIDA_TRABAJO:'Salida',DESAYUNO:'Desayuno',ALMUERZO:'Almuerzo',CENA:'Cena',
};

function detectarTurno() {
  const h = new Date().getHours() * 100 + new Date().getMinutes();
  if (h >= 500  && h <= 959)  return '☕ Desayuno (05:00–09:59)';
  if (h >= 1000 && h <= 1559) return '🍽️ Almuerzo (10:00–15:59)';
  return '🌙 Cena (16:00–23:59)';
}

async function loadDashboard() {
  try {
    const r = await fetch('<?= BASE_URL ?>/api/index.php?action=dashboard', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true' 
      }
    });
    
    const j = await r.json();
    if (!j.success) return;
    const d = j.data;

    // Métricas principales
    document.getElementById('s-desayunos').textContent    = d.desayunos;
    document.getElementById('s-almuerzos').textContent    = d.almuerzos;
    document.getElementById('s-cenas').textContent        = d.cenas;
    document.getElementById('s-trabajadores').textContent = d.total_trabajadores;
    document.getElementById('s-visitantes').textContent   = d.total_visitantes;
    document.getElementById('s-ingresos').textContent     = d.ingresos_laborales;
    document.getElementById('s-salidas').textContent      = d.salidas_laborales;

    // Renderizar Cumpleañeros
    const cumpleContainer = document.getElementById('cumple-container');
    const cumpleTotal = document.getElementById('cumple-total');

    if (d.cumpleaneros && d.cumpleaneros.length > 0) {
      cumpleTotal.textContent = `${d.cumpleaneros.length} en total`;
      cumpleContainer.innerHTML = d.cumpleaneros.map(c => `
        <div class="card flex-shrink-0 border p-3 ${c.is_today ? 'border-warning bg-warning-subtle' : 'bg-white'}" 
             style="min-width: 220px; max-width: 260px; border-radius: 10px;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="badge rounded-pill px-2 py-1 ${c.badge_class}" style="font-size:0.75rem;">
              ${c.estado_text}
            </span>
            <small class="text-muted fw-600"><i class="bi bi-calendar-event"></i> ${c.fecha_formateada}</small>
          </div>
          <div class="fw-700 text-truncate text-dark" title="${c.nombre}">
            ${c.nombre}
          </div>
          <small class="text-muted text-truncate" style="font-size:0.8rem;">
            <i class="bi bi-building"></i> ${c.area || 'Sin Área'}
          </small>
        </div>
      `).join('');
    } else {
      cumpleTotal.textContent = '0 en total';
      cumpleContainer.innerHTML = `
        <div class="text-center py-3 text-muted w-100" style="font-size: 0.9rem;">
          <i class="bi bi-calendar-x me-1"></i> No hay cumpleañeros registrados para este mes.
        </div>`;
    }

    // ÚLTIMAS MARCACIONES
    const tbody = document.getElementById('recent-tbody');
    if (!tbody) return;

    if (!d.ultimas_marcaciones || !d.ultimas_marcaciones.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Sin marcaciones hoy</td></tr>';
      return;
    }
    
    tbody.innerHTML = d.ultimas_marcaciones.map(m => {
      const dt   = m.fecha_hora ? m.fecha_hora.split(' ') : [];
      const hora = dt[1] ? dt[1].substring(0, 8) : '—';
      
      const badge = TIPOS[m.tipo_evento] || '';
      const lbl   = LABELS[m.tipo_evento] || m.tipo_evento;
      
      return `<tr>
        <td class="ps-4"><span style="font-family:var(--bs-font-monospace);font-size:.83rem">${hora}</span></td>
        <td>
          <span class="fw-500">${m.nombre || '—'}</span><br>
          <small class="text-muted">${m.dni || '—'}</small>
        </td>
        <td><small>${m.area || '—'}</small></td>
        <td><span class="evt-badge ${badge}">${lbl}</span></td>
      </tr>`;
    }).join('');
  } catch(e) { 
    console.error("Error crítico al procesar el Dashboard:", e); 
  }
}

// Solicitar permiso al cargar la página
document.addEventListener('DOMContentLoaded', () => {
  if ('Notification' in window && Notification.permission !== 'granted') {
    Notification.requestPermission();
  }
  // Consultar alertas al iniciar
  verificarAlertasProximas();
});

async function verificarAlertasProximas() {
  try {
    const response = await fetch('<?= BASE_URL ?>/api/index.php?action=alertas_proximas');
    const result = await response.json();

    if (result.success && result.total > 0) {
      // 1. Mostrar notificación nativa del sistema
      lanzarNotificacionNativa(result.total, result.mensaje);
      
      // 2. Opcional: Mostrar un Banner/Modal dentro del mismo sistema
      mostrarBannerAlerta(result.trabajadores);
    }
  } catch (error) {
    console.error('Error al verificar alertas:', error);
  }
}

function lanzarNotificacionNativa(total, resumen) {
  if (!('Notification' in window)) return;

  if (Notification.permission === 'granted') {
    new Notification(`⚠️ ALERTA DE PROGRAMACIÓN (${total} Pendientes)`, {
      body: resumen,
      icon: '<?= BASE_URL ?>/assets/img/icon-alert.png' // Opcional: ruta a un icono
    });
  }
}

function mostrarBannerAlerta(trabajadores) {
  const contenedor = document.getElementById('contenedor-alertas-sistema');
  if (!contenedor) return;

  let html = `
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <strong>⚠️ Recordatorio (2 días antes):</strong>
      <ul class="mb-0 mt-2">`;
  
  trabajadores.forEach(t => {
    html += `<li><strong>${t.nombre_completo}</strong> (${t.nombre_area}) - Turno: ${t.turno}</li>`;
  });

  html += `
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;

  contenedor.innerHTML = html;
}

document.getElementById('turno-activo').textContent = detectarTurno();
loadDashboard();
setInterval(loadDashboard, 30000);
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>