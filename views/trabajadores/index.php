<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$pageTitle = 'Trabajadores';
$activeNav = 'trabajadores';
require_once ROOT_PATH . 'views/layouts/header.php';
?>

<style>
#qr-modal .qr-box {
  background: #fff;
  border: 1px solid var(--gray-200);
  border-radius: 12px;
  padding: 1.5rem;
  text-align: center;
  display: inline-block;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div class="input-group" style="max-width:360px">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
    <input type="text" id="search-q" class="form-control border-start-0"
           placeholder="Buscar por nombre o DNI..." oninput="loadTrabajadores()">
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-form" onclick="openNew()">
    <i class="bi bi-person-plus-fill"></i> Nuevo trabajador
  </button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th class="ps-4">Trabajador</th>
            <th>DNI</th>
            <th>F. Nacimiento</th>
            <th>Área</th>
            <th>Cargo</th>
            <th>Empresa</th>
            <th>Condición</th>
            <th>Ingreso</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody id="trab-tbody">
          <tr><td colspan="8" class="text-center py-4 text-muted">Cargando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal form trabajador -->
<div class="modal fade" id="modal-form" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-title">Nuevo trabajador</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="f-id">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label small fw-600">DNI</label>
            <input type="text" id="f-dni" class="form-control" placeholder="Ej: 45879632" maxlength="15">
          </div>
          <div class="col-6">
            <label class="form-label small fw-600">Fecha nacimiento *</label>
            <input type="date" id="f-nacimiento" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-600">Área *</label>
            <select id="f-area-form" class="form-select"></select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-600">Nombre completo *</label>
            <input type="text" id="f-nombre" class="form-control" placeholder="Apellidos y nombres" style="text-transform: uppercase;>
          </div>
          <div class="col-6">
            <label class="form-label small fw-600">Cargo</label>
            <input type="text" id="f-cargo" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label small fw-600">Empresa</label>
            <input type="text" id="f-empresa" class="form-control">
          </div>
          <div class="col-6">
            <label for="trabajador-condicion" class="form-label small fw-600">Condición Laboral *</label>
            <select id="trabajador-condicion" class="form-select" required>
              <option value="">-- Seleccionar --</option>
              <option value="RH">RH</option>
              <option value="PLANILLA">PLANILLA</option>
              <option value="EXTERNO">EXTERNO</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-600">Fecha ingreso *</label>
            <input type="date" id="f-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarTrabajador()">
          <i class="bi bi-check-lg"></i> Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal QR -->
<div class="modal fade" id="qr-modal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">QR del trabajador</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-3">
        <div id="qr-name" class="fw-600 mb-3"></div>
        <div id="qr-container" class="qr-box d-inline-block mb-3"></div>
        <div id="qr-dni" class="text-muted small"></div>
        <div class="mt-3">
          <button class="btn btn-outline-primary btn-sm" onclick="imprimirQR()">
            <i class="bi bi-printer"></i> Imprimir QR
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QRCode.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
let areas = [];
let currentQR = null;

async function loadAreas() {
  try {
    const r = await fetch('<?= BASE_URL ?>/api/index.php?action=areas', {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true' }
    });
    const j = await r.json();
    areas = j.data || [];
    const sel = document.getElementById('f-area-form');
    if (sel) {
      sel.innerHTML = areas.map(a => `<option value="${a.id_area}">${a.nombre_area}</option>`).join('');
    }
  } catch (e) {
    console.error("Error al cargar áreas en el formulario:", e);
  }
}

async function loadTrabajadores() {
  const q = document.getElementById('search-q').value;
  const params = new URLSearchParams();
  if (q) params.append('q', q);

  const tbody = document.getElementById('trab-tbody');
  tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Cargando...</td></tr>';

  try {
    const r = await fetch(`<?= BASE_URL ?>/api/index.php?action=trabajadores&${params}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true' }
    });
    const j = await r.json();
    
    if (!j.data || !j.data.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">Sin resultados</td></tr>';
      return;
    }

    tbody.innerHTML = j.data.map(t => `<tr>
      <td class="ps-4"><span class="fw-500">${t.nombre_completo}</span></td>
      <td><span style="font-family:monospace;font-size:.83rem">${t.dni || '—'}</span></td>
      <td><small>${t.nombre_area || '—'}</small></td>
      <td><small>${t.cargo || '—'}</small></td>
      <td><small class="text-muted">${t.empresa || '—'}</small></td>
      <td><span class="badge bg-secondary">${t.condicion || '—'}</span></td>
      <td><small>${t.fecha_ingreso}</small></td>
      <td class="text-center">
        <button class="btn btn-xs btn-outline-primary me-1"
                onclick='editTrabajador(${JSON.stringify(t)})'
                data-bs-toggle="modal" data-bs-target="#modal-form"
                title="Editar">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-xs btn-outline-secondary"
                onclick='mostrarQR("${t.dni || ''}","${t.nombre_completo.replace(/'/g, "\\'")}")'
                data-bs-toggle="modal" data-bs-target="#qr-modal"
                title="Ver QR">
          <i class="bi bi-qr-code"></i>
        </button>
      </td>
    </tr>`).join('');
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Error de conexión con la API</td></tr>';
    console.error("Error al listar trabajadores:", e);
  }
}

function openNew() {
  document.getElementById('modal-title').textContent = 'Nuevo trabajador';
  document.getElementById('f-id').value                  = '';
  document.getElementById('f-dni').value                 = '';
  document.getElementById('f-nacimiento').value        = '<?= date('Y-m-d') ?>';
  document.getElementById('f-nombre').value              = '';
  document.getElementById('f-cargo').value               = '';
  document.getElementById('f-empresa').value             = '';
  document.getElementById('trabajador-condicion').value = ''; // Resetea el combo box
  document.getElementById('f-fecha').value               = '<?= date('Y-m-d') ?>';
  document.getElementById('f-dni').disabled              = false;
}

function editTrabajador(t) {
  document.getElementById('modal-title').textContent  = 'Editar trabajador';
  document.getElementById('f-id').value               = t.id_trabajador;
  document.getElementById('f-dni').value              = t.dni || '';
  document.getElementById('f-nacimiento').value     = t.fecha_nacimiento || '<?= date('Y-m-d') ?>';
  document.getElementById('f-nombre').value           = t.nombre_completo;
  document.getElementById('f-cargo').value            = t.cargo || '';
  document.getElementById('f-empresa').value          = t.empresa || '';
  document.getElementById('f-fecha').value            = t.fecha_ingreso || '<?= date('Y-m-d') ?>';
  document.getElementById('f-area-form').value        = t.id_area;
  
  // Asigna el valor al combo box asegurando mayúsculas
  const condVal = (t.condicion || '').trim().toUpperCase();
  document.getElementById('trabajador-condicion').value = condVal;
  
  document.getElementById('f-dni').disabled           = true; // no editar DNI
}

async function guardarTrabajador() {
  const id        = document.getElementById('f-id').value;
  const condicion = document.getElementById('trabajador-condicion')?.value.trim() || '';
  const nombre    = document.getElementById('f-nombre').value.trim().toUpperCase();

  if (!nombre) {
    alert("Por favor ingrese el Nombre Completo.");
    return;
  }

  if (!condicion) {
    alert("Por favor seleccione la Condición Laboral (RH, PLANILLA o EXTERNO).");
    return;
  }

  const payload = {
    dni:              document.getElementById('f-dni').value.trim(),
    nombre_completo:  nombre,
    fecha_nacimiento: document.getElementById('f-nacimiento').value,
    id_area:          document.getElementById('f-area-form').value,
    cargo:            document.getElementById('f-cargo').value.trim(),
    empresa:          document.getElementById('f-empresa').value.trim(),
    condicion:        condicion, // Usamos la variable ya validada arriba
    fecha_ingreso:    document.getElementById('f-fecha').value
  };

  // Limpiamos barras sobrantes en BASE_URL para evitar el doble //
  const baseUrlClean = "<?= rtrim(BASE_URL, '/') ?>";
  const url = id
    ? `${baseUrlClean}/api/index.php?action=trabajadores&sub=${id}`
    : `${baseUrlClean}/api/index.php?action=trabajadores`;
  const method = id ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'ngrok-skip-browser-warning': 'true'
      },
      body: JSON.stringify(payload)
    });

    // Leemos la respuesta en texto plano para evitar que truene r.json() si PHP devuelve HTML/Error 500
    const responseText = await r.text();
    let j;

    try {
      j = JSON.parse(responseText);
    } catch (jsonErr) {
      console.error("El servidor no devolvió JSON válido. Respuesta del servidor:", responseText);
      alert("Error 500 en el servidor al procesar la solicitud. Revisa la consola para más detalle.");
      return;
    }

    if (r.ok && j.success) {
      const modalEl = document.getElementById('modal-form');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      
      await loadTrabajadores();
      alert(j.message || "Guardado exitosamente");
    } else {
      alert("Error: " + (j.message || "No se pudo guardar la información"));
    }
  } catch (e) {
    console.error("Error al guardar:", e);
    alert("Error de comunicación con el servidor");
  }
}
function mostrarQR(dni, nombre) {
  document.getElementById('qr-name').textContent = nombre;
  document.getElementById('qr-dni').textContent  = 'DNI: ' + (dni || 'S/N');
  const cont = document.getElementById('qr-container');
  cont.innerHTML = '';
  currentQR = new QRCode(cont, {
    text: dni || nombre,
    width: 200, height: 200,
    colorDark: '#0f4c81',
    colorLight: '#fff',
    correctLevel: QRCode.CorrectLevel.H,
  });
}

function imprimirQR() {
  const w = window.open('', '_blank');
  const canvas = document.querySelector('#qr-container canvas');
  const img    = canvas?.toDataURL('image/png') ?? '';
  const nombre = document.getElementById('qr-name').textContent;
  const dniTxt = document.getElementById('qr-dni').textContent;
  w.document.write(`<html><head><title>QR ${nombre}</title></head>
    <body style="text-align:center;font-family:sans-serif;padding:2rem">
      <h3>${nombre}</h3>
      <img src="${img}" style="width:200px;height:200px">
      <p>${dniTxt}</p>
      <script>window.print();window.close();<\/script>
    </body></html>`);
  w.document.close();
}

loadAreas().then(() => loadTrabajadores());
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>