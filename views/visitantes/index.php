<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$pageTitle = 'Visitantes';
$activeNav = 'visitantes';
require_once ROOT_PATH . 'views/layouts/header.php';
?>

<style>
.vis-card {
  background: #fff; border: 1px solid var(--gray-200);
  border-radius: 12px; padding: 1rem 1.1rem;
  transition: box-shadow .15s, border-color .15s; cursor: pointer;
}
.vis-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); border-color: #93c5fd; }
.vis-avatar {
  width: 44px; height: 44px; border-radius: 10px;
  background: var(--primary-light); display: flex;
  align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;
}
.vis-name     { font-weight: 600; font-size: .9rem; }
.vis-empresa { font-size: .78rem; color: var(--gray-600); }
.vis-dni     { font-size: .72rem; font-family: monospace; color: var(--gray-600); }

.comida-pip {
  display: inline-block; width: 8px; height: 8px;
  border-radius: 50%; margin-right: 2px;
  background: var(--gray-200);
}
.comida-pip.done { background: var(--success); }

/* Historial modal */
#modal-historial .evt-row {
  display: flex; align-items: center; gap: .75rem;
  padding: .55rem 0; border-bottom: 1px solid var(--gray-100);
  font-size: .85rem;
}
#modal-historial .evt-row:last-child { border-bottom: none; }
</style>

<!-- Toolbar -->
<div class="d-flex gap-3 align-items-center mb-4 flex-wrap">
  <div class="input-group" style="max-width:360px">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
    <input type="text" id="search-q" class="form-control border-start-0"
           placeholder="Buscar por nombre, DNI o empresa..." oninput="loadVisitantes()">
  </div>
  <div class="ms-auto d-flex gap-2">
    <div class="d-flex align-items-center gap-2 small text-muted">
      <span id="total-count" class="fw-600 text-dark">—</span> visitantes
    </div>
    <button class="btn btn-success fw-600"
            data-bs-toggle="modal" data-bs-target="#modal-form" onclick="openNew()">
      <i class="bi bi-person-plus-fill"></i> Nuevo visitante
    </button>
  </div>
</div>

<!-- Stats rápidas del día -->
<div id="stats-hoy" class="row g-2 mb-4"></div>

<!-- Grid de visitantes -->
<div id="vis-grid" class="row g-2">
  <div class="col-12 text-center py-5 text-muted">
    <i class="bi bi-arrow-clockwise fs-3 d-block mb-2 opacity-25"></i> Cargando...
  </div>
</div>

<!-- ─── Modal: crear / editar visitante ─── -->
<div class="modal fade" id="modal-form" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15)">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <h5 class="modal-title fw-700" id="modal-form-title">Nuevo visitante</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 pb-4">
        <input type="hidden" id="f-id">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small fw-600">Nombre completo *</label>
            <input type="text" id="f-nombre" class="form-control"
                   placeholder="Apellidos y nombres">
          </div>
          <div class="col-12">
            <label class="form-label small fw-600">Empresa / Institución *</label>
            <input type="text" id="f-empresa" class="form-control"
                   placeholder="Empresa u organización">
          </div>
          
        </div>
      </div>
      <div class="modal-footer border-0 pt-0 px-4 pb-4 d-flex justify-content-between">
        <button class="btn btn-outline-danger" id="btn-modal-eliminar" style="display:none" onclick="eliminarDesdeModal()">
          <i class="bi bi-trash"></i> Eliminar
        </button>
        <div class="ms-auto">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-success fw-600 px-4" onclick="guardar()">
            <i class="bi bi-check-lg"></i> Guardar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Modal: QR del visitante ─── -->
<div class="modal fade" id="modal-qr" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15)">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <h5 class="modal-title fw-700">Código QR</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center px-4 pb-4">
        <div id="qr-vis-nombre" class="fw-700 mb-1"></div>
        <div id="qr-vis-empresa" class="text-muted small mb-3"></div>
        <div id="qr-container"
             style="display:inline-block;background:#fff;padding:1rem;
                    border-radius:12px;border:1px solid var(--gray-200)" class="mb-3">
        </div>
        <div id="qr-code-txt" class="text-muted small mb-3"
             style="font-family:monospace"></div>
        <div class="d-flex gap-2 justify-content-center">
          <button class="btn btn-outline-primary btn-sm" onclick="imprimirQR()">
            <i class="bi bi-printer"></i> Imprimir
          </button>
          <button class="btn btn-outline-secondary btn-sm" onclick="copiarCodigo()">
            <i class="bi bi-clipboard"></i> Copiar código
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Modal: historial de un visitante ─── -->
<div class="modal fade" id="modal-historial" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15)">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <div>
          <h5 class="modal-title fw-700" id="hist-nombre">Historial</h5>
          <small class="text-muted" id="hist-empresa"></small>
        </div>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 pb-4">
        <div id="hist-body">
          <div class="text-center py-4 text-muted">Cargando historial...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QRCode.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const EVT_LABELS = {
  DESAYUNO:'☕ Desayuno', ALMUERZO:'🍽️ Almuerzo', CENA:'🌙 Cena',
  INGRESO:'🟢 Ingreso', SALIDA:'🔴 Salida',
};
const EVT_BADGE = {
  DESAYUNO:'badge-DESAYUNO', ALMUERZO:'badge-ALMUERZO', CENA:'badge-CENA',
  INGRESO:'badge-INGRESO', SALIDA:'badge-SALIDA_TRABAJO',
};

let currentQRCode = null;
let currentQRId   = null;
let visitantesData = [];

// 1. Cargar visitantes
async function loadVisitantes() {
  const query = document.getElementById('search-q')?.value.trim() || '';
  
  try {
    const res = await fetch(`<?= BASE_URL ?>/api/index.php?action=visitantes&q=${encodeURIComponent(query)}`, {
      headers: { 
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true' 
      }
    });
    
    const json = await res.json();
    
    if (json.success) {
      visitantesData = json.data || [];
      
      const totalEl = document.getElementById('total-count');
      if (totalEl) totalEl.textContent = visitantesData.length;

      renderGrid(visitantesData);
    } else {
      console.error('Error al cargar visitantes:', json.message);
    }
  } catch (e) {
    console.error('Error de red al cargar visitantes:', e);
  }
}

// 2. Renderizar grid
function renderGrid(lista) {
  const container = document.getElementById('vis-grid');
  if (!container) return;

  if (lista.length === 0) {
    container.innerHTML = `
      <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
        No se encontraron visitantes.
      </div>`;
    return;
  }

  container.innerHTML = lista.map(v => {
    const jsonVis = encodeURIComponent(JSON.stringify(v));
    const nomEscapado = (v.nombre || '').replace(/'/g, "\\'");
    const empEscapada = (v.empresa || '').replace(/'/g, "\\'");

    return `
      <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card h-100 border-0 shadow-sm rounded-4 vis-card p-3 position-relative"
             onclick="verHistorial(${v.id_visitante}, '${nomEscapado}', '${empEscapada}')">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="fw-700 mb-1 text-dark">${v.nombre}</h6>
              <span class="badge bg-light text-dark border fw-500 mb-2">${v.empresa}</span>
              <div class="small text-muted">
                <i class="bi bi-calendar me-1"></i>
                Días visitados: ${v.dias_visitados}
              </div>
            </div>
            
            <div class="d-flex flex-column gap-1">
              <button class="btn btn-xs btn-outline-secondary" onclick="event.stopPropagation(); abrirQR(${v.id_visitante}, '${nomEscapado}', '${empEscapada}')" data-bs-toggle="modal" data-bs-target="#modal-qr" title="Ver QR">
                <i class="bi bi-qr-code"></i>
              </button>
              <button class="btn btn-xs btn-outline-primary" onclick="event.stopPropagation(); editVisitante(JSON.parse(decodeURIComponent('${jsonVis}')))" data-bs-toggle="modal" data-bs-target="#modal-form" title="Editar">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); eliminarVisitante(${v.id_visitante}, '${nomEscapado}')" title="Eliminar">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// 3. Formulario Modal: Crear / Editar
function openNew() {
  document.getElementById('modal-form-title').textContent = 'Nuevo visitante';
  document.getElementById('f-id').value = '';
  document.getElementById('f-nombre').value = '';
  document.getElementById('f-empresa').value = '';
  
  const btnEliminar = document.getElementById('btn-modal-eliminar');
  if (btnEliminar) btnEliminar.style.display = 'none';
}

function editVisitante(v) {
  document.getElementById('modal-form-title').textContent = 'Editar visitante';
  document.getElementById('f-id').value = v.id_visitante;
  document.getElementById('f-nombre').value = v.nombre || '';
  document.getElementById('f-empresa').value = v.empresa || '';
  

  const btnEliminar = document.getElementById('btn-modal-eliminar');
  if (btnEliminar) btnEliminar.style.display = 'inline-block';
}

// 4. Guardar (Crear / Editar)
async function guardar() {
  const id = document.getElementById('f-id').value;
  const payload = {
    nombre:  document.getElementById('f-nombre').value.trim().toUpperCase(),
    empresa: document.getElementById('f-empresa').value.trim().toUpperCase(),
  };

  if (!payload.nombre || !payload.empresa) {
    alert("Por favor complete los campos obligatorios (*)");
    return;
  }

  const url = id 
    ? `<?= BASE_URL ?>/api/index.php?action=visitantes&sub=${id}` 
    : `<?= BASE_URL ?>/api/index.php?action=visitantes`;
  const method = id ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, {
      method,
      headers: { 
        'Content-Type': 'application/json', 
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true' 
      },
      body: JSON.stringify(payload)
    });
    
    const textoCrudo = await r.text();
    if (textoCrudo.includes('<br />') || textoCrudo.includes('<b>')) {
      alert("Error en el servidor PHP al guardar. Revisa la consola.");
      console.error("Error backend:", textoCrudo);
      return;
    }

    const j = JSON.parse(textoCrudo);

    if (j.success) {
      const modalEl = document.getElementById('modal-form');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      
      await loadVisitantes();
      alert(j.message || "Guardado exitosamente");
    } else {
      alert("Error: " + (j.message || "No se pudo realizar la operación"));
    }
  } catch (e) {
    console.error("Error al guardar:", e);
    alert("Error de comunicación con el servidor");
  }
}

// 5. Eliminar visitante
async function eliminarVisitante(id, nombre = '') {
  const msg = nombre ? `¿Estás seguro de eliminar a "${nombre}"?` : '¿Estás seguro de eliminar este visitante?';
  if (!confirm(`${msg}\nEsta acción no se podrá deshacer.`)) return;

  try {
    const res = await fetch(`<?= BASE_URL ?>/api/index.php?action=visitantes&sub=${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true'
      }
    });

    const data = await res.json();

    if (data.success) {
      const modalEl = document.getElementById('modal-form');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();

      await loadVisitantes();
      alert(data.message || 'Visitante eliminado correctamente');
    } else {
      alert('Error: ' + (data.message || 'No se pudo eliminar'));
    }
  } catch (e) {
    console.error('Error al eliminar:', e);
    alert('Error al conectar con el servidor.');
  }
}

function eliminarDesdeModal() {
  const id = document.getElementById('f-id').value;
  const nombre = document.getElementById('f-nombre').value;
  if (id) eliminarVisitante(id, nombre);
}

// 6. Estadísticas del día
async function loadStatsHoy() {
  try {
    const r = await fetch('<?= BASE_URL ?>/api/index.php?action=dashboard', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true'
      }
    });

    if (!r.ok) throw new Error(`Error ${r.status}`);
    const j = await r.json();
    if (!j.success) return;
    
    const d = j.data;
    const statsContainer = document.getElementById('stats-hoy');
    if (!statsContainer) return;

    statsContainer.innerHTML = `
      <div class="col-4">
        <div class="card border p-2 text-center" style="border-radius:10px">
          <small class="text-muted d-block" style="font-size:.7rem">DESAYUNOS</small>
          <span class="fw-700 text-warning" style="font-size:1.1rem">${d.desayunos_visitantes || 0}</span>
        </div>
      </div>
      <div class="col-4">
        <div class="card border p-2 text-center" style="border-radius:10px">
          <small class="text-muted d-block" style="font-size:.7rem">ALMUERZOS</small>
          <span class="fw-700 text-primary" style="font-size:1.1rem">${d.almuerzos_visitantes || 0}</span>
        </div>
      </div>
      <div class="col-4">
        <div class="card border p-2 text-center" style="border-radius:10px">
          <small class="text-muted d-block" style="font-size:.7rem">CENAS</small>
          <span class="fw-700 text-success" style="font-size:1.1rem">${d.cenas_visitantes || 0}</span>
        </div>
      </div>
    `;

  } catch (e) {
    console.error("Error al cargar estadísticas rápidas:", e);
  }
}

// 7. Generación e impresión de QR
function abrirQR(id, nombre, empresa) {
  currentQRId = id;
  document.getElementById('qr-vis-nombre').textContent  = nombre;
  document.getElementById('qr-vis-empresa').textContent = '🏢 ' + empresa;
  
  const codigoString = String(id);
  document.getElementById('qr-code-txt').textContent = 'Código: ' + codigoString;
  
  const cont = document.getElementById('qr-container');
  cont.innerHTML = '';
  
  currentQRCode = new QRCode(cont, {
    text: codigoString,
    width: 200, 
    height: 200,
    colorDark: '#0f4c81', 
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
  });
}

function imprimirQR() {
  const cvs    = document.querySelector('#qr-container canvas');
  const img    = cvs ? cvs.toDataURL('image/png') : '';
  const nombre = document.getElementById('qr-vis-nombre').textContent;
  const emp    = document.getElementById('qr-vis-empresa').textContent;
  const cod    = document.getElementById('qr-code-txt').textContent;
  
  const w = window.open('','_blank');
  w.document.write(`<html><head><title>QR ${nombre}</title>
    <style>body{text-align:center;font-family:sans-serif;padding:2rem}
    h3{margin-bottom:.5rem}p{color:#666;font-size:.9rem}</style></head>
    <body><h3>${nombre}</h3><p>${emp}</p>
    <img src="${img}" style="width:200px;height:200px;border-radius:12px">
    <p style="font-family:monospace;margin-top:.75rem">${cod}</p>
    <script>window.print();window.close();<\/script></body></html>`);
  w.document.close();
}

function copiarCodigo() {
  if (!currentQRId) return;
  navigator.clipboard.writeText(String(currentQRId)).then(() => {
    alert('Código copiado: ' + currentQRId);
  }).catch(() => {
    alert('Código: ' + currentQRId);
  });
}

// 8. Historial del visitante
async function verHistorial(id, nombre, empresa) {
  document.getElementById('hist-nombre').textContent = "Historial de " + nombre;
  document.getElementById('hist-empresa').textContent = empresa;
  const body = document.getElementById('hist-body');
  body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-muted me-2"></span>Cargando historial...</div>';
  
  const modalHist = new bootstrap.Modal(document.getElementById('modal-historial'));
  modalHist.show();

  try {
    const r = await fetch(`<?= BASE_URL ?>/api/index.php?action=visitantes&sub=${id}&sub2=historial`, {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true' }
    });
    const j = await r.json();
    const records = j.data || [];

    if (!records.length) {
      body.innerHTML = '<div class="text-center py-4 text-muted">Este visitante no registra consumos en el sistema aún.</div>';
      return;
    }

    body.innerHTML = records.map(r => {
      const label = EVT_LABELS[r.tipo_evento] || r.tipo_evento;
      const badge = EVT_BADGE[r.tipo_evento] || 'bg-secondary text-white';
      return `
        <div class="evt-row">
          <div style="font-family:monospace; font-size:.85rem;" class="text-muted">${r.fecha_hora}</div>
          <div class="ms-auto"><span class="badge ${badge}">${label}</span></div>
        </div>`;
    }).join('');

  } catch (e) {
    document.getElementById('hist-body').innerHTML =
      `<div class="text-center py-4 text-danger">${e.message}</div>`;
  }
}

// Init
loadStatsHoy();
loadVisitantes();
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>