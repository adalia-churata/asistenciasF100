<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$modulo    = $_GET['modulo'] ?? 'auto';
$pageTitle = match($modulo) {
    'comedor' => 'Escanear — Comedor',
    'laboral' => 'Escanear — Asistencia',
    default   => 'Escanear QR',
};
$activeNav = 'scanner';
require_once ROOT_PATH . 'views/layouts/header.php';
?>

<style>
/* ── Scanner layout ── */
#scan-wrap { max-width: 540px; margin: 0 auto; }

#scan-status {
  min-height: 80px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: .35rem;
  font-weight: 600; font-size: 1rem; transition: all .3s;
  background: var(--gray-100); color: var(--gray-600);
}
#scan-status.success  { background: #dcfce7; color: #166534; }
#scan-status.error    { background: #fee2e2; color: #991b1b; }
#scan-status.scanning { background: var(--primary-light); color: var(--primary); }

#reader-container {
  border-radius: 16px; overflow: hidden;
  border: 2px solid var(--gray-200); background: #000;
  aspect-ratio: 1; position: relative;
}
#qr-reader { width:100% !important; }
#qr-reader video { width:100% !important; height:100% !important; object-fit:cover; }

.modulo-btn { padding: .5rem 1.1rem; font-weight: 600; border-radius: 8px; }
.modulo-btn.active { box-shadow: 0 0 0 2px var(--primary); }

#result-card {
  display: none; background: #fff;
  border: 1.5px solid var(--gray-200); border-radius: 16px; padding: 1.5rem;
}
#result-card.show { display: block; }
#result-card .r-name   { font-size: 1.3rem; font-weight: 700; }
#result-card .r-evento { font-size: 1.1rem; font-weight: 600; margin-top: .25rem; }

.scanner-guide {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center; pointer-events: none;
}
.scanner-guide::after {
  content: ''; width: 55%; height: 55%;
  border: 2.5px solid rgba(255,255,255,.7); border-radius: 16px;
  box-shadow: 0 0 0 2000px rgba(0,0,0,.3);
}

/* ── Modal Visitantes ── */
#modal-visitante .modal-dialog { max-width: 560px; }

.nav-tabs-vis .nav-link {
  border-radius: 8px 8px 0 0; font-weight: 600; font-size: .88rem;
  color: var(--gray-600); border-color: var(--gray-200) var(--gray-200) transparent;
}
.nav-tabs-vis .nav-link.active { color: var(--primary); background: #fff; }

/* Autocomplete */
#autocomplete-list {
  position: absolute; top: 100%; left: 0; right: 0; z-index: 999;
  background: #fff; border: 1px solid var(--gray-200);
  border-radius: 0 0 10px 10px; max-height: 260px; overflow-y: auto;
  box-shadow: 0 8px 24px rgba(0,0,0,.1);
}
.ac-item {
  padding: .7rem 1rem; cursor: pointer; display: flex;
  align-items: center; gap: .75rem; border-bottom: 1px solid var(--gray-100);
  transition: background .12s;
}
.ac-item:hover, .ac-item.selected { background: var(--primary-light); }
.ac-item:last-child { border-bottom: none; }
.ac-avatar {
  width: 36px; height: 36px; border-radius: 8px; background: var(--primary-light);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.ac-name { font-weight: 600; font-size: .88rem; }
.ac-meta { font-size: .75rem; color: var(--gray-600); }

/* Visitor selected card */
#vis-selected-card {
  background: var(--primary-light); border-radius: 12px; padding: 1rem 1.1rem;
  display: none; margin-top: .75rem;
}
#vis-selected-card.show { display: block; }

/* Comida badges hoy */
.comida-badge {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .72rem; font-weight: 600; padding: .2rem .5rem;
  border-radius: 5px; opacity: .5;
}
.comida-badge.done { opacity: 1; text-decoration: line-through; }

/* Action buttons comedor */
.btn-comida {
  flex: 1; padding: .6rem .5rem; font-weight: 700;
  font-size: .82rem; border-radius: 10px; transition: all .15s;
}
.btn-comida:active { transform: scale(.96); }
.btn-comida:disabled { opacity: .45; cursor: not-allowed; }
</style>

<div id="scan-wrap">
  <!-- Módulo selector + botón visitante -->
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <button type="button" 
            id="btn-modulo-comedor"
            class="modulo-btn btn btn-outline-warning active"
            onclick="setModulo('comedor', this)">🍽️ Comedor</button>

    <button type="button" 
            id="btn-modulo-laboral"
            class="modulo-btn btn btn-outline-primary"
            onclick="setModulo('laboral', this)">🏭 Asistencia</button>

    <button type="button" 
            class="btn btn-success ms-auto fw-600"
            data-bs-toggle="modal" 
            data-bs-target="#modal-visitante"
            onclick="abrirModalVisitante()">
      <i class="bi bi-person-plus-fill"></i> Registrar Visitante
    </button>
  </div>

  <!-- Status -->
  <div id="scan-status" class="mb-3 scanning">
    <i class="bi bi-qr-code-scan fs-4"></i>
    <span>Iniciando cámara...</span>
  </div>

  <!-- Camera -->
  <div id="reader-container" class="mb-4">
    <div id="qr-reader"></div>
    <div class="scanner-guide"></div>
  </div>

  <!-- Resultado scan -->
  <div id="result-card">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div id="r-avatar" style="width:52px;height:52px;border-radius:12px;background:var(--primary-light);
           display:flex;align-items:center;justify-content:center;font-size:1.6rem">👤</div>
      <div>
        <div class="r-name" id="r-name">—</div>
        <small class="text-muted" id="r-meta">—</small>
      </div>
    </div>
    <div class="d-flex gap-3 align-items-center">
      <div>
        <div class="r-evento" id="r-evento">—</div>
        <small class="text-muted" id="r-hora">—</small>
      </div>
      <div id="r-horas" class="ms-auto text-end" style="display:none">
        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-600)">Horas trabajadas</div>
        <div id="r-horas-val" style="font-size:1.4rem;font-weight:700;font-family:var(--bs-font-monospace)"></div>
        <small id="r-horas-tipo" class="fw-600"></small>
      </div>
    </div>
  </div>

  <!-- Log de sesión -->
  <div class="mt-4">
    <h6 class="fw-600 mb-3">Registros de esta sesión</h6>
    <div id="session-log" class="d-flex flex-column gap-2">
      <div class="text-muted text-center py-3" style="font-size:.85rem">Sin registros aún</div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL DE VISITANTES
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modal-visitante" tabindex="-1" aria-labelledby="modal-vis-title">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.18)">

      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <div>
          <h5 class="modal-title fw-700" id="modal-vis-title">
            <i class="bi bi-person-badge-fill text-success me-2"></i>Registro de Visitante
          </h5>
          <small class="text-muted">Selecciona un visitante existente o registra uno nuevo</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body px-4 pb-4 pt-3">
        <!-- TABS -->
        <ul class="nav nav-tabs nav-tabs-vis mb-4" id="vis-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-existente-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-existente"
                    type="button" role="tab">
              <i class="bi bi-search me-1"></i>Visitante existente
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-nuevo-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-nuevo"
                    type="button" role="tab">
              <i class="bi bi-person-plus me-1"></i>Nuevo visitante
            </button>
          </li>
        </ul>

        <div class="tab-content" id="vis-tab-content">

          <!-- ── TAB 1: VISITANTE EXISTENTE ── -->
          <div class="tab-pane fade show active" id="tab-existente" role="tabpanel">

            <!-- Buscador autocomplete -->
            <div style="position:relative">
              <label class="form-label small fw-600 mb-1">Buscar por nombre, DNI o empresa</label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="vis-buscar" class="form-control"
                       placeholder="Escriba al menos 2 caracteres..."
                       autocomplete="off" 
                       oninput="onFiltrarAutocomplete(this.value)">
                <button class="btn btn-outline-secondary" type="button"
                        onclick="limpiarBusqueda()" title="Limpiar">
                  <i class="bi bi-x"></i>
                </button>
              </div>
              <div id="autocomplete-list" style="display:none"></div>
            </div>

            <!-- Card del visitante seleccionado -->
            <div id="vis-selected-card" class="mt-3" style="display:none">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:44px;height:44px;border-radius:10px;background:var(--primary);
                     display:flex;align-items:center;justify-content:center;font-size:1.3rem">🪪</div>
                <div class="flex-grow-1">
                  <div id="sel-nombre" class="fw-700" style="font-size:1rem"></div>
                  <div id="sel-empresa" class="text-muted small"></div>
                  <div id="sel-dni" class="text-muted small" style="font-family:monospace"></div>
                </div>
                <button class="btn btn-xs btn-outline-secondary" onclick="limpiarSeleccion()" title="Cambiar">
                  <i class="bi bi-pencil"></i>
                </button>
              </div>

              <!-- Estado de comidas hoy -->
              <div class="d-flex gap-2 mb-3" id="comidas-hoy-row">
                <span class="comida-badge badge-DESAYUNO" id="badge-des">☕ Desayuno</span>
                <span class="comida-badge badge-ALMUERZO" id="badge-alm">🍽️ Almuerzo</span>
                <span class="comida-badge badge-CENA"     id="badge-cen">🌙 Cena</span>
              </div>

              <!-- Botones acción -->
              <div class="d-flex gap-2">
                <button class="btn btn-warning btn-comida" id="btn-des-exist"
                        onclick="registrarExistente('DESAYUNO')">
                  ☕ Desayuno
                </button>
                <button class="btn btn-purple btn-comida" id="btn-alm-exist"
                        onclick="registrarExistente('ALMUERZO')"
                        style="background:#7c3aed;color:#fff;border-color:#7c3aed">
                  🍽️ Almuerzo
                </button>
                <button class="btn btn-comida" id="btn-cen-exist"
                        onclick="registrarExistente('CENA')"
                        style="background:#1d4ed8;color:#fff;border-color:#1d4ed8">
                  🌙 Cena
                </button>
              </div>
            </div>

            <!-- Estado vacío -->
            <div id="vis-empty-state" class="text-center py-4 text-muted" style="font-size:.88rem">
              <i class="bi bi-person-lines-fill fs-2 mb-2 d-block opacity-25"></i>
              Busca un visitante para registrar su consumo
            </div>

          </div>

          <!-- ── TAB 2: NUEVO VISITANTE ── -->
          <div class="tab-pane fade" id="tab-nuevo" role="tabpanel">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small fw-600">Nombre completo *</label>
                <input type="text" id="n-nombre" class="form-control" placeholder="Apellidos y nombres">
              </div>
              <div class="col-12">
                <label class="form-label small fw-600">Empresa / Institución *</label>
                <input type="text" id="n-empresa" class="form-control" placeholder="Empresa u organización">
              </div>
              <div class="col-12">
                <label class="form-label small fw-600">Observación (opcional)</label>
                <input type="text" id="n-obs" class="form-control" placeholder="Ej: Motivo de la visita, proveedor, etc.">
              </div>
              <div class="col-12">
                <label class="form-label small fw-600">Tipo de consumo inmediato</label>
                <select id="n-tipo" class="form-select">
                  <option value="AUTO">🍽️ Según horario automático</option>
                  <option value="DESAYUNO">☕ Desayuno</option>
                  <option value="ALMUERZO">🍽️ Almuerzo</option>
                  <option value="CENA">🌙 Cena</option>
                </select>
              </div>
              <div class="col-12 mt-4">
                <button class="btn btn-success w-100 fw-600" onclick="registrarNuevo('AUTO')">
                  <i class="bi bi-check-circle-fill"></i> Registrar Entrada y Comedor
                </button>
              </div>
            </div>

            <!-- Botones registro inmediato -->
            <div class="mt-4">
              <div class="small fw-600 text-muted mb-2">Registrar consumo ahora:</div>
              <div class="d-flex gap-2">
                <button class="btn btn-warning btn-comida" onclick="registrarNuevo('DESAYUNO')">
                  ☕ Desayuno
                </button>
                <button class="btn btn-comida" onclick="registrarNuevo('ALMUERZO')"
                        style="background:#7c3aed;color:#fff;border-color:#7c3aed">
                  🍽️ Almuerzo
                </button>
                <button class="btn btn-comida" onclick="registrarNuevo('CENA')"
                        style="background:#1d4ed8;color:#fff;border-color:#1d4ed8">
                  🌙 Cena
                </button>
              </div>
              <button class="btn btn-outline-secondary w-100 mt-2" onclick="soloCrear()">
                <i class="bi bi-person-check"></i> Solo registrar visitante (sin comida)
              </button>
            </div>
          </div>

        </div><!-- /.tab-content -->
      </div><!-- /.modal-body -->
    </div>
  </div>
</div>

<!-- html5-qrcode -->
<!-- html5-qrcode -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

<script>
// ── Configuración Dinámica de API en Hosting (InfinityFree) ──
// Detecta la ruta raíz automáticamente
const BASE_PATH = window.location.pathname.substring(0, window.location.pathname.indexOf('/views')) || '';
const API_BASE = window.location.origin + BASE_PATH + '/api';

// ── Estado global ────────────────────────────────────────────
let currentModulo    = 'comedor';
let scanner          = null;
let lastQr           = '';
let lastQrTime       = 0;
const COOLDOWN_MS    = 3000;

let visSeleccionado  = null;
let seleccionadoId   = null;
let acTimer          = null;

const statusEl = document.getElementById('scan-status');
const resultEl = document.getElementById('result-card');
const logEl    = document.getElementById('session-log');

// ── Helpers ──────────────────────────────────────────────────
function playBeep(success = true) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.value = success ? 800 : 300;
    gain.gain.setValueAtTime(0.1, ctx.currentTime);
    osc.start();
    osc.stop(ctx.currentTime + 0.15);
  } catch(e) {}
}

function showToast(tipo, msj, sub = '') {
  if (typeof alert !== 'undefined') {
    alert((tipo === 'error' ? '❌ ' : '✅ ') + msj + (sub ? '\n' + sub : ''));
  }
}

function setStatus(txt, type = 'scanning') {
  if (!statusEl) return;
  statusEl.className = `mb-3 ${type}`;
  statusEl.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : (type === 'error' ? 'x-circle' : 'qr-code-scan')} fs-4"></i><span>${txt}</span>`;
}

function setModulo(mod, btn) {
    currentModulo = mod;
    document.querySelectorAll('.modulo-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
}

// ── Escáner Principal Corregido para InfinityFree ─────────
async function onScan(decodedText) {
  const now = Date.now();
  if (decodedText === lastQr && (now - lastQrTime) < COOLDOWN_MS) return;
  lastQr = decodedText; 
  lastQrTime = now;

  setStatus('Procesando...', 'scanning');
  
  try {
    // CORRECCIÓN: Apuntamos explícitamente a index.php pasando la acción en la URL
    const res = await fetch(`${API_BASE}/index.php?action=scan`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ qr: decodedText.trim(), modulo: currentModulo }),
    });

    const txt = await res.text(); 
    console.log("Respuesta servidor Infinity:", txt);

    if (!res.ok) {
      throw new Error(`Error del servidor (${res.status}): ${txt}`);
    }

    let json = JSON.parse(txt);

    if (json.success) {
      playBeep(true);
      setStatus(json.message || 'Registro exitoso', 'success');
      showResult(json.data, true);
      addLog(json.data, true);
    } else {
      playBeep(false);
      setStatus(json.message || 'Error en el escaneo', 'error');
      addLog({ evento: json.message || 'Error de registro' }, false);
    }
  } catch(e) {
    playBeep(false);
    console.error("Detalle exacto del error:", e); 
    setStatus('Error de conexión con el servidor', 'error');
  }

  setTimeout(() => setStatus('Listo para escanear', 'scanning'), COOLDOWN_MS);
}
    
function showResult(d, ok = true) {
  if (!d || !resultEl) return;
  resultEl.classList.add('show');
  document.getElementById('r-name').textContent   = d.persona || d.nombre || '—';
  document.getElementById('r-meta').textContent   = [d.area, d.empresa, d.dni].filter(Boolean).join(' · ') || '—';
  document.getElementById('r-evento').textContent = d.evento || '—';
  document.getElementById('r-hora').textContent   = d.hora ? `⏱ ${d.hora}` : '';
  document.getElementById('r-avatar').textContent = ok ? (d.tipo === 'visitante' ? '🪪' : '👷') : '❌';

  const horasDiv = document.getElementById('r-horas');
  if (d.horas_resumen?.horas_trabajadas !== null && d.horas_resumen?.horas_trabajadas !== undefined) {
    horasDiv.style.display = 'block';
    document.getElementById('r-horas-val').textContent = `${d.horas_resumen.horas_trabajadas}h`;
    const h = document.getElementById('r-horas-tipo');
    if (d.horas_resumen.tipo_diferencia === 'extra') {
      h.textContent = `+${d.horas_resumen.diferencia}h extra`; h.style.color = 'var(--success)';
    } else {
      h.textContent = `-${d.horas_resumen.diferencia}h déficit`; h.style.color = 'var(--danger)';
    }
  } else { 
    horasDiv.style.display = 'none'; 
  }
}

function addLog(d, ok) {
  if (!d || !logEl) return;
  const hora = d.hora || new Date().toLocaleTimeString('es-PE', {hour12:false});
  const nombre = d.persona || d.nombre || d.evento || '—';
  const tipo   = d.tipo === 'visitante' ? '🪪' : (ok ? '👷' : '❌');
  const html = `<div class="d-flex align-items-center gap-2 p-2 rounded-3"
                      style="background:${ok ? '#f0fdf4' : '#fef2f2'};font-size:.83rem">
    <span>${tipo}</span>
    <div class="flex-grow-1">
      <span class="fw-500">${nombre}</span>
      ${d.evento ? `<span class="ms-1 text-muted">· ${d.evento}</span>` : ''}
    </div>
    <span class="ms-auto text-muted" style="font-family:monospace;font-size:.78rem">${hora}</span>
  </div>`;
  
  const emptyMsg = logEl.querySelector('.text-muted.text-center');
  if (emptyMsg) emptyMsg.remove();
  
  logEl.insertAdjacentHTML('afterbegin', html);
}

// ── MODAL VISITANTES ─────────────────────────────────────────
function abrirModalVisitante() {
  limpiarBusqueda();
  limpiarNuevoForm();
  const tabBtn = document.getElementById('tab-existente-btn');
  if (tabBtn) tabBtn.click();
}

// ── Autocomplete / Búsqueda ──────────────────────────────────
window.onFiltrarAutocomplete = function(val) {
  clearTimeout(acTimer);
  const listContainer = document.getElementById('autocomplete-list');
  if (!listContainer) return;

  const query = val.trim();
  if (query.length < 2) {
    listContainer.innerHTML = '';
    listContainer.style.display = 'none';
    return;
  }

  acTimer = setTimeout(async () => {
    try {
      const r = await fetch(`${API_BASE}/index.php?action=visitantes&sub=buscar&q=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: { 
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      const txt = await r.text();
      let j = JSON.parse(txt);
      const data = j.data || [];

      if (!data.length) {
        listContainer.innerHTML = '<div class="p-3 text-muted small text-center bg-white border rounded-3">No se encontraron visitantes</div>';
        listContainer.style.display = 'block';
        return;
      }

      listContainer.innerHTML = data.map(v => {
        const objetoSanitizado = JSON.stringify(v).replace(/'/g, "&apos;").replace(/"/g, '&quot;');
        return `
          <div class="ac-item" style="padding:.7rem 1rem; cursor:pointer; background:#fff; border-bottom:1px solid #eee;" onclick="seleccionarVisitante(${objetoSanitizado})">
            <div class="fw-600" style="font-size:.9rem; color:#333;">${v.nombre}</div>
            <div class="text-muted" style="font-size:.75rem;">🏢 ${v.empresa || '—'}</div>
          </div>
        `;
      }).join('');
      
      listContainer.style.zIndex = '9999';
      listContainer.style.position = 'absolute';
      listContainer.style.width = '100%';
      listContainer.style.display = 'block';

    } catch (e) {
      console.error("Error en autocomplete:", e);
      listContainer.innerHTML = '<div class="p-2 text-danger small text-center bg-white border">Error al cargar la lista</div>';
      listContainer.style.display = 'block';
    }
  }, 250);
};

function seleccionarVisitante(v) {
  visSeleccionado = v;
  seleccionadoId  = v.id_visitante || v.id;

  const listContainer = document.getElementById('autocomplete-list');
  if (listContainer) listContainer.style.display = 'none';
  
  const inputBuscar = document.getElementById('vis-buscar');
  if (inputBuscar) inputBuscar.value = v.nombre;

  document.getElementById('sel-nombre').textContent = v.nombre;
  document.getElementById('sel-empresa').textContent = '🏢 ' + (v.empresa || '—');
  document.getElementById('sel-dni').textContent = v.dni ? ('DNI: ' + v.dni) : ''; 

  const des = parseInt(v.tuvo_desayuno) ? true : false;
  const alm = parseInt(v.tuvo_almuerzo) ? true : false;
  const cen = parseInt(v.tuvo_cena)     ? true : false;

  document.getElementById('badge-des').classList.toggle('done', des);
  document.getElementById('badge-alm').classList.toggle('done', alm);
  document.getElementById('badge-cen').classList.toggle('done', cen);

  document.getElementById('btn-des-exist').disabled = des;
  document.getElementById('btn-alm-exist').disabled = alm;
  document.getElementById('btn-cen-exist').disabled = cen;

  document.getElementById('vis-selected-card').style.display = 'block';
  
  const emptyState = document.getElementById('vis-empty-state');
  if (emptyState) emptyState.style.display = 'none';
}

window.limpiarBusqueda = function() {
  const input = document.getElementById('vis-buscar');
  if (input) input.value = '';
  
  const listContainer = document.getElementById('autocomplete-list');
  if (listContainer) {
    listContainer.innerHTML = '';
    listContainer.style.display = 'none';
  }
  
  const card = document.getElementById('vis-selected-card');
  if (card) card.style.display = 'none';

  const emptyState = document.getElementById('vis-empty-state');
  if (emptyState) emptyState.style.display = 'block';
  
  visSeleccionado = null;
  seleccionadoId  = null;
};

function limpiarSeleccion() { limpiarBusqueda(); }

function limpiarNuevoForm() {
  ['n-nombre','n-empresa','n-obs'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
}

// ── Registrar evento visitante EXISTENTE ─────────────────────
async function registrarExistente(tipo) {
  if (!seleccionadoId) {
    alert("Por favor, selecciona primero un visitante de la lista");
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/index.php?action=visitantes&sub=evento`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ 
        id_visitante: seleccionadoId, 
        tipo_evento: tipo 
      })
    });

    const txt = await res.text();
    let json = JSON.parse(txt);

    if (json.success) {
      const modalEl = document.getElementById('modal-visitante');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      
      setStatus(json.message || 'Registro exitoso', 'success');
      if (json.data) showResult(json.data, true);
      
      addLog({ 
        persona: json.data?.nombre || visSeleccionado?.nombre, 
        evento: json.data?.evento || tipo, 
        hora: json.data?.hora, 
        empresa: json.data?.empresa,
        tipo: 'visitante'
      }, true);
      
      limpiarBusqueda();
    } else {
      alert("Atención: " + (json.message || "No se pudo registrar"));
    }
  } catch (e) {
    console.error("Error al registrar comedor manual:", e);
    alert("Error de conexión: No se pudo enviar el registro.");
  }
}

window.registrarComidaManual = registrarExistente;

// ── Registrar NUEVO visitante + evento ───────────────────────
async function registrarNuevo(tipoEvento) {
  const nombre  = document.getElementById('n-nombre').value.trim().toUpperCase();
  const empresa = document.getElementById('n-empresa').value.trim().toUpperCase();
  const obs     = document.getElementById('n-obs').value.trim().toUpperCase();

  if (!nombre)  { showToast('error', 'El nombre es obligatorio'); document.getElementById('n-nombre').focus(); return; }
  if (!empresa) { showToast('error', 'La empresa es obligatoria'); document.getElementById('n-empresa').focus(); return; }

  const actualTipo = tipoEvento === 'AUTO' ? document.getElementById('n-tipo').value : tipoEvento;

  try {
    const r = await fetch(`${API_BASE}/index.php?action=visitantes&sub=crear-y-registrar`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ nombre, empresa, observacion: obs, tipo_evento: actualTipo }),
    });
    
    const txt = await r.text();
    let j = JSON.parse(txt);

    if (j.success) {
      playBeep(true);
      showToast('success', j.message || 'Visitante registrado con éxito');
      
      addLog({ 
        persona: j.data?.nombre || nombre, 
        evento: j.data?.evento || actualTipo, 
        hora: j.data?.hora, 
        empresa: j.data?.empresa || empresa,
        tipo: 'visitante'
      }, true);
      
      const modalEl = document.getElementById('modal-visitante');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      
      limpiarNuevoForm();
    } else {
      playBeep(false);
      showToast('error', j.message || 'Error al guardar');
    }
  } catch(e) {
    console.error(e);
    showToast('error', 'Error de conexión');
  }
}

async function soloCrear() {
  const nombre  = document.getElementById('n-nombre').value.trim();
  const empresa = document.getElementById('n-empresa').value.trim();
  const obs     = document.getElementById('n-obs').value.trim();

  if (!nombre)  { showToast('error', 'El nombre es obligatorio'); return; }
  if (!empresa) { showToast('error', 'La empresa es obligatoria'); return; }

  try {
    const r = await fetch(`${API_BASE}/index.php?action=visitantes`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ nombre, empresa, observacion: obs }),
    });

    const txt = await r.text();
    let j = JSON.parse(txt);

    if (j.success) {
      showToast('success', 'Visitante registrado', nombre + ' — ' + empresa);
      const modalEl = document.getElementById('modal-visitante');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      
      limpiarNuevoForm();
    } else {
      showToast('error', j.message || 'Error al crear');
    }
  } catch(e) {
    console.error(e);
    showToast('error', 'Error de conexión');
  }
}

// ── Oyentes e inicialización ────────────────────────────────
document.addEventListener('click', e => {
  if (!e.target.closest('#vis-buscar') && !e.target.closest('#autocomplete-list')) {
    const listContainer = document.getElementById('autocomplete-list');
    if (listContainer) listContainer.style.display = 'none';
  }
});

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('qr-reader')) {
    scanner = new Html5Qrcode('qr-reader');
    scanner.start(
      { facingMode: "environment" },
      { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
      onScan,
      () => {}
    )
    .then(() => setStatus('Listo para escanear', 'scanning'))
    .catch((err) => {
      console.error("Error cámara:", err);
      setStatus('Error de cámara — verifique permisos o HTTPS', 'error');
    });
  }
});
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>