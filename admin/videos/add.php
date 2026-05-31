<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>
<main class="app-content">
<?php
$canchasStmt = $pdo->query("CALL GetAllCanchas()");
$canchas = $canchasStmt->fetchAll(PDO::FETCH_ASSOC);
$canchasStmt->closeCursor();

// Generar código automático
$stmtMaxCodigo = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_video, 2) AS UNSIGNED)) AS max_num FROM video WHERE codigo_video LIKE 'V%'");
$resultMaxCodigo = $stmtMaxCodigo->fetch(PDO::FETCH_ASSOC);
$stmtMaxCodigo->closeCursor();
$nextNum = ($resultMaxCodigo['max_num'] ?? 0) + 1;
$codigoAutomatico = 'V' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
?>

<div class="adm-form-wrap">
  <div class="adm-form-heading">
    <h1><i class="fas fa-video" style="color:var(--dk-accent);margin-right:8px;"></i>Registrar Video</h1>
    <p>Complete los datos del evento para registrar el video</p>
  </div>

  <div class="adm-form-card">
    <form id="form_ingreso_video" action="procesos/registrar.php" method="POST" autocomplete="off">

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="codigo_video">Código Video <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(automático)</span></label>
          <input type="text" id="codigo_video" name="codigo_video"
                 class="adm-input" value="<?= htmlspecialchars($codigoAutomatico) ?>" readonly style="opacity:.6;cursor:not-allowed;" required />
        </div>
        <div class="adm-field">
          <label class="adm-label" for="fecha_partido">Fecha del Evento</label>
          <input type="text" id="fecha_partido" name="fecha_partido" class="adm-input" placeholder="Selecciona una fecha" readonly required>
        </div>
      </div>

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="hora_partido">Hora del Evento</label>
          <input type="text" id="hora_partido" name="hora_partido" class="adm-input" placeholder="Selecciona la hora" readonly required>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="codigo_cancha">Cancha</label>
          <select id="codigo_cancha" name="codigo_cancha" class="adm-select" required>
            <option value="">Seleccione una cancha</option>
            <?php foreach ($canchas as $c): ?>
              <option value="<?= htmlspecialchars($c['codigo_cancha']) ?>">
                <?= htmlspecialchars($c['descripcion']) ?> (<?= htmlspecialchars($c['codigo_cancha']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="descripcion">Descripción del evento</label>
        <textarea id="descripcion" name="descripcion" class="adm-textarea"
                  placeholder="Descripción detallada del evento" required></textarea>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="ruta_base">Ubicación Base del Video</label>
        <select id="ruta_base" class="adm-select">
          <option value="">Seleccione ubicación o use explorador</option>
          <option value="\\servidor\videos\">Servidor Principal (\\servidor\videos\)</option>
          <option value="\\192.168.1.100\storage\videos\">Servidor Backup (\\192.168.1.100\storage\videos\)</option>
          <option value="/mnt/ddr/videos/">DDR Local (/mnt/ddr/videos/)</option>
          <option value="/mnt/camera1/recordings/">Cámara 1 (/mnt/camera1/recordings/)</option>
          <option value="/mnt/camera2/recordings/">Cámara 2 (/mnt/camera2/recordings/)</option>
          <option value="custom">✏️ Escribir ruta personalizada</option>
        </select>
      </div>

      <div class="adm-grid-2">
        <div class="adm-field">
          <label class="adm-label" for="video_file_selector">
            Explorador de Archivos
            <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(255, 251, 251, 0.96)">(opcional)</span>
          </label>
          <input type="file" id="video_file_selector" class="adm-input" 
                 accept="video/*,.mp4,.avi,.mkv,.mov,.wmv" 
                 style="padding:8px;cursor:pointer;" />
          <small style="display:block;margin-top:4px;color:rgba(168,168,200,.6);font-size:12px;">
            Explore y seleccione el archivo para obtener su ruta automáticamente
          </small>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="duracion">Duración</label>
          <input type="text" id="duracion" name="duracion"
                 class="adm-input" placeholder="Ej: 90 min" required />
        </div>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="video_url">
          Ruta Completa del Video
          <span style="font-weight:400;color:#4ade80;margin-left:8px;">●</span>
        </label>
        <input type="text" id="video_url" name="video_url"
               class="adm-input" placeholder="Se generará automáticamente o escríbala manualmente" required 
               style="font-family:monospace;font-size:13px;" />
        <small style="display:block;margin-top:4px;color:rgba(168,168,200,.6);font-size:12px;">
          Esta ruta se genera automáticamente al seleccionar ubicación + archivo, o puede escribirla manualmente
        </small>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="observacion">Observaciones <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(opcional)</span></label>
        <textarea id="observacion" name="observacion" class="adm-textarea"
                  placeholder="Observaciones adicionales sobre el evento"></textarea>
      </div>

      <hr class="adm-divider">
      <div class="adm-form-actions">
        <button type="submit" id="btn_registrar" class="btn-adm-save">
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
(function() {
  const rutaBaseSelect = document.getElementById('ruta_base');
  const videoFileInput = document.getElementById('video_file_selector');
  const videoUrlInput = document.getElementById('video_url');

  // Función para construir la ruta completa
  function construirRuta() {
    const rutaBase = rutaBaseSelect.value;
    
    // Si seleccionó "custom", permitir escritura manual
    if (rutaBase === 'custom') {
      videoUrlInput.readOnly = false;
      videoUrlInput.placeholder = 'Escriba la ruta completa manualmente';
      videoUrlInput.style.backgroundColor = '';
      return;
    }

    // Si hay archivo seleccionado
    if (videoFileInput.files.length > 0) {
      const file = videoFileInput.files[0];
      const fileName = file.name;
      
      // Si hay ruta base, combinar
      if (rutaBase) {
        videoUrlInput.value = rutaBase + fileName;
      } else {
        // Solo el nombre del archivo si no hay ruta base
        videoUrlInput.value = fileName;
      }
      
      videoUrlInput.readOnly = false;
    } else if (rutaBase && rutaBase !== 'custom') {
      // Solo ruta base sin archivo
      videoUrlInput.value = rutaBase;
      videoUrlInput.placeholder = 'Seleccione un archivo o complete la ruta manualmente';
      videoUrlInput.readOnly = false;
    }
  }

  // Eventos
  rutaBaseSelect.addEventListener('change', construirRuta);
  videoFileInput.addEventListener('change', construirRuta);

  // Permitir edición manual siempre
  videoUrlInput.addEventListener('focus', function() {
    this.readOnly = false;
  });
})();
</script>

</main>
<?php include '../shared/footer_admin.php'; ?>
