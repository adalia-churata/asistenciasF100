<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$pageTitle = 'Historial Comedor';
$activeNav = 'comedor';
require_once ROOT_PATH . 'views/layouts/header.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);


?>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body py-3 px-4">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Desde</label>
        <input type="date" id="f-desde" class="form-control form-control-sm"
               value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Hasta</label>
        <input type="date" id="f-hasta" class="form-control form-control-sm"
               value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Área</label>
        <select id="f-area" class="form-select form-select-sm">
          <option value="">Todas</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Tipo comida</label>
        <select id="f-tipo" class="form-select form-select-sm">
          <option value="">Todos</option>
          <option value="DESAYUNO">Desayuno</option>
          <option value="ALMUERZO">Almuerzo</option>
          <option value="CENA">Cena</option>
        </select>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2 justify-content-md-end">
        <button class="btn btn-primary btn-sm px-3" onclick="loadComedor()">
          <i class="bi bi-search"></i> Buscar
        </button>
        <a id="export-btn" href="#" class="btn btn-success btn-sm px-3">
          <i class="bi bi-file-earmark-spreadsheet"></i> Exportar
        </a>
        <button type="button" class="btn btn-warning btn-sm px-3 fw-bold text-dark" onclick="abrirModalPendientes()">
          <i class="bi bi-clock-history"></i> Pendientes <span id="badge-pendientes-comida" class="badge bg-danger ms-1">0</span>
        </button>
      </div>
      
    </div>
  </div>
</div>

<!-- Summary chips -->
<div id="summary-row" class="d-flex gap-3 mb-3 flex-wrap"></div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex align-items-center gap-2 bg-white py-3 px-4">
    <i class="bi bi-cup-hot text-warning"></i>
    <span class="fw-600">Registros de comedor</span>
    <span id="total-badge" class="badge bg-secondary ms-2">0</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th class="ps-4">Fecha/Hora</th>
            <th>Tipo</th>
            <th>Trabajador</th>
            <th>DNI</th>
            <th>Área</th>
            <th>Empresa</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody id="comedor-tbody">
          <tr><td colspan="7" class="text-center py-4 text-muted">Cargando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── MODAL: PENDIENTES DE COMIDA ── -->
<div class="modal fade" id="modalPendientesComida" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark py-2">
        <h6 class="modal-title fw-bold">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> Control de Comidas - Faltan Alimentarse
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body bg-light border-bottom py-2 px-3">
        <div class="row align-items-center">
          <div class="col-auto">
            <label for="inputFechaComidaModal" class="col-form-label fw-bold text-dark mb-0">Seleccionar Fecha:</label>
          </div>
          <div class="col-auto">
            <input type="date" id="inputFechaComidaModal" class="form-control form-control-sm" onchange="cargarPendientesComida()">
          </div>
          <div class="col text-muted small">
            <span>Visualizando registros para la fecha elegida.</span>
          </div>
        </div>
      </div>

      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover align-middle mb-0" style="font-size: 0.85rem;">
            <thead class="table-dark" style="color: #ffffff !important;">
              <tr style="color: #ffffff !important;">
                <th class="ps-3" style="color: #ffffff !important;">DNI</th>
                <th style="color: #ffffff !important;">Trabajador</th>
                <th style="color: #ffffff !important;">Área</th>
                <th class="text-center" style="color: #ffffff !important;">Turno</th>
                <th class="text-center" style="color: #ffffff !important;">Condición</th>
                <th class="text-center" style="width: 350px; color: #ffffff !important;">Acciones de Marcación</th>
              </tr>
            </thead>
            <tbody id="tbody-pendientes-comida">
              <!-- Se puebla dinámicamente -->
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer py-2 justify-content-between">
        <div class="footer-totales" style="display: flex; gap: 20px; font-weight: bold;">
          <span>🍳 Pendientes Desayuno: <span id="cntPendientesDesayuno">0</span></span>
          <span>🍲 Pendientes Almuerzo: <span id="cntPendientesAlmuerzo">0</span></span>
          <span>🌙 Pendientes Cena: <span id="cntPendientesCena">0</span></span>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
const BADGE_CLASS = {DESAYUNO:'badge-DESAYUNO',ALMUERZO:'badge-ALMUERZO',CENA:'badge-CENA'};
const LABEL = {DESAYUNO:'☕ Desayuno',ALMUERZO:'🍽️ Almuerzo',CENA:'🌙 Cena'};

async function loadAreas() {
  const r = await fetch('<?= BASE_URL ?>/api/index.php?action=areas');
  const j = await r.json();
  const sel = document.getElementById('f-area');

  sel.innerHTML = '<option value="">Todas las áreas</option>';

  j.data.forEach(a => {
    const o = document.createElement('option');
    o.value = a.id_area;
    o.textContent = a.nombre_area;
    sel.appendChild(o);
  });
}

async function loadComedor() {
  const desde = document.getElementById('f-desde').value;
  const hasta = document.getElementById('f-hasta').value;
  const area  = document.getElementById('f-area').value;
  const tipo  = document.getElementById('f-tipo').value;

  const params = new URLSearchParams({desde, hasta});
  if (area) params.append('area', area);
  if (tipo) params.append('tipo', tipo);

  const exportUrl = `<?= BASE_URL ?>/api/index.php?action=export/comedor&${params}`;
  document.getElementById('export-btn').href = exportUrl;

  const tbody = document.getElementById('comedor-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Cargando...</td></tr>';

  try {
    const r = await fetch(`<?= BASE_URL ?>/api/index.php?action=comedor&${params}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true'
      }
    });
    
    const textoCrudo = await r.text();
    
    if (textoCrudo.includes('<br />') || textoCrudo.includes('<b>')) {
       console.error("❌ ERROR FÍSICO EN TU BACKEND PHP:");
       console.log(textoCrudo);
       tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger fw-bold">Error interno de PHP/SQL. Abre la consola F12 en tu laptop para leerlo.</td></tr>`;
       return;
    }

    const j = JSON.parse(textoCrudo);
    if (!j.success) throw new Error(j.message);

    document.getElementById('total-badge').textContent = j.data.length;

    // Summary
    const counts = {DESAYUNO:0, ALMUERZO:0, CENA:0};
    j.data.forEach(row => { if (counts[row.tipo_evento] !== undefined) counts[row.tipo_evento]++; });
    const sr = document.getElementById('summary-row');
    sr.innerHTML = Object.entries(counts).map(([t,c]) =>
      `<span class="evt-badge ${BADGE_CLASS[t]}" style="font-size:.8rem;padding:.3rem .7rem">${LABEL[t]}: ${c}</span>`
    ).join('');

    if (!j.data.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">Sin registros en el período seleccionado</td></tr>';
      return;
    }

    tbody.innerHTML = j.data.map(row => {
      const dt    = row.fecha_hora ? row.fecha_hora.split(' ') : [];
      const fecha = dt[0] || '—';
      const hora  = dt[1] ? dt[1].substring(0,8) : '—';
      const idReg = row.id_comedor || row.id;
      
      return `<tr>
        <td class="ps-4">
          <span style="font-size:.85rem">${fecha}</span><br>
          <span style="font-family:monospace;font-size:.78rem;color:var(--gray-600)">${hora}</span>
        </td>
        <td><span class="evt-badge ${BADGE_CLASS[row.tipo_evento]||''}">${LABEL[row.tipo_evento]||row.tipo_evento}</span></td>
        <td><span class="fw-500">${row.nombre || '—'}</span></td> 
        <td><span style="font-family:monospace;font-size:.83rem">${row.dni || '—'}</span></td>
        <td><small>${row.nombre_area || '—'}</small></td>
        <td><small class="text-muted">${row.empresa||'—'}</small></td>
        <td class="text-center">
          <button class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar registro" onclick="eliminarMarcacionComedor(${idReg})">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>`;
    }).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Error en JS de la vista: ${e.message}</td></tr>`;
    console.error("Error capturado en el catch:", e);
  }
}

// ── LÓGICA DE PENDIENTES DE COMIDA ──

function abrirModalPendientes() {
  const inputFechaModal = document.getElementById('inputFechaComidaModal');

  if (inputFechaModal && !inputFechaModal.value) {
    const hoy = new Date();
    const yyyy = hoy.getFullYear();
    const mm = String(hoy.getMonth() + 1).padStart(2, '0');
    const dd = String(hoy.getDate()).padStart(2, '0');
    inputFechaModal.value = `${yyyy}-${mm}-${dd}`;
  }

  cargarPendientesComida();

  const modalEl = document.getElementById('modalPendientesComida');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
}

async function cargarPendientesComida() {
  const inputFechaModal = document.getElementById('inputFechaComidaModal');
  let fechaSeleccionada = inputFechaModal ? inputFechaModal.value : '';

  if (!fechaSeleccionada) {
    const hoy = new Date();
    const yyyy = hoy.getFullYear();
    const mm = String(hoy.getMonth() + 1).padStart(2, '0');
    const dd = String(hoy.getDate()).padStart(2, '0');
    fechaSeleccionada = `${yyyy}-${mm}-${dd}`;
    if (inputFechaModal) inputFechaModal.value = fechaSeleccionada;
  }

  try {
    const response = await fetch(`<?= BASE_URL ?>/api/index.php?resource=faltan_comer&action=faltan_comer&fecha=${fechaSeleccionada}`);
    const result = await response.json();

    if (!result.success) {
      alert('Error al cargar datos: ' + result.message);
      return;
    }

    const data = result.data;
    const trabajadores = data.trabajadores || [];

    const tbody = document.getElementById('tbody-pendientes-comida');
    tbody.innerHTML = '';

    if (trabajadores.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="text-center text-muted py-3">
            No hay programación o registros para la fecha seleccionada (${fechaSeleccionada}).
          </td>
        </tr>`;
      document.getElementById('cntPendientesDesayuno').innerText = 0;
      document.getElementById('cntPendientesAlmuerzo').innerText = 0;
      document.getElementById('cntPendientesCena').innerText = 0;
      return;
    }

    let cntDesayuno = 0;
    let cntAlmuerzo = 0;
    let cntCena = 0;

    trabajadores.forEach(t => {
      const tieneDesayuno = parseInt(t.desayuno_marcado, 10) === 1;
      const tieneAlmuerzo = parseInt(t.almuerzo_marcado, 10) === 1;
      const tieneCena     = parseInt(t.cena_marcada, 10) === 1;

      if (!tieneDesayuno) cntDesayuno++;
      if (!tieneAlmuerzo) cntAlmuerzo++;
      if (!tieneCena)     cntCena++;

      const btnDesayunoClass = tieneDesayuno ? 'btn-success' : 'btn-outline-secondary';
      const btnAlmuerzoClass = tieneAlmuerzo ? 'btn-success' : 'btn-outline-secondary';
      const btnCenaClass     = tieneCena     ? 'btn-success' : 'btn-outline-secondary';
      const btnTodosClass    = (tieneDesayuno && tieneAlmuerzo && tieneCena) ? 'btn-success' : 'btn-outline-primary';

      const nombreLimpio = (t.nombre_completo || '').replace(/'/g, "\\'");
      const tresComidas = 'DESAYUNO,ALMUERZO,CENA';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="ps-3">${t.dni || ''}</td>
        <td><strong>${t.nombre_completo || ''}</strong></td>
        <td>${t.nombre_area || '-'}</td>
        <td class="text-center"><span class="badge bg-info text-dark">${t.turno_programado || ''}</span></td>
        <td class="text-center"><span class="badge bg-secondary">${t.condicion || ''}</span></td>
        <td class="text-center">
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn ${btnDesayunoClass}" 
                    onclick="gestionarComidaModal(${t.id_trabajador}, '${nombreLimpio}', 'DESAYUNO', ${tieneDesayuno ? 1 : 0}, '${fechaSeleccionada}')">
              ${tieneDesayuno ? '☕' : '☕'}
            </button>
            <button type="button" class="btn ${btnAlmuerzoClass}" 
                    onclick="gestionarComidaModal(${t.id_trabajador}, '${nombreLimpio}', 'ALMUERZO', ${tieneAlmuerzo ? 1 : 0}, '${fechaSeleccionada}')">
              ${tieneAlmuerzo ? '🍲' : '🍲'}
            </button>
            <button type="button" class="btn ${btnCenaClass}" 
                    onclick="gestionarComidaModal(${t.id_trabajador}, '${nombreLimpio}', 'CENA', ${tieneCena ? 1 : 0}, '${fechaSeleccionada}')">
              ${tieneCena ? '🌙' : '🌙'}
            </button>
            <button type="button" class="btn ${btnTodosClass}" 
                    onclick="marcarComidaManual(${t.id_trabajador}, '${nombreLimpio}', '${tresComidas}', '${fechaSeleccionada}')">
              ⚡ Marcar 3
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    document.getElementById('cntPendientesDesayuno').innerText = cntDesayuno;
    document.getElementById('cntPendientesAlmuerzo').innerText = cntAlmuerzo;
    document.getElementById('cntPendientesCena').innerText = cntCena;

  } catch (error) {
    console.error('Error al obtener pendientes:', error);
  }
}

// Gestiona si debe registrar o eliminar según si ya está marcado
async function gestionarComidaModal(idTrabajador, nombreCompleto, tipoEvento, yaMarcado, fechaSeleccionada) {
  if (yaMarcado) {
    
    try {
      const response = await fetch('<?= BASE_URL ?>/api/index.php?action=eliminar_comida_manual', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id_trabajador: idTrabajador,
          tipo_evento: tipoEvento,
          fecha: fechaSeleccionada
        })
      });

      const result = await response.json();
      if (result.success) {
        await cargarPendientesComida();
        loadComedor();
      } else {
        alert('Error al deseleccionar: ' + (result.message || 'Error desconocido'));
      }
    } catch (error) {
      console.error('Error al eliminar marcación:', error);
      alert('Error de conexión al intentar eliminar.');
    }
  } else {
    await marcarComidaManual(idTrabajador, nombreCompleto, tipoEvento, fechaSeleccionada);
  }
}

async function marcarComidaManual(idTrabajador, nombreCompleto, tipoEvento, fechaSeleccionada = null) {
  if (!fechaSeleccionada) {
    const inputFechaModal = document.getElementById('inputFechaComidaModal');
    fechaSeleccionada = inputFechaModal ? inputFechaModal.value : new Date().toISOString().split('T')[0];
  }

  try {
    const response = await fetch('<?= BASE_URL ?>/api/index.php?action=marcar_comida_manual', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id_trabajador: idTrabajador,
        tipo_evento: tipoEvento,
        fecha: fechaSeleccionada
      })
    });

    const result = await response.json();

    if (result.success) {
      await cargarPendientesComida();
      loadComedor();
    } else {
      alert('Error al registrar marcación: ' + (result.message || 'Error desconocido'));
    }
  } catch (error) {
    console.error('Error al guardar marcación manual:', error);
    alert('Ocurrió un error de conexión al intentar registrar.');
  }
}

// Eliminar directamente desde el Historial Principal por ID
async function eliminarMarcacionComedor(idComedor) {
  if (!confirm('¿Estás seguro de que deseas eliminar este registro de comedor?')) return;

  try {
    const response = await fetch('<?= BASE_URL ?>/api/index.php?action=eliminar_comedor', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_comedor: idComedor })
    });

    const result = await response.json();
    if (result.success) {
      loadComedor();
      cargarPendientesComida();
    } else {
      alert('Error al eliminar: ' + (result.message || 'Error desconocido'));
    }
  } catch (e) {
    console.error('Error al eliminar registro:', e);
    alert('Error de conexión al intentar eliminar.');
  }
}

// Inicialización de la vista
loadAreas().then(() => {
  loadComedor();
  cargarPendientesComida(); 
});
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>