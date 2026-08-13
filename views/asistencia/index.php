<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$pageTitle = 'Asistencia Laboral';
$activeNav = 'asistencia';
require_once ROOT_PATH . 'views/layouts/header.php';
?>

<style>
.horas-chip {
  display: inline-block;
  font-family: monospace;
  font-size: .8rem;
  font-weight: 600;
  padding: .2rem .5rem;
  border-radius: 5px;
}
.horas-extra   { background: #dcfce7; color: #166534; }
.horas-deficit { background: #fee2e2; color: #991b1b; }
.horas-ok      { background: #dbeafe; color: #1e40af; }
.turno-badge   { font-size: 0.7rem; font-weight: bold; padding: 1px 4px; border-radius: 3px; }
.badge-t1      { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.badge-t2      { background-color: #faf5ff; color: #6b21a8; border: 1px solid #f3e8ff; }
</style>

<!-- Tarjetas de Resumen de Horas (EN CABEZADO SOLICITADO) -->
<div id="resumen-container" class="row g-3 mb-4" style="display: none;">
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm bg-primary bg-opacity-10 h-100">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle bg-primary bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
          <i class="bi bi-clock-history text-primary fs-4"></i>
        </div>
        <div>
          <span class="text-muted small d-block">Total Horas Trabajadas</span>
          <span id="resumen-trabajadas" class="fs-4 fw-bold text-primary">0.00h</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100" id="card-balance">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center" id="icon-container-balance" style="width: 48px; height: 48px;">
          <i class="bi bi-calculator-fill fs-4" id="icon-balance"></i>
        </div>
        <div>
          <span class="text-muted small d-block" id="label-balance">Diferencia Horas</span>
          <span id="resumen-balance" class="fs-4 fw-bold">0.00h</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body py-3 px-4">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Desde</label>
        <input type="date" id="f-desde" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Hasta</label>
        <input type="date" id="f-hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1 small fw-600">Área</label>
        <select id="f-area" class="form-select form-select-sm">
          <option value="">Todas las áreas</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label mb-1 small fw-600">Trabajador</label>
        <select id="f-trabajador" class="form-select form-select-sm">
          <option value="">Todos los trabajadores</option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2 justify-content-md-end">
        <button class="btn btn-primary btn-sm px-3" onclick="loadAsistencia()">
          <i class="bi bi-search"></i> Buscar
        </button>
        <a id="export-btn" href="#" class="btn btn-success btn-sm px-3">
          <i class="bi bi-file-earmark-spreadsheet"></i> Excel
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex align-items-center gap-2 bg-white py-3 px-4">
    <i class="bi bi-clock-history text-primary"></i>
    <span class="fw-600">Registro de asistencia laboral</span>
    <span id="total-badge" class="badge bg-secondary ms-2">0</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th class="ps-4">Fecha / Turno</th>
            <th>Trabajador</th>
            <th>Área</th>
            <th class="text-center">Ingreso</th>
            <th class="text-center">S. Break</th>
            <th class="text-center">I. Break</th>
            <th class="text-center">Salida</th>
            <th class="text-center">H. Netas</th>
            <th class="text-center">Prog.</th>
            <th class="text-center">Diferencia</th>
            <th>Observación / Motivo</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody id="asist-tbody">
          <tr><td colspan="12" class="text-center py-4 text-muted">Cargando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal de Edición de Asistencia -->
<div class="modal fade" id="modalEditarAsistencia" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fs-6 fw-bold">
          <i class="bi bi-pencil-square me-2"></i>Editar Asistencia (Fuerza Mayor)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-id-trabajador">
        <input type="hidden" id="edit-fecha">

        <div class="mb-3 p-2 bg-light rounded border">
          <div class="fw-bold" id="edit-nombre-trabajador">—</div>
          <small class="text-muted" id="edit-fecha-label">—</small>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small fw-600 mb-1">Ingreso</label>
            <input type="time" id="edit-ingreso" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-600 mb-1">Salida Break</label>
            <input type="time" id="edit-s-break" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-600 mb-1">Ingreso Break</label>
            <input type="time" id="edit-i-break" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-600 mb-1">Salida Trabajo</label>
            <input type="time" id="edit-salida" class="form-control form-control-sm">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label small fw-600 mb-1">Motivo / Observación</label>
          <textarea id="edit-observacion" class="form-control form-control-sm text-uppercase" rows="2" 
                    placeholder="EJ: TRABAJÓ MEDIO TURNO POR MOTIVOS MEDICOS" 
                    oninput="this.value = this.value.toUpperCase()"></textarea>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="guardarEdicionAsistencia()">
          <i class="bi bi-save"></i> Guardar Cambios
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function fmtHora(dt) {
  if (!dt || dt === '—') return '<span class="text-muted">—</span>';
  let horaLimpia = dt.includes(' ') ? dt.split(' ')[1] : dt;
  return `<span style="font-family:monospace;font-size:.82rem">${horaLimpia.substring(0, 5)}</span>`;
}

function fmtHoras(h, tipo) {
  if (h === null || h === undefined || h === '' || h === '—') return '<span class="text-muted">—</span>';
  const cls = tipo === 'extra' ? 'horas-extra' : (tipo === 'deficitaria' ? 'horas-deficit' : 'horas-ok');
  return `<span class="horas-chip ${cls}">${h}h</span>`;
}

function fmtDiff(d, tipo) {
  if (d === null || d === undefined || d === '—') return '<span class="text-muted">—</span>';
  const sign  = tipo === 'extra' ? '+' : '-';
  const color = tipo === 'extra' ? '#166534' : '#991b1b';
  return `<span style="font-family:monospace;font-size:.82rem;font-weight:600;color:${color}">${sign}${Math.abs(d)}h</span>`;
}

async function loadAreasYTrabajadores() {
  try {
    const r = await fetch('<?= BASE_URL ?>/api/index.php?action=trabajadores', {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true' }
    });
    if (!r.ok) throw new Error(`Error ${r.status}`);
    const j = await r.json();
    if (!j.success || !j.data) return;

    const areasUnicas = {};
    j.data.forEach(t => { if(t.id_area && t.nombre_area) areasUnicas[t.id_area] = t.nombre_area; });

    const selArea = document.getElementById('f-area');
    selArea.innerHTML = '<option value="">Todas las áreas</option>';
    Object.entries(areasUnicas).forEach(([id, nombre]) => {
      const o = document.createElement('option');
      o.value = id; o.textContent = nombre;
      selArea.appendChild(o);
    });

    const selTrabajador = document.getElementById('f-trabajador');
    selTrabajador.innerHTML = '<option value="">Todos los trabajadores</option>';
    const trabajadoresOrdenados = j.data.sort((a, b) => a.nombre_completo.localeCompare(b.nombre_completo));
    trabajadoresOrdenados.forEach(t => {
      const o = document.createElement('option');
      o.value = t.id_trabajador;
      o.textContent = `${t.nombre_completo} (${t.dni})`;
      selTrabajador.appendChild(o);
    });
  } catch (e) {
    console.error("Error al cargar filtros dinámicos:", e);
  }
}

function renderResumen(resumen, registros) {
  const container = document.getElementById('resumen-container');
  if (!resumen) { container.style.display = 'none'; return; }
  
  container.style.display = 'flex';
  
  // Calcular total horas trabajadas
  let totalTrabajadas = 0;
  if(registros && registros.length > 0) {
     totalTrabajadas = registros.reduce((acc, r) => acc + parseFloat(r.horas_netas || 0), 0);
  }
  document.getElementById('resumen-trabajadas').textContent = `${totalTrabajadas.toFixed(2)}h`;
  
  const balanceNeto = resumen.balance_neto || 0;
  const balanceText = document.getElementById('resumen-balance');
  const cardBalance = document.getElementById('card-balance');
  const iconContainer = document.getElementById('icon-container-balance');
  const iconBalance = document.getElementById('icon-balance');
  const labelBalance = document.getElementById('label-balance');
  
  if (balanceNeto > 0) {
    // Horas Extras (Verde)
    balanceText.textContent = `+${balanceNeto.toFixed(2)}h`;
    balanceText.className = "fs-4 fw-bold text-success";
    cardBalance.className = "card border-0 shadow-sm bg-success bg-opacity-10 h-100";
    iconContainer.className = "rounded-circle bg-success bg-opacity-25 d-flex align-items-center justify-content-center";
    iconBalance.className = "bi bi-plus-circle-fill text-success fs-4";
    labelBalance.textContent = "Horas Extras Totales";
  } else if (balanceNeto < 0) {
    // Horas Faltantes (Rojo con negativo)
    balanceText.textContent = `${balanceNeto.toFixed(2)}h`;
    balanceText.className = "fs-4 fw-bold text-danger";
    cardBalance.className = "card border-0 shadow-sm bg-danger bg-opacity-10 h-100";
    iconContainer.className = "rounded-circle bg-danger bg-opacity-25 d-flex align-items-center justify-content-center";
    iconBalance.className = "bi bi-dash-circle-fill text-danger fs-4";
    labelBalance.textContent = "Horas Debe / Faltantes";
  } else {
    // Exacto (Sin diferencias)
    balanceText.textContent = "0.00h";
    balanceText.className = "fs-4 fw-bold text-secondary";
    cardBalance.className = "card border-0 shadow-sm bg-light h-100";
    iconContainer.className = "rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center";
    iconBalance.className = "bi bi-check-circle-fill text-secondary fs-4";
    labelBalance.textContent = "Diferencia de Horas";
  }
}

async function loadAsistencia() {
  const desde = document.getElementById('f-desde').value;
  const hasta = document.getElementById('f-hasta').value;
  const area  = document.getElementById('f-area').value;
  const trabajador = document.getElementById('f-trabajador').value; 

  const params = new URLSearchParams({desde, hasta});
  if (area) params.append('area', area);
  if (trabajador) params.append('trabajador', trabajador); 

  document.getElementById('export-btn').href =
    `<?= BASE_URL ?>/api/index.php?action=export/asistencia&${params}&_t=${Date.now()}`;

  const tbody = document.getElementById('asist-tbody');
  tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-muted">Cargando...</td></tr>';

  try {
    const r = await fetch(`<?= BASE_URL ?>/api/index.php?action=asistencia&${params}&_t=${Date.now()}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true' }
    });
    
    const textoCrudo = await r.text();
    if (textoCrudo.includes('<br />') || textoCrudo.includes('<b>')) {
       tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-danger fw-bold">Error interno de SQL/Vista.</td></tr>';
       return;
    }

    const j = JSON.parse(textoCrudo);
    if (!j.success) throw new Error(j.message);

    const registros = j.data.registros || [];
    const resumen = j.data.resumen || null;

    document.getElementById('total-badge').textContent = registros.length;
    renderResumen(resumen, registros);

    if (!registros.length) {
      tbody.innerHTML = '<tr><td colspan="12" class="text-center py-5 text-muted">Sin registros en el período</td></tr>';
      return;
    }

    const mapaConteo = {};
    registros.forEach(row => {
      const llave = `${row.fecha}_${row.id_trabajador || row.dni}`;
      mapaConteo[llave] = (mapaConteo[llave] || 0) + 1;
    });

    const trackerTurno = {};

    tbody.innerHTML = registros.map(row => {
      const netas = row.horas_netas !== undefined ? row.horas_netas : 0;
      const diff  = row.diferencia !== undefined ? row.diferencia : 0;
      
      const textoObs = (
        String(row.observacion_automatica || '') + ' ' + 
        String(row.observacion || '') + ' ' + 
        String(row.tipo_turno || '') + ' ' + 
        String(row.turno || '')
      ).toLowerCase();

      let horaIngresoLimpia = 0;
      if (row.hora_ingreso && row.hora_ingreso !== '—') {
         const parteHora = row.hora_ingreso.includes(' ') ? row.hora_ingreso.split(' ')[1] : row.hora_ingreso;
         horaIngresoLimpia = parseInt(parteHora.split(':')[0] || 0);
      }

      const esTurnoNocheReal = textoObs.includes('noche') || horaIngresoLimpia >= 18;
      let tagTurnoNoche = esTurnoNocheReal ? `<br><span class="badge bg-dark text-white" style="font-size:0.68rem; padding:2px 5px; border-radius:4px;"><i class="bi bi-moon-stars-fill text-warning me-1"></i> Turno Noche</span>` : '';

      const llave = `${row.fecha}_${row.id_trabajador || row.dni}`;
      const tieneDobleTurno = mapaConteo[llave] > 1;
      
      let badgeTurno = '';
      if (tieneDobleTurno) {
        trackerTurno[llave] = (trackerTurno[llave] || 0) + 1;
        const nTurno = trackerTurno[llave];
        badgeTurno = (esTurnoNocheReal || nTurno === 2) 
          ? `<br><span class="turno-badge badge-t2">🌙 T. Noche (J${nTurno})</span>`
          : `<br><span class="turno-badge badge-t1">☀️ T. Mañana (J${nTurno})</span>`;
      }

      const rowJson = encodeURIComponent(JSON.stringify(row));
      const obsTexto = row.observacion ? `<span class="badge bg-light text-dark border text-wrap text-start">${row.observacion}</span>` : '<span class="text-muted small">—</span>';

      return `<tr>
        <td class="ps-4">
          <span style="font-size:.85rem; font-weight:600">${row.fecha || '—'}</span>
          ${badgeTurno}
          ${!badgeTurno ? tagTurnoNoche : ''}
        </td>
        <td>
          <span class="fw-500">${row.nombre_completo || row.nombre}</span><br>
          <small class="text-muted" style="font-family:monospace">${row.dni || '—'}</small>
        </td>
        <td><small>${row.nombre_area || '—'}</small></td>
        <td class="text-center">${fmtHora(row.hora_ingreso)}</td>
        <td class="text-center">${fmtHora(row.hora_salida_break)}</td>
        <td class="text-center">${fmtHora(row.hora_ingreso_break)}</td>
        <td class="text-center">${fmtHora(row.hora_salida_trabajo)}</td>
        <td class="text-center">${fmtHoras(netas, row.tipo_diferencia)}</td>
        <td class="text-center"><span class="horas-chip horas-ok">${row.horas_programadas}h</span></td>
        <td class="text-center">${fmtDiff(diff, row.tipo_diferencia)}</td>
        <td>${obsTexto}</td>
        <td class="text-center ps-2 pe-3">
          <button class="btn btn-outline-primary btn-sm py-0 px-2" title="Editar asistencia por fuerza mayor" onclick="abrirModalEdicion(JSON.parse(decodeURIComponent('${rowJson}')))">
            <i class="bi bi-pencil-fill"></i>
          </button>
        </td>
      </tr>`;
    }).join('');
    
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-danger">Error al procesar la lista: ${e.message}</td></tr>`;
  }
}

function obtenerHoraSimple(dt) {
  if (!dt || dt === '—') return '';
  return dt.includes(' ') ? dt.split(' ')[1].substring(0, 5) : dt.substring(0, 5);
}

function abrirModalEdicion(row) {
  document.getElementById('edit-id-trabajador').value = row.id_trabajador;
  document.getElementById('edit-fecha').value = row.fecha;
  
  document.getElementById('edit-nombre-trabajador').textContent = row.nombre_completo || row.nombre;
  document.getElementById('edit-fecha-label').textContent = `Fecha: ${row.fecha} | DNI: ${row.dni}`;

  document.getElementById('edit-ingreso').value = obtenerHoraSimple(row.hora_ingreso);
  document.getElementById('edit-s-break').value = obtenerHoraSimple(row.hora_salida_break);
  document.getElementById('edit-i-break').value = obtenerHoraSimple(row.hora_ingreso_break);
  document.getElementById('edit-salida').value = obtenerHoraSimple(row.hora_salida_trabajo);
  document.getElementById('edit-observacion').value = (row.observacion || '').toUpperCase();

  const modal = new bootstrap.Modal(document.getElementById('modalEditarAsistencia'));
  modal.show();
}

async function guardarEdicionAsistencia() {
  const idTrabajador = document.getElementById('edit-id-trabajador').value;
  const fecha        = document.getElementById('edit-fecha').value;
  const obs          = document.getElementById('edit-observacion').value.trim().toUpperCase();

  const payload = {
    id_trabajador: idTrabajador,
    fecha: fecha,
    hora_ingreso: document.getElementById('edit-ingreso').value,
    hora_salida_break: document.getElementById('edit-s-break').value,
    hora_ingreso_break: document.getElementById('edit-i-break').value,
    hora_salida_trabajo: document.getElementById('edit-salida').value,
    observacion: obs
  };

  try {
    const response = await fetch('<?= BASE_URL ?>/api/index.php?action=asistencia/editar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    });

    const textoCrudo = await response.text();
    if (textoCrudo.includes('<br />') || textoCrudo.includes('<b>')) {
      alert("Error en el servidor backend PHP. Revisa la consola.");
      return;
    }

    const result = JSON.parse(textoCrudo);
    if (result.success) {
      const modalEl = document.getElementById('modalEditarAsistencia');
      const modal = bootstrap.Modal.getInstance(modalEl);
      modal.hide();
      loadAsistencia();
    } else {
      alert("Error: " + (result.message || "No se pudo actualizar el registro."));
    }
  } catch (e) {
    alert("Error al procesar la respuesta: " + e.message);
  }
}

loadAreasYTrabajadores().then(() => loadAsistencia());
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>