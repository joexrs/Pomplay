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
        c.descripcion AS descripcion_cancha
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
?>

<div class="adm-form-wrap">
  

  <div class="adm-form-heading">
    <h1><i class="fas fa-pencil-alt" style="margin-right:8px;"></i>Editar Video</h1>
    <p>Modifique los datos del video seleccionado</p>
  </div>

  <div class="adm-form-card">
    <form action="procesos/procesar_edit.php" method="POST" autocomplete="off">
      <input type="hidden" name="codigo_video" value="<?= htmlspecialchars($video['codigo_video']) ?>" />

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

      <div class="adm-field">
        <label class="adm-label" for="ruta_base_edit">Ubicación Base del Video</label>
        <select id="ruta_base_edit" class="adm-select">
          <option value="">Seleccione ubicación o use explorador</option>
          <option value="\\servidor\videos\">Servidor Principal (\\servidor\videos\)</option>
          <option value="\\192.168.1.100\storage\videos\">Servidor Backup (\\192.168.1.100\storage\videos\)</option>
          <option value="/mnt/ddr/videos/">DDR Local (/mnt/ddr/videos/)</option>
          <option value="/mnt/camera1/recordings/">Cámara 1 (/mnt/camera1/recordings/)</option>
          <option value="/mnt/camera2/recordings/">Cámara 2 (/mnt/camera2/recordings/)</option>
          <option value="custom">✏️ Escribir ruta personalizada</option>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="video_file_selector_edit">
          Explorador de Archivos
          <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(opcional)</span>
        </label>
        <input type="file" id="video_file_selector_edit" class="adm-input" 
               accept="video/*,.mp4,.avi,.mkv,.mov,.wmv" 
               style="padding:8px;cursor:pointer;" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="video_url">Ruta Completa del Video</label>
        <input type="text" id="video_url" name="video_url" class="adm-input"
               value="<?= htmlspecialchars($video['video_url']) ?>" required 
               style="font-family:monospace;font-size:13px;" />
        
      </div>

      <div class="adm-field">
        <label class="adm-label" for="observacion">Observaciones <span style="font-weight:400;text-transform:none;letter-spacing:0;color:rgba(168,168,200,.35)">(opcional)</span></label>
        <textarea id="observacion" name="observacion" class="adm-textarea"
                  placeholder="Observaciones adicionales sobre el evento"><?= htmlspecialchars($video['observacion']) ?></textarea>
      </div>

      <hr class="adm-divider">
      <div class="adm-form-actions">
        <button type="submit" class="btn-adm-save">
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
(function() {
  const rutaBaseSelect = document.getElementById('ruta_base_edit');
  const videoFileInput = document.getElementById('video_file_selector_edit');
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
