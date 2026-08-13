<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$pageTitle = 'Cronograma';
$activeNav = 'cronograma';
require_once ROOT_PATH . 'views/layouts/header.php';
?>
<!-- Librería para generar archivos Excel (.xlsx) -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<div class="container-fluid py-3">
  
  <!-- BARRA SUPERIOR DE ACCIONES Y FILTROS -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row align-items-center g-2">
        
        <!-- Seleccionar Mes/Año -->
        <div class="col-md-2">
          <label class="form-label small fw-bold mb-0" for="filtro-mes">Mes / Año:</label>
          <input type="month" id="filtro-mes" class="form-control form-control-sm" value="<?= date('Y-m') ?>">
        </div>

        <!-- Búsqueda en tiempo real por Nombre o DNI -->
        <div class="col-md-3">
          <label class="form-label small fw-bold mb-0" for="filtro-buscar">Filtrar Trabajador Activo:</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="filtro-buscar" class="form-control" placeholder="Buscar por nombre o DNI...">
          </div>
        </div>

        <!-- Filtro por Área -->
        <div class="col-md-3">
          <label for="filtro-area" class="form-label small fw-bold mb-0">Área:</label>
          <select id="filtro-area" class="form-select form-select-sm">
            <option value="TODAS">-- Todas las Áreas --</option>
          </select>
        </div>

        <!-- Acciones principales -->
        <div class="col-md-4 text-end pt-3 pt-md-0">
          <span class="me-3 small text-muted d-block d-sm-inline mb-1 mb-sm-0">
            Trabajadores Activos: <strong id="cant-activos" class="text-dark">0</strong>
          </span>
          
          <button type="button" class="btn btn-sm btn-outline-primary me-1" id="btn-abrir-asistente">
            <i class="bi bi-magic"></i> Asistente 22x8
          </button>

          <button type="button" class="btn btn-sm btn-success fw-bold" id="btn-exportar-excel">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar a Excel
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- LEYENDA Y CONTROL -->
  <div class="d-flex align-items-center justify-content-between mb-2 px-1 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2 small">
      <span class="fw-bold">Leyenda rápida:</span>
      <span class="badge bg-success text-white px-2 py-1">T1..T22 (Trabajo)</span>
      <span class="badge bg-secondary text-white px-2 py-1">L1..L8 (Libre)</span>
      <span class="badge bg-danger text-white px-2 py-1">F (Falta)</span>
    </div>
    <div class="small text-muted">
      💡 Escribe "T" o "L" para correlativo automático. Doble clic o clic derecho para nota (📌).
    </div>
  </div>

  <!-- TABLA PRINCIPAL DE CRONOGRAMA -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive" style="max-height: 650px; overflow: auto;">
        <table class="table table-bordered table-hover table-sm align-middle text-center mb-0 style-grid">
          <thead class="table-dark sticky-top" style="z-index: 10;" id="thead-dias">
            <!-- Encabezado dinámico generado con renderTabla() -->
          </thead>
          <tbody id="tbody-cronograma">
            <tr><td colspan="35" class="py-4">Cargando personal activo...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- ── MODAL: ASISTENTE DE SECUENCIA RÁPIDA 22x8 ── -->
<div class="modal fade" id="modalAsistente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title fw-bold"><i class="bi bi-magic me-1"></i> Programación Automática 22x8</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold" for="ast-trabajador">Trabajador Activo:</label>
          <select id="ast-trabajador" class="form-select form-select-sm">
            <!-- Se llena dinámicamente con los trabajadores -->
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-bold" for="ast-tipo-inicio">Estado el Día 1 del Mes:</label>
            <select id="ast-tipo-inicio" class="form-select form-select-sm">
              <option value="T">TRABAJO (T)</option>
              <option value="L">LIBRE (L)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold" for="ast-num-inicio">Día de la Secuencia:</label>
            <input type="number" id="ast-num-inicio" class="form-control form-control-sm" value="1" min="1" max="22">
            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Ejemplo: T1, T15 o L3</small>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary fw-bold" id="btn-aplicar-asistente">Completar Mes</button>
      </div>
    </div>
  </div>
</div>

<style>
.style-grid th, .style-grid td {
  font-size: 0.75rem;
  padding: 3px !important;
  min-width: 32px;
  height: 34px;
}
.col-fija-nombre {
  min-width: 220px !important;
  text-align: left !important;
  padding-left: 10px !important;
  position: sticky;
  left: 0;
  background-color: #fff;
  z-index: 5;
}
.col-fija-condicion {
  min-width: 90px !important;
  position: sticky;
  left: 220px;
  background-color: #fff;
  z-index: 5;
}
thead th.col-fija-nombre,
thead th.col-fija-condicion {
  background-color: #212529 !important;
  color: #fff;
  z-index: 11;
}
.celda-dia {
  cursor: pointer;
  font-weight: 700;
  transition: background-color 0.1s ease;
  user-select: none;
}
.celda-dia:hover { filter: brightness(0.9); }
.celda-t { background-color: #d1e7dd !important; color: #0f5132; }
.celda-l { background-color: #e2e3e5 !important; color: #495057; }
.celda-f { background-color: #f8d7da !important; color: #842029; }
.celda-editada { outline: 2px solid #0d6efd !important; z-index: 2; }

/* Colores de fondo por Área (Suaves para mantener la legibilidad) */
  .area-supervisores     { background-color: #e3f2fd !important; } /* Azul claro */
  .area-administracion   { background-color: #f3e5f5 !important; } /* Morado claro */
  .area-operaciones      { background-color: #e8f5e9 !important; } /* Verde claro */
  .area-flota            { background-color: #fff3e0 !important; } /* Naranja claro */
  .area-maquinaria-pesada{ background-color: #efebe9 !important; } /* Café claro */
  .area-limpieza         { background-color: #e0f7fa !important; } /* Cian claro */
  .area-seguridad        { background-color: #ffebee !important; } /* Rojo claro */
  .area-comedor          { background-color: #fffde7 !important; } /* Amarillo claro */
  .area-default          { background-color: #f8f9fa !important; } /* Gris por defecto */

  /* Estilos para el encabezado de fechas */
  .th-fecha {
    min-width: 42px;
    text-align: center;
    font-size: 11px;
    padding: 4px 2px !important;
    color: #ffffff !important;
  }
  .th-fecha .num-dia {
    font-size: 13px;
    font-weight: bold;
    display: block;
  }
  .th-fecha .nom-dia {
    font-size: 9px;
    text-transform: uppercase;
    color: #dddfe1 ;
  }
  /* Estilo especial para Sábados y Domingos en el encabezado */
  .th-fin-semana {
    background-color: #212529!important;
  }
  /* Posicionamiento para la celda */
.celda-cronograma {
  position: relative;
  cursor: pointer;
}

/* Indicador visual de nota (Pin o punto rojo) */
.celda-cronograma .indicador-nota {
  position: absolute;
  top: 1px;
  right: 2px;
  font-size: 0.65rem;
  line-height: 1;
}

/* Fondo sutil para celdas que tienen alguna nota */
.celda-cronograma.con-observacion {
  background-color: #fff8e1 !important; /* Tono amarillento suave de nota adhesiva */
}

/* Evita la selección accidental de texto al hacer doble clic */
.celda-cronograma {
  user-select: none;
  -webkit-user-select: none;
  cursor: pointer;
}

/* Encabezado del día de hoy */
.th-hoy {
  background-color: #209a08 !important; /* Amarillo resaltado */
  color: #000 !important;
  border-bottom: 3px solid #f57c00 !important;
  font-weight: bold;
}

/* Celda (td) del día de hoy */
.celda-hoy {
  background-color: #fffde7 !important; /* Fondo amarillo muy suave */
  border-left: 2px solid #f57c00 !important;
  border-right: 2px solid #f57c00 !important;
}

/* Input del día de hoy */
.input-hoy {
  background-color: #fff9c4 !important; /* Amarillo claro dentro del input */
}

/* Color del encabezado cuando pasas el cursor por sus celdas inferiores */
.th-iluminado {
  background-color: #95b4c2 !important; /* Azul celeste claro (ajusta el color si deseas) */
  color: #000 !important;
  transition: background-color 0.15s ease-in-out;
}
</style>

<script>
let datosTrabajadoresGlobal = [];
let cambiosMap = {};

// ── INICIALIZACIÓN DE COMPONENTES Y EVENTOS ──
document.addEventListener('DOMContentLoaded', () => {
  cargarSelectAreas();
  cargarCronograma();
  verificarAlertasProximas();

  // Escuchar inputs y filtros
  const inputBuscar = document.getElementById('filtro-buscar');
  if (inputBuscar) inputBuscar.addEventListener('input', filtrarTabla);

  const selectArea = document.getElementById('filtro-area');
  if (selectArea) selectArea.addEventListener('change', filtrarTabla);

  const inputMes = document.getElementById('filtro-mes');
  if (inputMes) inputMes.addEventListener('change', cargarCronograma);

  const btnAsistente = document.getElementById('btn-abrir-asistente');
  if (btnAsistente) btnAsistente.addEventListener('click', abrirModalAsistente);

  const btnAplicarAst = document.getElementById('btn-aplicar-asistente');
  if (btnAplicarAst) btnAplicarAst.addEventListener('click', aplicarAsistente);

  const btnExcel = document.getElementById('btn-exportar-excel');
  if (btnExcel) btnExcel.addEventListener('click', exportarExcelCronograma);

  // Escuchar eventos en la tabla para notas (Doble clic / Clic derecho)
  const tabla = document.getElementById('tbody-cronograma');
  if (tabla) {
    tabla.addEventListener('dblclick', (e) => {
      const celda = e.target.closest('td[data-id-trabajador]');
      if (celda) abrirEditorObservacion(celda);
    });

    tabla.addEventListener('contextmenu', (e) => {
      const celda = e.target.closest('td[data-id-trabajador]');
      if (celda) {
        e.preventDefault();
        abrirEditorObservacion(celda);
      }
    });
  }
});

// Cargar catálogo de trabajadores al abrir el modal Asistente
document.getElementById('modalAsistente')?.addEventListener('show.bs.modal', function () {
  const select = document.getElementById('ast-trabajador');
  if (!select) return;

  if (!datosTrabajadoresGlobal || datosTrabajadoresGlobal.length === 0) {
    select.innerHTML = '<option value="">No hay trabajadores cargados</option>';
    return;
  }

  let options = '<option value="">-- Seleccione un Trabajador --</option>';
  datosTrabajadoresGlobal.forEach(t => {
    const area = t.area ? ` (${t.area})` : '';
    options += `<option value="${t.id_trabajador}">${t.nombre_completo}${area}</option>`;
  });
  select.innerHTML = options;
});

// ── PINTAR CELDAS Y CLASES DE ÁREA ──
window.aplicarColorCelda = function(input) {
  const valor = input.value.trim().toUpperCase();

  if (valor.startsWith('T')) {
    input.style.backgroundColor = '#d1e7dd';
    input.style.color = '#0f5132';
  } else if (valor.startsWith('L')) {
    input.style.backgroundColor = '#fefefe';
    input.style.color = '#000000';
  } else if (valor.startsWith('F')) {
    input.style.backgroundColor = '#bc2020';
    input.style.color = '#ffffff';
  } else {
    input.style.backgroundColor = '';
    input.style.color = '';
  }
};

window.obtenerClaseArea = function(nombreArea) {
  if (!nombreArea) return 'area-default';
  const area = nombreArea.toUpperCase().trim();
  switch (area) {
    case 'SUPERVISORES':       return 'area-supervisores';
    case 'ADMINISTRACION':     return 'area-administracion';
    case 'OPERACIONES':        return 'area-operaciones';
    case 'FLOTA':              return 'area-flota';
    case 'MAQUINARIA PESADA':  return 'area-maquinaria-pesada';
    case 'LIMPIEZA':           return 'area-limpieza';
    case 'SEGURIDAD':          return 'area-seguridad';
    case 'COMEDOR':            return 'area-comedor';
    default:                   return 'area-default';
  }
};

// ── CONSULTAS API HTTP (PHP BACKEND) ──
window.cargarSelectAreas = async function() {
  try {
    const r = await fetch(`<?= BASE_URL ?>/api/index.php?action=obtener_areas`);
    const j = await r.json();

    if (j.success && Array.isArray(j.data)) {
      let options = `<option value="TODAS">-- Todas las Áreas --</option>`;
      j.data.forEach(a => {
        const nombre = (typeof a === 'object' && a !== null)
          ? (a.nombre_area || a[1] || a.nombre || '')
          : String(a);

        const nombreLimpio = String(nombre).trim();
        if (nombreLimpio !== '') {
          options += `<option value="${nombreLimpio.toUpperCase()}">${nombreLimpio}</option>`;
        }
      });
      document.getElementById('filtro-area').innerHTML = options;
    }
  } catch (e) {
    console.error("Error al cargar selector de áreas:", e);
  }
};

window.cargarCronograma = async function() {
  const mesAño = document.getElementById('filtro-mes').value;
  if (!mesAño) return;

  const tbody = document.getElementById('tbody-cronograma');
  if (tbody) tbody.innerHTML = '<tr><td colspan="35" class="py-4">Cargando datos...</td></tr>';

  try {
    const r = await fetch(`<?= BASE_URL ?>/api/index.php?action=obtener_cronograma_mes&mes=${mesAño}`);
    const j = await r.json();

    if (j.success) {
      datosTrabajadoresGlobal = j.data || [];
      filtrarTabla();
    } else {
      alert("Error: " + j.message);
    }
  } catch (e) {
    console.error("Error al cargar cronograma:", e);
  }
};

window.filtrarTabla = function() {
  const textoBuscado = document.getElementById('filtro-buscar')?.value.toLowerCase().trim() || '';
  const areaSeleccionada = document.getElementById('filtro-area')?.value || 'TODAS';
  const mesAño = document.getElementById('filtro-mes').value;

  if (!mesAño) return;

  const [año, mes] = mesAño.split('-');
  const diasEnMes = new Date(año, mes, 0).getDate();

  const listaFiltrada = datosTrabajadoresGlobal.filter(t => {
    const nombre = (t.nombre_completo || '').toLowerCase();
    const dni = (t.dni || '').toLowerCase();
    const area = (t.area || '').toUpperCase();

    const coincideTexto = nombre.includes(textoBuscado) || dni.includes(textoBuscado);
    const coincideArea = (areaSeleccionada === 'TODAS') || (area === areaSeleccionada.toUpperCase());

    return coincideTexto && coincideArea;
  });

  const elemCant = document.getElementById('cant-activos');
  if (elemCant) elemCant.textContent = listaFiltrada.length;

  renderTabla(listaFiltrada, diasEnMes, mesAño);
};

window.renderTabla = function(trabajadores, diasEnMes, mesAño) {
  const [año, mes] = mesAño.split('-').map(Number);
  const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

  const hoyObj = new Date();
  const hoyAnio = hoyObj.getFullYear();
  const hoyMes = String(hoyObj.getMonth() + 1).padStart(2, '0');
  const hoyDia = String(hoyObj.getDate()).padStart(2, '0');
  const fechaHoyStr = `${hoyAnio}-${hoyMes}-${hoyDia}`;

  // Encabezado
  let htmlHead = `<tr>
    <th class="col-fija-nombre">TRABAJADOR (ACTIVOS)</th>
    <th class="col-fija-condicion">COND.</th>`;

  for (let d = 1; d <= diasEnMes; d++) {
    const diaNum = String(d).padStart(2, '0');
    const fechaCompleta = `${mesAño}-${diaNum}`;
    
    const fechaObj = new Date(año, mes - 1, d);
    const numDiaSemana = fechaObj.getDay();
    const nombreDia = diasSemana[numDiaSemana];
    const esFinSemana = (numDiaSemana === 0 || numDiaSemana === 6);
    
    const esHoy = (fechaCompleta === fechaHoyStr);

    // Guardamos la fecha en el atributo data-fecha-col para identificar la columna
    htmlHead += `
      <th class="th-fecha ${esFinSemana ? 'th-fin-semana' : ''} ${esHoy ? 'th-hoy' : ''}" data-fecha-col="${fechaCompleta}">
        <span class="num-dia">${diaNum}</span>
        <span class="nom-dia">${nombreDia}</span>
      </th>`;
  }

  htmlHead += `<th style="min-width:50px">ACC.</th></tr>`;
  document.getElementById('thead-dias').innerHTML = htmlHead;

  // Cuerpo
  let htmlBody = '';

  trabajadores.forEach(t => {
    const areaTexto = t.area ? t.area.toUpperCase() : 'SIN ÁREA';
    const claseColorArea = obtenerClaseArea(t.area);

    htmlBody += `<tr>
      <td class="col-fija-nombre ${claseColorArea}">
        <strong class="d-block text-dark" style="font-size: 12px;">${t.nombre_completo}</strong>
        <span class="badge bg-dark text-white" style="font-size: 9px; opacity: 0.8;">${areaTexto}</span>
      </td>
      <td class="col-fija-condicion ${claseColorArea}">
        <small style="font-size: 11px;">${t.condicion || ''}</small>
      </td>`;

    for (let d = 1; d <= diasEnMes; d++) {
      const diaNum = String(d).padStart(2, '0');
      const fechaCompleta = `${mesAño}-${diaNum}`;

      const valor = t.programacion ? (t.programacion[fechaCompleta] || '') : '';
      const observacion = t.observaciones ? (t.observaciones[fechaCompleta] || '') : '';

      const notaLimpia = observacion ? observacion.trim() : '';
      const tieneNota = notaLimpia !== '';

      htmlBody += `
        <td class="p-0 text-center celda-cronograma ${tieneNota ? 'con-observacion' : ''}"
            data-id-trabajador="${t.id_trabajador}"
            data-fecha="${fechaCompleta}"
            data-observacion="${notaLimpia}"
            style="position: relative;">
            
            <input type="text"
                   class="form-control form-control-sm text-center input-cronograma"
                   data-id="${t.id_trabajador}"
                   data-fecha="${fechaCompleta}"
                   value="${valor}"
                   maxlength="4"
                   style="width: 42px; font-weight: bold; font-size: 11px; padding: 2px;">
            
            ${tieneNota ? '<span class="indicador-nota" style="position: absolute; top: 0; right: 1px; font-size: 8px; pointer-events: none;">📌</span>' : ''}
        </td>`;
    }

    htmlBody += `<td class="text-center">
      <button class="btn btn-sm btn-outline-primary p-1" onclick="guardarFila(${t.id_trabajador})" title="Guardar cambios de esta fila">💾</button>
    </td></tr>`;
  });

  document.getElementById('tbody-cronograma').innerHTML = htmlBody;
  document.querySelectorAll('.input-cronograma').forEach(input => aplicarColorCelda(input));

  // 🔄 ASIGNAR EVENTOS DE ILUMINACIÓN AL ENCABEZADO AL PASAR EL MOUSE POR LAS CELDAS
  activarResaltadoEncabezado();
};

function activarResaltadoEncabezado() {
  const celdas = document.querySelectorAll('.celda-cronograma');

  celdas.forEach(celda => {
    // Al entrar a la celda
    celda.addEventListener('mouseenter', function() {
      const fecha = this.getAttribute('data-fecha');
      const th = document.querySelector(`th[data-fecha-col="${fecha}"]`);
      if (th) th.classList.add('th-iluminado');
    });

    // Al salir de la celda
    celda.addEventListener('mouseleave', function() {
      const fecha = this.getAttribute('data-fecha');
      const th = document.querySelector(`th[data-fecha-col="${fecha}"]`);
      if (th) th.classList.remove('th-iluminado');
    });
  });
}

// ── ASISTENTE Y RELLENO EN VIVO 22x8 ──
window.abrirModalAsistente = function() {
  const modalEl = document.getElementById('modalAsistente');
  const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  modalInstance.show();
};

window.aplicarAsistente = function() {
  const idTrabajador = document.getElementById('ast-trabajador').value;
  const tipoInicio = document.getElementById('ast-tipo-inicio').value;
  let numInicio = parseInt(document.getElementById('ast-num-inicio').value, 10);
  const mesAño = document.getElementById('filtro-mes').value;

  if (!idTrabajador) { alert("Por favor, selecciona un trabajador."); return; }
  if (isNaN(numInicio) || numInicio < 1) numInicio = 1;

  if (tipoInicio === 'T' && numInicio > 22) { alert("Para trabajo (T), el día de inicio debe ser entre 1 y 22."); return; }
  if (tipoInicio === 'L' && numInicio > 8) { alert("Para libre (L), el día de inicio debe ser entre 1 y 8."); return; }

  const [año, mes] = mesAño.split('-').map(Number);
  const diasEnMes = new Date(año, mes, 0).getDate();

  let indiceCiclo = (tipoInicio === 'T') ? (numInicio - 1) : (22 + numInicio - 1);

  for (let dia = 1; dia <= diasEnMes; dia++) {
    const diaNum = String(dia).padStart(2, '0');
    const fechaCompleta = `${mesAño}-${diaNum}`;

    let valorDia = (indiceCiclo < 22) ? `T${indiceCiclo + 1}` : `L${indiceCiclo - 21}`;

    const input = document.querySelector(`input[data-id="${idTrabajador}"][data-fecha="${fechaCompleta}"]`);
    if (input) {
      input.value = valorDia;
      aplicarColorCelda(input);
    }

    const tIndex = datosTrabajadoresGlobal.findIndex(t => t.id_trabajador == idTrabajador);
    if (tIndex !== -1) {
      if (!datosTrabajadoresGlobal[tIndex].programacion) datosTrabajadoresGlobal[tIndex].programacion = {};
      datosTrabajadoresGlobal[tIndex].programacion[fechaCompleta] = valorDia;
    }

    indiceCiclo = (indiceCiclo + 1) % 30;
  }

  const modalEl = document.getElementById('modalAsistente');
  const modal = bootstrap.Modal.getInstance(modalEl);
  if (modal) modal.hide();

  mostrarNotificacion("Secuencia 22x8 completada. Haz clic en 💾 para guardar.", "bg-info");
};

// Autocompletar correlativo al escribir en la celda T / L
document.addEventListener('change', function (e) {
  if (!e.target.classList.contains('input-cronograma')) return;

  const inputInicial = e.target;
  let valorIngresado = inputInicial.value.trim().toUpperCase();

  const soloLetraMatch = valorIngresado.match(/^(T|L)$/);
  const letraConNumeroMatch = valorIngresado.match(/^(T|L)(\d+)$/);

  if (!soloLetraMatch && !letraConNumeroMatch) return;

  const idTrabajador = inputInicial.getAttribute('data-id');
  const fechaInicial = inputInicial.getAttribute('data-fecha');
  const [añoStr, mesStr, diaInicialStr] = fechaInicial.split('-');
  const año = parseInt(añoStr, 10);
  const mes = parseInt(mesStr, 10);
  const diaInicial = parseInt(diaInicialStr, 10);
  const diasEnMes = new Date(año, mes, 0).getDate();
  const mesAño = `${añoStr}-${mesStr}`;

  const MAX_TRABAJO = 22;
  const MAX_LIBRES = 8;

  function obtenerSiguienteCorrelativo(tipoBuscado, diaLimite, maxPermitido) {
    let ultimoNumEncontrado = 0;
    for (let d = diaLimite - 1; d >= 1; d--) {
      const fechaBusqueda = `${mesAño}-${String(d).padStart(2, '0')}`;
      const inputPrevio = document.querySelector(`input[data-id="${idTrabajador}"][data-fecha="${fechaBusqueda}"]`);
      if (inputPrevio) {
        const val = inputPrevio.value.trim().toUpperCase();
        const match = val.match(new RegExp(`^${tipoBuscado}(\\d+)$`));
        if (match) {
          ultimoNumEncontrado = parseInt(match[1], 10);
          break;
        }
      }
    }
    return (ultimoNumEncontrado > 0 && ultimoNumEncontrado < maxPermitido) ? ultimoNumEncontrado + 1 : 1;
  }

  let tipoActual = '';
  let numActual = 0;

  if (soloLetraMatch) {
    tipoActual = soloLetraMatch[1];
    const maxPermitido = (tipoActual === 'T') ? MAX_TRABAJO : MAX_LIBRES;
    numActual = obtenerSiguienteCorrelativo(tipoActual, diaInicial, maxPermitido);
  } else if (letraConNumeroMatch) {
    tipoActual = letraConNumeroMatch[1];
    numActual = parseInt(letraConNumeroMatch[2], 10);
  }

  for (let dia = diaInicial; dia <= diasEnMes; dia++) {
    const diaNum = String(dia).padStart(2, '0');
    const fechaCompleta = `${mesAño}-${diaNum}`;
    const valorDia = `${tipoActual}${numActual}`;

    const inputCelda = document.querySelector(`input[data-id="${idTrabajador}"][data-fecha="${fechaCompleta}"]`);
    if (inputCelda) {
      inputCelda.value = valorDia;
      aplicarColorCelda(inputCelda);
    }

    const tIndex = datosTrabajadoresGlobal.findIndex(t => t.id_trabajador == idTrabajador);
    if (tIndex !== -1) {
      if (!datosTrabajadoresGlobal[tIndex].programacion) datosTrabajadoresGlobal[tIndex].programacion = {};
      datosTrabajadoresGlobal[tIndex].programacion[fechaCompleta] = valorDia;
    }

    if (tipoActual === 'T') {
      if (numActual < MAX_TRABAJO) numActual++;
      else {
        tipoActual = 'L';
        numActual = obtenerSiguienteCorrelativo('L', dia + 1, MAX_LIBRES);
      }
    } else if (tipoActual === 'L') {
      if (numActual < MAX_LIBRES) numActual++;
      else {
        tipoActual = 'T';
        numActual = obtenerSiguienteCorrelativo('T', dia + 1, MAX_TRABAJO);
      }
    }
  }
});

// Escuchar escritura directa
document.addEventListener('input', function(e) {
  if (e.target.classList.contains('input-cronograma')) {
    aplicarColorCelda(e.target);
  }
});

// ── GUARDAR Y EDITAR NOTAS ──
window.guardarFila = async function(idTrabajador) {
  const inputs = document.querySelectorAll(`input.input-cronograma[data-id="${idTrabajador}"]`);
  if (inputs.length === 0) { alert("No se encontraron celdas."); return; }

  const cambios = [];
  inputs.forEach(input => {
    const fecha = input.getAttribute('data-fecha');
    const celdaTd = input.closest('td');
    const observacionVal = celdaTd ? (celdaTd.getAttribute('data-observacion') || '') : '';

    cambios.push({
      id_trabajador: idTrabajador,
      fecha: fecha,
      valor: input.value.trim().toUpperCase(),
      observacion: observacionVal
    });
  });

  try {
    const response = await fetch('<?= BASE_URL ?>/api/index.php?action=guardar_cronograma_lote', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({ cambios })
    });

    const result = await response.json();
    if (result.success) {
      mostrarNotificacion("Fila guardada con éxito 💾", "bg-success");
    } else {
      alert("Error al guardar: " + (result.message || "Error desconocido"));
    }
  } catch (error) {
    console.error("Error al enviar los datos:", error);
    alert("Ocurrió un error al intentar guardar los cambios.");
  }
};

window.abrirEditorObservacion = async function(celda) {
  const idTrabajador = celda.getAttribute('data-id-trabajador');
  const fecha = celda.getAttribute('data-fecha');
  const observacionActual = celda.getAttribute('data-observacion') || '';

  const nuevaObservacion = prompt(`Escribe una nota/observación para la fecha (${fecha}):`, observacionActual);
  if (nuevaObservacion === null) return;

  const textoLimpio = nuevaObservacion.trim();

  try {
    const response = await fetch(`<?= BASE_URL ?>/api/index.php?action=guardar_observacion_cronograma`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_trabajador: idTrabajador, fecha: fecha, observacion: textoLimpio })
    });

    const result = await response.json();
    if (result.success) {
      celda.setAttribute('data-observacion', textoLimpio);
      celda.title = textoLimpio ? `💬 Nota: ${textoLimpio}` : 'Doble clic para agregar comentario';

      let spanIndicador = celda.querySelector('.indicador-nota');
      if (textoLimpio !== '') {
        celda.classList.add('con-observacion');
        if (!spanIndicador) {
          celda.insertAdjacentHTML('beforeend', '<span class="indicador-nota" style="position: absolute; top: 0; right: 1px; font-size: 8px; pointer-events: none;">📌</span>');
        }
      } else {
        celda.classList.remove('con-observacion');
        if (spanIndicador) spanIndicador.remove();
      }
      mostrarNotificacion("Nota guardada correctamente 📌", "bg-success");
    } else {
      alert('Error al guardar: ' + result.message);
    }
  } catch (error) {
    console.error('Error al guardar observación:', error);
    alert('Ocurrió un error al intentar guardar la nota.');
  }
};

// ── ALERTAS Y UTILIDADES ──
window.verificarAlertasProximas = async function() {
  try {
    const response = await fetch('<?= BASE_URL ?>/api/index.php?action=alertas_proximas');
    const result = await response.json();

    if (result.success && result.total > 0) {
      result.trabajadores.forEach(t => {
        let textoTiempo = '';
        const dias = parseInt(t.dias_faltantes);
        const esSalida = (t.tipo_alerta === 'SALIDA');

        if (esSalida) {
          // --- MENSAJES PARA SALIDA DE DÍAS LIBRES ---
          if (dias === 0) textoTiempo = 'SALE HOY A DÍAS LIBRES ✈️🏖️';
          else if (dias === 1) textoTiempo = 'Sale mañana a días libres 🌴';
          else textoTiempo = `Sale en ${dias} días a sus días libres 📅`;

          const mensaje = `<strong>${t.nombre_completo}</strong> (${t.nombre_area}): ${textoTiempo}`;
          // Usamos color azul (bg-info) o morado/oscuro para diferenciar de las llegadas
          mostrarNotificacion(mensaje, dias === 0 ? 'bg-info' : 'bg-primary');

        } else {
          // --- MENSAJES PARA LLEGADAS DE TRABAJO ---
          if (dias === 0) textoTiempo = 'LLEGA HOY 🚨';
          else if (dias === 1) textoTiempo = 'Llega mañana ⚠️';
          else textoTiempo = `Llega en ${dias} días 📅`;

          const mensaje = `<strong>${t.nombre_completo}</strong> (${t.nombre_area}): ${textoTiempo} - Turno ${t.turno}`;
          mostrarNotificacion(mensaje, dias === 0 ? 'bg-danger' : 'bg-warning');
        }
      });
    }
  } catch (error) {
    console.error('Error al verificar alertas próximas:', error);
  }
};

window.mostrarNotificacion = function(mensaje, colorClase = 'bg-success') {
  let container = document.getElementById('toastContainerGlobal');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainerGlobal';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
  }

  const toastEl = document.createElement('div');
  toastEl.className = `toast show align-items-center text-white ${colorClase} border-0 mb-2 shadow`;
  toastEl.setAttribute('role', 'alert');

  toastEl.innerHTML = `
    <div class="d-flex align-items-center">
      <div class="toast-body flex-grow-1">${mensaje}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
    </div>
  `;
  container.appendChild(toastEl);
};

window.exportarExcelCronograma = function() {
  if (!datosTrabajadoresGlobal || datosTrabajadoresGlobal.length === 0) {
    alert("No hay datos cargados en la tabla para exportar.");
    return;
  }

  const mesAño = document.getElementById('filtro-mes').value;
  const [añoStr, mesStr] = mesAño.split('-');
  const año = parseInt(añoStr, 10);
  const mes = parseInt(mesStr, 10);
  const diasEnMes = new Date(año, mes, 0).getDate();
  const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

  const filaEncabezado = ['TRABAJADOR', 'DNI', 'ÁREA', 'CONDICIÓN'];
  for (let d = 1; d <= diasEnMes; d++) {
    const fechaObj = new Date(año, mes - 1, d);
    const nomDia = diasSemana[fechaObj.getDay()];
    const numDia = String(d).padStart(2, '0');
    filaEncabezado.push(`${numDia} ${nomDia}`);
  }

  const filasDatos = [filaEncabezado];
  datosTrabajadoresGlobal.forEach(t => {
    const fila = [
      t.nombre_completo || '',
      t.dni || '',
      t.nombre_area || t.area || 'SIN ÁREA',
      t.condicion || ''
    ];
    for (let d = 1; d <= diasEnMes; d++) {
      const diaNum = String(d).padStart(2, '0');
      const fechaCompleta = `${mesAño}-${diaNum}`;
      const valor = t.programacion ? (t.programacion[fechaCompleta] || '') : '';
      fila.push(valor);
    }
    filasDatos.push(fila);
  });

  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet(filasDatos);

  ws['!cols'] = [{ wch: 32 }, { wch: 12 }, { wch: 20 }, { wch: 14 }];
  for (let d = 1; d <= diasEnMes; d++) ws['!cols'].push({ wch: 7 });

  XLSX.utils.book_append_sheet(wb, ws, `Cronograma ${mesAño}`);
  XLSX.writeFile(wb, `Cronograma_Trabajo_${mesAño}.xlsx`);
};
</script>

<?php require_once ROOT_PATH . 'views/layouts/footer.php'; ?>