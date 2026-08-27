<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>
<main class="app-content">
<?php
$codigo_video = $_GET['codigo_video'] ?? null;
if (!$codigo_video) { header("Location: " . $baseUrl . "/admin/videos/index.php"); exit(); }

// Usar SELECT directo en lugar de stored procedure para evitar problemas de formato
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        c.descripcion AS descripcion_cancha,
        c.id_local
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.codigo_video = :codigo_video
");
$stmt->execute(['codigo_video' => $codigo_video]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

// DEBUG: Ver qué datos trae
error_log("=== DEBUG EDIT VIDEO (SELECT DIRECTO) ===");
error_log("Código: " . $codigo_video);
error_log("Video encontrado: " . ($video ? "SÍ" : "NO"));
if ($video) {
    error_log("Fecha en BD: " . ($video['fecha_partido'] ?? 'NULL'));
    error_log("Hora en BD: " . ($video['hora_partido'] ?? 'NULL'));
    error_log("Tipo fecha: " . gettype($video['fecha_partido']));
}

if (!$video) { header("Location: " . $baseUrl . "/admin/videos/index.php"); exit(); }

$canchasStmt = $pdo->query("CALL GetAllCanchas()");
$canchas = $canchasStmt->fetchAll(PDO::FETCH_ASSOC);
$canchasStmt->closeCursor();

$VPS_STORAGE_URL = rtrim(getenv('VPS_STORAGE_URL') ?: 'https://cctv.pomplay.com.pe/storage.php', '/');
$VPS_KEY = getenv('VPS_KEY') ?: '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6';
?>

<div class="adm-form-wrap">
  

  <div class="adm-form-heading">
    <h1><i class="fas fa-pencil-alt" style="margin-right:8px;"></i>Editar Video</h1>
    <p>Modifique los datos del video seleccionado</p>
  </div>

  <div class="adm-form-card">
    <form id="form_edit_video" action="procesos/procesar_edit.php" method="POST" autocomplete="off">
      <input type="hidden" name="codigo_video" value="<?= htmlspecialchars($video['codigo_video']) ?>" />

      <!-- video_url se llenará por JS tras una nueva subida, o conserva el valor actual -->
      <input type="hidden" id="video_url_hidden" name="video_url" value="<?= htmlspecialchars($video['video_url']) ?>" />

      <!-- Código (readonly) -->
      <div class="adm-field">
        <label class="adm-label">Código del Video</label>
        <input type="text" class="adm-input"
               value="<?= htmlspecialchars($video['codigo_video']) ?>"
               readonly style="opacity:.6;cursor:not-allowed;" />
      </div>

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="fecha_partido">Fecha del Evento</label>
          <?php 
          // Convertir fecha de Y-m-d a d/m/Y para flatpickr
          $fecha_valor = $video['fecha_partido'] ?? '';
          
          if (!empty($fecha_valor) && $fecha_valor !== '0000-00-00') {
              // Convertir de Y-m-d a d/m/Y para flatpickr
              $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_valor);
              if ($fecha_obj) {
                  $fecha_display = $fecha_obj->format('d/m/Y');
              } else {
                  $fecha_display = date('d/m/Y');
              }
          } else {
              $fecha_display = date('d/m/Y');
          }
          
          error_log("Fecha para flatpickr: {$fecha_display}");
          ?>
          <input type="text" id="fecha_partido" name="fecha_partido" class="adm-input" 
                 value="<?= htmlspecialchars($fecha_display) ?>" 
                 placeholder="Selecciona una fecha" readonly required />
        </div>
        <div class="adm-field">
          <label class="adm-label" for="hora_partido">Hora del Evento</label>
          <?php 
          // Convertir hora de H:i:s a H:i para flatpickr
          $hora_valor = $video['hora_partido'] ?? '';
          
          if (!empty($hora_valor) && $hora_valor !== '00:00:00') {
              // Extraer solo H:i
              $hora_display = substr($hora_valor, 0, 5);
          } else {
              $hora_display = date('H:i');
          }
          
          error_log("Hora para flatpickr: {$hora_display}");
          ?>
          <input type="text" id="hora_partido" name="hora_partido" class="adm-input" 
                 value="<?= htmlspecialchars($hora_display) ?>" 
                 placeholder="Selecciona la hora" readonly required />
        </div>
      </div>

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="codigo_cancha">Cancha</label>
          <select id="codigo_cancha" name="codigo_cancha" class="adm-select" required>
            <option value="">Seleccione una cancha</option>
            <?php foreach ($canchas as $c): ?>
              <option value="<?= htmlspecialchars($c['codigo_cancha']) ?>"
                      data-id-local="<?= htmlspecialchars($c['id_local']) ?>"
                <?= ($c['codigo_cancha'] == $video['codigo_cancha']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['descripcion']) ?> (<?= htmlspecialchars($c['codigo_cancha']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="duracion">Duración</label>
          <input type="text" id="duracion" name="duracion" class="adm-input"
                 value="<?= htmlspecialchars($video['duracion']) ?>" placeholder="Ej: 90 min" required />
        </div>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" class="adm-textarea" required><?= htmlspecialchars($video['descripcion']) ?></textarea>
      </div>

      <!-- URL actual del video (informativo) -->
      <div class="adm-field" id="url_actual_wrap">
        <label class="adm-label">URL actual del Video <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(se reemplazará si subes un nuevo archivo)</span></label>
        <input type="text" id="url_actual_display" class="adm-input"
               value="<?= htmlspecialchars($video['video_url']) ?>" readonly
               style="font-family:monospace;font-size:13px;opacity:.7;cursor:not-allowed;" />
      </div>

      <!-- Sección de reemplazo de video -->
      <div class="adm-field" style="margin-top:8px;">
        <label class="adm-label" for="video_file">
          Reemplazar Archivo de Video
          <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.45);margin-left:4px;">(opcional)</span>
        </label>
        <input type="file" id="video_file"
               class="adm-input" accept=".mp4,.avi,.mkv,.mov,.wmv,.ts,video/*"
               style="padding:8px;cursor:pointer;" />
        <small style="display:block;margin-top:4px;color:rgba(168,168,200,.6);font-size:12px;">
          Sin límite de tamaño. El archivo se envía por fragmentos directamente al servidor. Si no seleccionas un archivo, se conserva la URL actual.
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
          <i class="fas fa-check-circle"></i> Nuevo archivo subido — URL generada
        </small>
        <p id="url_generada_text" style="margin:3px 0 0;font-family:monospace;font-size:12px;color:rgba(200,255,200,.85);word-break:break-all;"></p>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="observacion">Observaciones <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(opcional)</span></label>
        <textarea id="observacion" name="observacion" class="adm-textarea"
                  placeholder="Observaciones adicionales sobre el evento"><?= htmlspecialchars($video['observacion']) ?></textarea>
      </div>

      <hr class="adm-divider">
      <div class="adm-form-actions">
        <!-- Botón de subir archivo al VPS (solo visible cuando hay archivo seleccionado) -->
        <button type="button" id="btn_upload_vps" class="btn-adm-save"
                style="display:none;" onclick="iniciarSubida()">
          <i class="fas fa-cloud-upload-alt"></i> Subir Nuevo Video al Servidor
        </button>
        <!-- Botón principal de guardar -->
        <button type="submit" id="btn_actualizar" class="btn-adm-save">
          <i class="fas fa-check"></i> Actualizar Video
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
const CHUNK_SIZE      = 1 * 1024 * 1024; // 1 MB por chunk

// ── Referencias DOM ──────────────────────────────────────────────────────────
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
const urlActualDisplay   = document.getElementById('url_actual_display');
const btnUploadVps       = document.getElementById('btn_upload_vps');
const btnActualizar      = document.getElementById('btn_actualizar');

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

// ── Actualizar info de ruta al cambiar cancha ─────────────────────────────────
function updateRutaInfo() {
  const opt     = canchaSelect.options[canchaSelect.selectedIndex];
  const idLocal = opt ? opt.getAttribute('data-id-local') : '';
  const codigo  = canchaSelect.value;

  if (idLocal && codigo) {
    rutaInfoText.textContent = '/opt/cctv/recordings/' + idLocal + '/' + codigo + '/';
    rutaInfoBox.style.display = 'block';
  } else {
    rutaInfoBox.style.display = 'none';
  }
}

canchaSelect.addEventListener('change', function () {
  updateRutaInfo();
  // Si hay un archivo seleccionado y la subida fue exitosa, advertir al usuario
  if (fileInput.files.length > 0 && urlGeneradaWrap.style.display !== 'none') {
    urlGeneradaWrap.style.display = 'none';
    btnUploadVps.style.display    = '';
    videoUrlHidden.value          = '<?= htmlspecialchars($video['video_url']) ?>';
    urlActualDisplay.value        = videoUrlHidden.value;
    uploadWrap.style.display      = 'none';
  }
});

// ── Manejo del input de archivo ───────────────────────────────────────────────
fileInput.addEventListener('change', function () {
  if (this.files.length > 0) {
    const f = this.files[0];
    fileNameDisp.textContent = f.name;
    fileSizeDisp.textContent = formatBytes(f.size);
    fileInfo.style.display = 'block';

    // Mostrar botón de subida
    btnUploadVps.style.display = '';

    // Resetear estado de subida anterior
    urlGeneradaWrap.style.display = 'none';
    uploadWrap.style.display = 'none';
    uploadBar.style.width = '0%';
    uploadBar.style.background = 'linear-gradient(90deg,#6366f1,#8b5cf6)';
    uploadPercentText.textContent = '0%';
    uploadSpeedText.textContent = '';

    // Mantener la url_hidden con el valor actual hasta que se complete la subida
    videoUrlHidden.value = '<?= htmlspecialchars($video['video_url']) ?>';

    // Actualizar info de ruta
    updateRutaInfo();
  } else {
    fileInfo.style.display = 'none';
    btnUploadVps.style.display = 'none';
  }
});

// ── Subida por chunks al VPS ─────────────────────────────────────────────────
async function iniciarSubida() {
  // --- Validaciones previas ---
  const file         = fileInput.files[0];
  const opt          = canchaSelect.options[canchaSelect.selectedIndex];
  const codigoCancha = canchaSelect.value;
  const idLocal      = opt ? opt.getAttribute('data-id-local') : '';
  const fecha        = getFechaFormatted();
  const hora         = getHoraFormatted();

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
  const uploadId    = generateUploadId();
  const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

  btnUploadVps.disabled = true;
  btnUploadVps.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
  uploadWrap.style.display = 'block';
  urlGeneradaWrap.style.display = 'none';

  let startTime = Date.now();
  let bytesSent = 0;

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
      resp = await response.json();
    } catch (e) {
      return onUploadError('Error de red en chunk ' + i + ': ' + e.message);
    }

    if (!resp.ok) {
      return onUploadError('Error en chunk ' + i + ': ' + (resp.error || 'desconocido'));
    }

    // Actualizar progreso
    bytesSent += (end - start);
    const pct       = Math.round((bytesSent / file.size) * 100);
    const elapsed   = (Date.now() - startTime) / 1000;
    const speed     = bytesSent / elapsed;
    const remaining = (file.size - bytesSent) / speed;

    uploadBar.style.width         = pct + '%';
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
    finalResp = await response.json();
  } catch (e) {
    return onUploadError('Error al finalizar la subida: ' + e.message);
  }

  if (!finalResp.ok) {
    return onUploadError('Error al ensamblar: ' + (finalResp.error || 'desconocido'));
  }

  // --- Subida exitosa ---
  const videoUrl = finalResp.video_url;
  videoUrlHidden.value = videoUrl;
  urlActualDisplay.value = videoUrl;

  uploadStatusText.textContent = '✓ Archivo subido correctamente (' + (finalResp.size_mb || '?') + ' MB)';
  uploadBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
  uploadSpeedText.textContent = '';

  urlGeneradaText.textContent = videoUrl;
  urlGeneradaWrap.style.display = 'block';

  btnUploadVps.style.display = 'none';

  // Scroll hacia el botón de actualizar
  btnActualizar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function onUploadError(msg) {
  uploadStatusText.textContent = '✗ ' + msg;
  uploadBar.style.background = '#ef4444';
  btnUploadVps.disabled = false;
  btnUploadVps.innerHTML = '<i class="fas fa-redo"></i> Reintentar subida';
  alert('Error: ' + msg);
}

// ── Validar antes de enviar el formulario final ──────────────────────────────
document.getElementById('form_edit_video').addEventListener('submit', function (e) {
  // Si hay un archivo seleccionado pero NO se ha subido aún, bloquear
  if (fileInput.files.length > 0 && urlGeneradaWrap.style.display === 'none') {
    e.preventDefault();
    alert('Hay un archivo de video seleccionado que aún no se ha subido. Haz clic en "Subir Nuevo Video al Servidor" antes de guardar, o quita la selección del archivo.');
    return;
  }
  if (!videoUrlHidden.value) {
    e.preventDefault();
    alert('El video no tiene URL asignada. Sube un archivo de video primero.');
    return;
  }
  btnActualizar.disabled = true;
  btnActualizar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
});
</script>

</main>
<?php include '../shared/footer_admin.php'; ?>
