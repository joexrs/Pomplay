<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>
<main class="app-content">
    <?php if (!empty($_SESSION['error'])): ?>
  <div style="background:#7f1d1d;color:#fff;padding:10px 16px;border-radius:8px;margin-bottom:16px;">
    <?= htmlspecialchars($_SESSION['error']) ?>
  </div>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php
// Obtener todos los locales
$localesStmt = $pdo->prepare("CALL GetLocalesPaginadoSimple(:p_limit, :p_offset)");
$localesStmt->execute([':p_limit' => 200, ':p_offset' => 0]);
$locales = $localesStmt->fetchAll(PDO::FETCH_ASSOC);
$localesStmt->closeCursor();

// Obtener todas las canchas con su id_local y nombre del local
$canchasStmt = $pdo->query("
    SELECT c.codigo_cancha, c.descripcion, c.id_local, l.nombre_local
    FROM cancha c
    LEFT JOIN locales l ON c.id_local = l.id_local
    ORDER BY c.id_local, c.codigo_cancha
");
$canchas = $canchasStmt->fetchAll(PDO::FETCH_ASSOC);
$canchasStmt->closeCursor();

// Generar código automático
$stmtMaxCodigo = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_video, 2) AS UNSIGNED)) AS max_num FROM video WHERE codigo_video LIKE 'V%'");
$resultMaxCodigo = $stmtMaxCodigo->fetch(PDO::FETCH_ASSOC);
$stmtMaxCodigo->closeCursor();
$nextNum = ($resultMaxCodigo['max_num'] ?? 0) + 1;
$codigoAutomatico = 'V' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

$VPS_STORAGE_URL = rtrim(getenv('VPS_STORAGE_URL') ?: 'https://cctv.pomplay.com.pe/storage.php', '/');
$VPS_KEY = getenv('VPS_KEY') ?: '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6';
?>

<div class="adm-form-wrap">
  <div class="adm-form-heading">
    
  <h1><i class="fas fa-video" style="color:var(--color-brand);margin-right:8px;"></i>Registrar Video</h1>
    <p>Complete los datos del evento. El archivo se subirá directamente al servidor por fragmentos.</p></div>

  <div class="adm-form-card">
    <!-- Formulario final (solo datos, sin archivo) -->
    <form id="form_datos_video" action="procesos/registrar.php" method="POST" autocomplete="off">

      <!-- video_url se llenará por JS después de la subida -->
      <input type="hidden" id="video_url_hidden" name="video_url" value="" />

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="codigo_video">Código Video <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(automático)</span></label>
          <input type="text" id="codigo_video" name="codigo_video"
                 class="adm-input" value="<?= htmlspecialchars($codigoAutomatico) ?>"
                 readonly style="opacity:.6;cursor:not-allowed;" required />
        </div>
        <input type="hidden" id="id_local_hidden" name="id_local" value="" />
        <div class="adm-field">
          <label class="adm-label" for="fecha_partido">Fecha del Evento</label>
          <input type="text" id="fecha_partido" name="fecha_partido"
                 class="adm-input" placeholder="Selecciona una fecha" readonly required>
        </div>
      </div>

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="hora_partido">Hora del Evento</label>
          <input type="text" id="hora_partido" name="hora_partido"
                 class="adm-input" placeholder="Selecciona la hora" readonly required>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="duracion">Duración</label>
          <input type="text" id="duracion" name="duracion"
                 class="adm-input" placeholder="Ej: 90 min" required />
        </div>
      </div>

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="select_local">Local</label>
          <select id="select_local" class="adm-select">
            <option value="">— Seleccione un local —</option>
            <?php foreach ($locales as $loc): ?>
              <option value="<?= htmlspecialchars($loc['id_local']) ?>">
                <?= htmlspecialchars($loc['nombre_local']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="codigo_cancha">Cancha</label>
          <select id="codigo_cancha" name="codigo_cancha" class="adm-select" required disabled>
            <option value="">— Primero seleccione un local —</option>
          </select>
          <small id="canchas_loading" style="display:none;margin-top:4px;color:var(--dk-accent);font-size:12px;">
            <i class="fas fa-spinner fa-spin"></i> Cargando canchas...
          </small>
        </div>
      </div>


      <div class="adm-field">
        <label class="adm-label" for="descripcion">Descripción del evento</label>
        <textarea id="descripcion" name="descripcion" class="adm-textarea"
                  placeholder="Descripción detallada del evento" required></textarea>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="video_file">
          Archivo de Video
          <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#f87171;margin-left:4px;">*</span>
        </label>
        <input type="file" id="video_file"
               class="adm-input" accept=".mp4,.avi,.mkv,.mov,.wmv,.ts,video/*"
               style="padding:8px;cursor:pointer;" />
        <small style="display:block;margin-top:4px;color:rgba(168,168,200,.6);font-size:12px;">
          Sin límite de tamaño. El archivo se envía por fragmentos directamente al servidor.
        </small>

        <!-- Info del archivo seleccionado -->
        <div id="file_info" style="display:none;margin-top:10px;padding:10px 14px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-radius:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
            <span id="file_name_display" style="font-size:13px;color:rgba(200,200,230,.9);font-family:monospace;"></span>
            <span id="file_size_display" style="font-size:12px;color:rgba(168,168,200,.6);"></span>
          </div>
        </div>
      </div>

      <!-- Barra de progreso (oculta hasta que empiece la subida) -->
      <div id="upload_progress_wrap" style="display:none;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <span style="font-size:13px;color:rgba(168,168,200,.8);">
            <i class="fas fa-cloud-upload-alt"></i>
            <span id="upload_status_text">Preparando subida...</span>
          </span>
          <span id="upload_percent_text" style="font-size:13px;font-weight:600;color:var(--dk-accent);">0%</span>
        </div>
        <div style="height:8px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;">
          <div id="upload_progress_bar"
               style="height:100%;width:0%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:4px;transition:width .3s ease;"></div>
        </div>
        <div id="upload_speed_text" style="margin-top:4px;font-size:11px;color:rgba(168,168,200,.5);text-align:right;"></div>
      </div>

      <!-- Destino en el servidor (informativo) -->
      <div id="ruta_info_box" style="display:none;margin-bottom:16px;padding:8px 14px;background:rgba(74,222,128,.05);border:1px solid rgba(74,222,128,.15);border-radius:8px;">
        <small style="color:rgba(168,168,200,.6);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Destino en el servidor</small>
        <p id="ruta_info_text" style="margin:3px 0 0;font-family:monospace;font-size:12px;color:rgba(168,168,200,.8);"></p>
      </div>

      <!-- URL generada (visible tras subida exitosa) -->
      <div id="url_generada_wrap" style="display:none;margin-bottom:16px;padding:10px 14px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.3);border-radius:8px;">
        <small style="color:#4ade80;font-size:11px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">
          <i class="fas fa-check-circle"></i> Archivo subido — URL generada
        </small>
        <p id="url_generada_text" style="margin:3px 0 0;font-family:monospace;font-size:12px;color:rgba(200,255,200,.85);word-break:break-all;"></p>
      </div>



      <hr class="adm-divider">
      <div class="adm-form-actions">
        <!-- Paso 1: botón de subir archivo al VPS -->
        <button type="button" id="btn_upload_vps" class="btn-adm-save" onclick="iniciarSubida()">
          <i class="fas fa-cloud-upload-alt"></i> Subir Video al Servidor
        </button>
        <!-- Paso 2: botón de registrar en BD (aparece después de la subida) -->
        <button type="submit" id="btn_registrar" class="btn-adm-save"
                style="display:none;background:linear-gradient(135deg,#10b981,#059669);">
          <i class="fas fa-save"></i> Registrar Video
        </button>
        <a href="<?= $baseUrl ?>/admin/videos/index.php" class="btn-adm-cancel">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
</div>

<script>
// ── Configuración del VPS ────────────────────────────────────────────────────
const VPS_STORAGE_URL = '<?= $VPS_STORAGE_URL ?>';
const VPS_KEY         = '<?= $VPS_KEY ?>';
const CHUNK_SIZE      = 1 * 1024 * 1024; // 1 MB por chunk (compatible con php.ini por defecto)

// ── Referencias DOM ──────────────────────────────────────────────────────────
const localSelect        = document.getElementById('select_local');
const canchasLoading     = document.getElementById('canchas_loading');
const canchaSelect       = document.getElementById('codigo_cancha');
const fileInput          = document.getElementById('video_file');
const fileInfo           = document.getElementById('file_info');
const fileNameDisp       = document.getElementById('file_name_display');
const fileSizeDisp       = document.getElementById('file_size_display');
const rutaInfoBox        = document.getElementById('ruta_info_box');
const rutaInfoText       = document.getElementById('ruta_info_text');
const uploadWrap         = document.getElementById('upload_progress_wrap');
const uploadBar          = document.getElementById('upload_progress_bar');
const uploadStatusText   = document.getElementById('upload_status_text');
const uploadPercentText  = document.getElementById('upload_percent_text');
const uploadSpeedText    = document.getElementById('upload_speed_text');
const urlGeneradaWrap    = document.getElementById('url_generada_wrap');
const urlGeneradaText    = document.getElementById('url_generada_text');
const videoUrlHidden     = document.getElementById('video_url_hidden');
const btnUploadVps       = document.getElementById('btn_upload_vps');
const btnRegistrar       = document.getElementById('btn_registrar');

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatBytes(bytes) {
  if (bytes < 1024)        return bytes + ' B';
  if (bytes < 1048576)     return (bytes / 1024).toFixed(1) + ' KB';
  if (bytes < 1073741824)  return (bytes / 1048576).toFixed(1) + ' MB';
  return (bytes / 1073741824).toFixed(2) + ' GB';
}

function generateUploadId() {
  return 'uid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

function getFechaFormatted() {
  // Convierte d/m/Y (flatpickr) a dd-MM-yyyy para el nombre del archivo
  const raw = document.getElementById('fecha_partido').value;
  if (!raw) return null;
  const parts = raw.split('/'); // d/m/Y
  if (parts.length !== 3) return null;
  return parts[0].padStart(2,'0') + '-' + parts[1].padStart(2,'0') + '-' + parts[2];
}

function getHoraFormatted() {
  // Convierte HH:mm (o HH:mm:ss) a HH-mm-ss
  const raw = document.getElementById('hora_partido').value;
  if (!raw) return null;
  const parts = raw.split(':');
  const h = (parts[0] || '00').padStart(2,'0');
  const m = (parts[1] || '00').padStart(2,'0');
  const s = (parts[2] || '00').padStart(2,'0');
  return h + '-' + m + '-' + s;
}

// ── Filtrado de canchas por local (AJAX) ─────────────────────────────────────
localSelect.addEventListener('change', function () {
  const idLocal = this.value;
  const idLocalHidden = document.getElementById('id_local_hidden');

  // Resetear cancha y estado
  canchaSelect.innerHTML = '<option value="">— Seleccione una cancha —</option>';
  canchaSelect.disabled  = true;
  idLocalHidden.value    = '';
  updateRutaInfo();

  if (!idLocal) return;

  // Mostrar spinner y cargar canchas del local
  canchasLoading.style.display = 'block';

  fetch('get_canchas_by_local.php?id_local=' + encodeURIComponent(idLocal))
    .then(function (r) {
      return r.text().then(function (raw) {
        try {
          return JSON.parse(raw);
        } catch (e) {
          console.error('Respuesta inválida de get_canchas_by_local.php:', raw);
          throw new Error('Respuesta inválida del servidor');
        }
      });
    })
    .then(function (data) {
      canchasLoading.style.display = 'none';
      if (!data.ok || !data.canchas.length) {
        canchaSelect.innerHTML = '<option value="">— Sin canchas disponibles —</option>';
        return;
      }
      canchaSelect.innerHTML = '<option value="">— Seleccione una cancha —</option>';
      data.canchas.forEach(function (c) {
        const opt = document.createElement('option');
        opt.value = c.codigo_cancha;
        opt.setAttribute('data-id-local', c.id_local);
        opt.textContent = c.descripcion + ' (' + c.codigo_cancha + ')';
        canchaSelect.appendChild(opt);
      });
      canchaSelect.disabled = false;
      idLocalHidden.value   = idLocal;
    })
    .catch(function () {
      canchasLoading.style.display = 'none';
      canchaSelect.innerHTML = '<option value="">— Error al cargar canchas —</option>';
    });
});

// ── Actualizar info de ruta al cambiar cancha o archivo ──────────────────────
function updateRutaInfo() {
  const idLocal = document.getElementById('id_local_hidden').value;
  const codigo  = canchaSelect.value;

  if (idLocal && codigo) {
    rutaInfoText.textContent = '/opt/cctv/recordings/' + idLocal + '/' + codigo + '/';
    rutaInfoBox.style.display = 'block';
  } else {
    rutaInfoBox.style.display = 'none';
  }
}

fileInput.addEventListener('change', function () {
  if (this.files.length > 0) {
    const f = this.files[0];
    fileNameDisp.textContent = f.name;
    fileSizeDisp.textContent = formatBytes(f.size);
    fileInfo.style.display = 'block';
    // Resetear estado si cambia el archivo
    urlGeneradaWrap.style.display = 'none';
    btnRegistrar.style.display = 'none';
    btnUploadVps.style.display = '';
    videoUrlHidden.value = '';
  } else {
    fileInfo.style.display = 'none';
  }
});

canchaSelect.addEventListener('change', updateRutaInfo);

// ── Subida por chunks al VPS ─────────────────────────────────────────────────
async function iniciarSubida() {
  // --- Validaciones previas ---
  const file        = fileInput.files[0];
  const codigoCancha = canchaSelect.value;
  const opt         = canchaSelect.options[canchaSelect.selectedIndex];
  const idLocal     = opt ? opt.getAttribute('data-id-local') : '';
  const fecha       = getFechaFormatted();
  const hora        = getHoraFormatted();

  if (!file)         return alert('Seleccione un archivo de video.');
  if (!codigoCancha) return alert('Seleccione una cancha.');
  if (!idLocal)      return alert('La cancha seleccionada no tiene local asignado.');
  if (!fecha)        return alert('Seleccione la fecha del evento.');
  if (!hora)         return alert('Seleccione la hora del evento.');

  const ext = file.name.split('.').pop().toLowerCase();
  const allowedExt = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'ts'];
  if (!allowedExt.includes(ext)) {
    return alert('Formato no permitido. Use: ' + allowedExt.join(', '));
  }

  // --- Preparar subida ---
  const uploadId   = generateUploadId();
  const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

  btnUploadVps.disabled = true;
  btnUploadVps.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
  uploadWrap.style.display = 'block';
  urlGeneradaWrap.style.display = 'none';
  btnRegistrar.style.display = 'none';

  let startTime  = Date.now();
  let bytesSent  = 0;

  // --- Enviar chunks uno a uno ---
  for (let i = 0; i < totalChunks; i++) {
    const start = i * CHUNK_SIZE;
    const end   = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);

    const formData = new FormData();
    formData.append('action',       'upload_chunk');
    formData.append('key',          VPS_KEY);
    formData.append('upload_id',    uploadId);
    formData.append('chunk_index',  i);
    formData.append('total_chunks', totalChunks);
    formData.append('orig_ext',     ext);
    formData.append('chunk',        chunk, 'chunk_' + i);

    let resp;
    try {
      const response = await fetch(VPS_STORAGE_URL, { method: 'POST', body: formData });
      const rawText = await response.text();
      try {
        resp = JSON.parse(rawText);
      } catch (jsonErr) {
        return onUploadError(
          'Respuesta inválida del servidor en chunk ' + i + ' (HTTP ' + response.status + '). ' +
          'Respuesta: ' + rawText.substring(0, 200),
          btnUploadVps
        );
      }
    } catch (e) {
      return onUploadError('Error de red en chunk ' + i + ': ' + e.message, btnUploadVps);
    }

    if (!resp.ok) {
      return onUploadError('Error en chunk ' + i + ': ' + (resp.error || 'desconocido'), btnUploadVps);
    }

    // Actualizar progreso
    bytesSent += (end - start);
    const pct      = Math.round((bytesSent / file.size) * 100);
    const elapsed  = (Date.now() - startTime) / 1000;
    const speed    = bytesSent / elapsed;
    const remaining = (file.size - bytesSent) / speed;

    uploadBar.style.width        = pct + '%';
    uploadPercentText.textContent = pct + '%';
    uploadStatusText.textContent  = 'Subiendo... chunk ' + (i + 1) + ' de ' + totalChunks;
    uploadSpeedText.textContent   = formatBytes(speed) + '/s · ' +
      'enviado: ' + formatBytes(bytesSent) + ' de ' + formatBytes(file.size) +
      (remaining > 0 ? ' · ~' + Math.ceil(remaining) + 's restantes' : '');
  }

  // --- Finalizar: ensamblar en el VPS ---
  uploadStatusText.textContent = 'Ensamblando archivo en el servidor...';
  uploadBar.style.width = '100%';
  uploadPercentText.textContent = '100%';

  const finalFormData = new FormData();
  finalFormData.append('action',        'finalize_upload');
  finalFormData.append('key',           VPS_KEY);
  finalFormData.append('upload_id',     uploadId);
  finalFormData.append('id_local',      idLocal);
  finalFormData.append('codigo_cancha', codigoCancha);
  finalFormData.append('fecha',         fecha);
  finalFormData.append('hora',          hora);
  finalFormData.append('orig_ext',      ext);
  finalFormData.append('total_chunks',  totalChunks);

  let finalResp;
  try {
    const response = await fetch(VPS_STORAGE_URL, { method: 'POST', body: finalFormData });
    const rawText = await response.text();
    try {
      finalResp = JSON.parse(rawText);
    } catch (jsonErr) {
      return onUploadError(
        'El servidor no respondió correctamente al finalizar (HTTP ' + response.status + '). ' +
        'Posible timeout del servidor. Respuesta: ' + rawText.substring(0, 300),
        btnUploadVps
      );
    }
  } catch (e) {
    return onUploadError('Error de red al finalizar la subida: ' + e.message, btnUploadVps);
  }

  if (!finalResp.ok) {
    return onUploadError('Error al ensamblar: ' + (finalResp.error || 'desconocido'), btnUploadVps);
  }

  // --- Subida exitosa ---
  const videoUrl = finalResp.video_url;
  videoUrlHidden.value = videoUrl;

  uploadStatusText.textContent = ' Archivo subido correctamente (' + (finalResp.size_mb || '?') + ' MB)';
  uploadBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
  uploadSpeedText.textContent = '';

  urlGeneradaText.textContent = videoUrl;
  urlGeneradaWrap.style.display = 'block';

  btnUploadVps.style.display = 'none';
  btnRegistrar.style.display = '';

  // Scroll hacia el botón de registrar
  btnRegistrar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function onUploadError(msg, btn) {
  uploadStatusText.textContent = ' No se pudo completar la subida';
  uploadBar.style.background = '#ef4444';
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-redo"></i> Reintentar subida';

  // Detalle técnico solo en consola, para soporte/debugging
  console.error('Error de subida:', msg);

  // Mensaje amigable al usuario (sin jerga técnica ni códigos HTTP)
  mostrarMensajeError(
    'No se pudo completar la subida del video. Verifica tu conexión e inténtalo de nuevo.'
  );
}

function mostrarMensajeError(mensaje) {
  let box = document.getElementById('upload_error_box');
  if (!box) {
    box = document.createElement('div');
    box.id = 'upload_error_box';
    box.style.cssText = 'margin-top:12px;padding:10px 14px;background:rgba(239,68,68,.08);' +
      'border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#fca5a5;font-size:13px;';
    uploadWrap.insertAdjacentElement('afterend', box);
  }
  box.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + mensaje;
  box.style.display = 'block';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Validar antes de enviar el formulario final ──────────────────────────────
document.getElementById('form_datos_video').addEventListener('submit', function (e) {
  if (!videoUrlHidden.value) {
    e.preventDefault();
    alert('Debe subir el archivo de video al servidor antes de registrar.');
    return;
  }
  btnRegistrar.disabled = true;
  btnRegistrar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
});
</script>

</main>
<?php include '../shared/footer_admin.php'; ?>