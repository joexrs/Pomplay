<?php 
// 0. INICIAR LA SESIÓN (Crucial para que las variables $_SESSION existan)
session_start();

// 1. PRIMERO TODA LA LÓGICA DE PHP Y REDIRECCIONES
include __DIR__ . '/../conexion.php'; 

$userId = $_SESSION['user_id'] ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;
$localId = $_SESSION['id_local'] ?? null;

if (!$userId || !$propietarioId) {
    die('Acceso denegado');
}

try {
    // Obtener locales del propietario
    $stmtLocales = $pdo->prepare("CALL GetOwnerLocalList(:id_propietario)");
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
} catch (PDOException $e) {
    die('Error al obtener locales: ' . $e->getMessage());
}

// Si no hay local seleccionado, usar el primero
if (!$localId && !empty($locales)) {
    $localId = $locales[0]['id_local'];
    $_SESSION['id_local'] = $localId;
}

if (!$localId) {
    die('No tienes locales asignados');
}

// Cambiar local si se selecciona uno diferente
if (isset($_GET['change_local']) && !empty($_GET['change_local'])) {
    $newLocalId = (int)$_GET['change_local'];
    // Verificar que el local pertenezca al propietario
    $valid = false;
    foreach ($locales as $loc) {
        if ($loc['id_local'] == $newLocalId) {
            $valid = true;
            break;
        }
    }
    if ($valid) {
        $_SESSION['id_local'] = $newLocalId;
        $localId = $newLocalId;
        header('Location: canchas.php'); // Redirección segura, no hay HTML previo
        exit;
    }
}

try {
    // Obtener canchas del local seleccionado
    $stmtCanchas = $pdo->prepare("CALL GetCanchasByLocal(:p_id_local)");
    $stmtCanchas->execute([':p_id_local' => $localId]);
    $canchas = $stmtCanchas->fetchAll(PDO::FETCH_ASSOC);
    $stmtCanchas->closeCursor();
    
    // Verificar estado de conexión de cada cámara
    foreach ($canchas as &$cancha) {
        $cancha['camera_status'] = verificarEstadoCamaraBD($cancha, $pdo);
    }
    unset($cancha); // Romper la referencia
} catch (PDOException $e) {
    $canchas = [];
    $errorCanchas = 'Error al obtener canchas: ' . $e->getMessage();
}

// Función para verificar el estado de conexión de la cámara desde BD
function verificarEstadoCamaraBD($cancha, $pdo) {
    $codigoCancha = $cancha['codigo_cancha'];
    
    // Verificar si hay un proceso de grabación activo
    $pidFile = __DIR__ . '/../temp/pids/' . $codigoCancha . '_recording.pid';
    $grabando = false;
    
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        $isLinux = strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN';
        
        if ($isLinux) {
            exec("ps -p $pid 2>/dev/null", $output);
            $grabando = count($output) > 1;
        } else {
            exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
            foreach ($output as $line) {
                if (strpos($line, (string)$pid) !== false) {
                    $grabando = true;
                    break;
                }
            }
        }
    }
    
    // Verificar si la cámara tiene configuración en BD
    if (empty($cancha['camera_ip']) || empty($cancha['rtsp_url'])) {
        return [
            'conectada' => false,
            'grabando' => false,
            'error' => 'No configurada'
        ];
    }
    
    // Intentar hacer ping a la IP de la cámara (verificación rápida)
    $isLinux = strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN';
    
    if ($isLinux) {
        exec("ping -c 1 -W 1 " . escapeshellarg($cancha['camera_ip']) . " 2>&1", $pingOutput, $pingReturn);
    } else {
        exec("ping -n 1 -w 1000 " . escapeshellarg($cancha['camera_ip']) . " 2>&1", $pingOutput, $pingReturn);
    }
    
    $conectada = ($pingReturn === 0);
    
    return [
        'conectada' => $conectada,
        'grabando' => $grabando
    ];
}
?>

<?php 
// 2. RECIÉN AQUÍ SE INCLUYE EL DISEÑO VISUAL
include __DIR__ . '/../admin/shared/header_admin.php'; 
?>

<style>
  /* Tabla estilo tarjeta tipo lista */
  .canchas-table-wrapper {
    background: #11132d;
    border-radius: 12px;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
    overflow: hidden;
  }

  .canchas-table-header {
    display: grid;
    grid-template-columns: 110px 1fr 220px 140px;
    gap: 16px;
    background: rgba(255,255,255,0.035);
    padding: 24px 28px;
    color: rgba(255,255,255,0.6);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.78rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }

  .cancha-row {
    display: grid;
    grid-template-columns: 110px 1fr 220px 140px;
    gap: 16px;
    align-items: center;
    background: rgba(4,6,33,0.18);
    border-radius: 0;
    padding: 28px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    transition: background 200ms ease, box-shadow 200ms ease;
  }

  .cancha-row + .cancha-row { margin-top: 0; }
  .cancha-row:last-child { border-bottom: 0; }
  .cancha-row:hover {
    background: rgba(255,255,255,0.035);
    box-shadow: inset 3px 0 0 rgba(236,66,55,0.65);
  }

  .col-codigo { display:flex; align-items:center; justify-content:flex-start; }
  .codigo-badge {
    background: transparent;
    border: 1px solid rgba(236,66,55,0.36);
    color: #ec4237;
    padding: 12px 14px;
    border-radius: 8px;
    font-weight: 800;
    min-width: 64px;
    text-align: center;
  }

  .col-descripcion { display:flex; }
  .cancha-info { display:flex; gap:16px; align-items:center; }
  .cancha-imagen { width:72px; height:72px; border-radius:50%; overflow:hidden; flex:0 0 72px; border: 2px solid rgba(236,66,55,0.22); }
  .cancha-imagen img { width:100%; height:100%; object-fit:cover; display:block; }
  .cancha-details { color: #e6eef8; }
  .cancha-nombre { margin:0; font-size:1.05rem; color:#fff; }
  .cancha-tipo { margin:4px 0 0 0; font-size:0.9rem; color:rgba(255,255,255,0.6); }
  .cancha-ubicacion { margin:6px 0 0 0; font-size:0.85rem; color:rgba(255,255,255,0.45); }

  .col-estado { display:flex; flex-direction:column; gap:6px; align-items:flex-start; }
  .estado-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; font-weight:800; font-size:0.9rem; }
  .estado-conectada { background: rgba(16,185,129,0.1); color: #10B981; border: 1px solid rgba(16,185,129,0.32); }
  .estado-desconectada { background: rgba(236,66,55,0.1); color: #ec4237; border: 1px solid rgba(236,66,55,0.32); }
  .recording-indicator { color: #ec4237; font-weight:600; font-size:0.85rem; display:flex; align-items:center; gap:8px; }

  .col-acciones { display:flex; justify-content:flex-end; }
  .cancha-actions { display:flex; gap:10px; align-items:center; }
  .btn-action { width:46px; height:46px; display:inline-flex; align-items:center; justify-content:center; background: rgba(255,255,255,0.035); border: 1px solid rgba(255,255,255,0.08); color: #fff; padding:10px; border-radius:8px; cursor:pointer; }
  .btn-action i { font-size:1.15rem; }
  .btn-record { background: rgba(236,66,55,0.08); border:1px solid rgba(236,66,55,0.45); color:#ec4237; box-shadow:0 8px 24px rgba(236,66,55,0.18); border-radius:50%; }
  .btn-preview { background: rgba(255,255,255,0.02); }

  .btn-more { background: transparent; }
  .dropdown-menu { position:absolute; right:20px; top:60px; background: #11132d; border-radius:8px; padding:8px 0; box-shadow:0 8px 30px rgba(0,0,0,0.5); }
  .dropdown-menu a { display:block; padding:8px 16px; color: #fff; text-decoration:none; }
  .dropdown-menu a:hover { background: rgba(255,255,255,0.02); }

  /* Responsive */
  @media (max-width: 900px) {
    .canchas-table-header, .cancha-row { grid-template-columns: 90px 1fr 140px 110px; }
    .cancha-imagen { width:56px; height:56px; }
  }

  @media (max-width: 768px) {
    .videos-table-card {
      background: transparent;
      border: 0;
      box-shadow: none;
      padding: 0;
      overflow: visible;
    }

    .canchas-table-wrapper {
      background: transparent;
      border-radius: 0;
      gap: 16px;
      overflow: visible;
    }

    .canchas-table-header {
      display: none;
    }

    .cancha-row {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: 18px;
      padding: 18px;
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 14px;
      background: linear-gradient(180deg, rgba(17,19,45,0.96), rgba(11,13,35,0.96));
      box-shadow: 0 14px 36px rgba(0,0,0,0.22);
    }

    .cancha-row + .cancha-row {
      margin-top: 0;
    }

    .cancha-row:last-child {
      border-bottom: 1px solid rgba(255,255,255,0.09);
    }

    .cancha-row:hover {
      background: linear-gradient(180deg, rgba(17,19,45,0.96), rgba(11,13,35,0.96));
      box-shadow: 0 14px 36px rgba(0,0,0,0.22);
    }

    .col-codigo {
      justify-content: space-between;
      order: 1;
    }

    .col-codigo::after {
      content: 'Cancha';
      color: rgba(255,255,255,0.45);
      font-size: 0.76rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .codigo-badge {
      min-width: 58px;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 0.9rem;
    }

    .col-descripcion {
      order: 2;
      width: 100%;
    }

    .cancha-info {
      width: 100%;
      align-items: flex-start;
      gap: 14px;
    }

    .cancha-imagen {
      width: 74px;
      height: 74px;
      flex-basis: 74px;
      border-radius: 12px;
      background: rgba(255,255,255,0.04);
    }

    .cancha-details {
      min-width: 0;
      flex: 1;
    }

    .cancha-nombre {
      font-size: 1.15rem;
      line-height: 1.18;
      word-break: break-word;
    }

    .cancha-tipo,
    .cancha-ubicacion {
      font-size: 0.88rem;
      line-height: 1.35;
    }

    .col-estado {
      order: 3;
      flex-direction: row;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      padding-top: 14px;
      border-top: 1px solid rgba(255,255,255,0.07);
    }

    .estado-badge,
    .col-estado [id^="status-"] {
      max-width: 100%;
      min-height: 38px;
      padding: 8px 11px;
      border-radius: 10px;
      font-size: 0.82rem;
      line-height: 1.2;
      white-space: normal;
    }

    .recording-indicator {
      font-size: 0.82rem;
    }

    .col-acciones {
      order: 4;
      justify-content: stretch;
      width: 100%;
    }

    .cancha-actions {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      width: 100%;
    }

    .btn-action {
      width: 100%;
      height: 44px;
      border-radius: 10px;
    }

    .btn-record {
      border-radius: 10px;
    }

    .btn-menu-more {
      position: relative;
    }

    .dropdown-menu {
      right: 0;
      top: 50px;
      min-width: 150px;
      z-index: 20;
    }
  }

  @media (max-width: 420px) {
    .cancha-row {
      padding: 16px;
    }

    .cancha-info {
      flex-direction: column;
    }

    .cancha-imagen {
      width: 100%;
      height: auto;
      aspect-ratio: 16 / 9;
      flex-basis: auto;
    }
  }
</style>

<main class="app-content">
  <?php if (isset($errorCanchas)): ?>
  <div style="background: rgba(236,66,55,.1); border: 1px solid rgba(236,66,55,.3); color: #ec4237; padding: 12px 16px; border-radius: 6px; margin: 20px;">
    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i><?= htmlspecialchars($errorCanchas) ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mis Canchas</h1>
      <p class="page-subtitle">Canchas deportivas de tu local</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Canchas</span>
      </div>
    </div>
  </div>

  <?php if (count($locales) > 1): ?>
  <div class="adm-form-card" style="margin-bottom: 20px; padding: 20px;">
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
      <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; color: var(--dk-muted);">Local Activo</label>
        <select class="form-control" style="width: 100%;" onchange="window.location.href='canchas.php?change_local='+this.value">
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>" <?= $localId == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="videos-table-card">
    <?php if (empty($canchas)): ?>
      <div class="empty-state">
        <i class="bi bi-geo-alt"></i>
        <p>No hay canchas registradas en este local.</p>
      </div>
    <?php else: ?>
      <div class="canchas-table-wrapper">
        <div class="canchas-table-header">
          <div class="th-codigo">CÓDIGO</div>
          <div class="th-descripcion">DESCRIPCIÓN</div>
          <div class="th-estado">ESTADO</div>
          <div class="th-acciones">GRABAR</div>
        </div>
        <?php foreach ($canchas as $cancha): 
          $estado = $cancha['camera_status']['conectada'] ?? false;
          $estadoClase = $estado ? 'conectada' : 'desconectada';
          $estadoTexto = $estado ? 'Conectada' : 'Desconectada';
          $estadoColor = $estado ? '#10B981' : '#EF4444';
          $estadoIcon = $estado ? 'check' : 'x';
        ?>
        <div class="cancha-row" data-id="<?= htmlspecialchars($cancha['codigo_cancha']) ?>">
          <div class="col-codigo">
            <div class="codigo-badge"><?= htmlspecialchars($cancha['codigo_cancha']) ?></div>
          </div>
          
          <div class="col-descripcion">
            <div class="cancha-info">
              <div class="cancha-imagen">
                <img src="<?= htmlspecialchars($cancha['imagen_url'] ?? '/public/images/placeholder-cancha.jpg') ?>" alt="<?= htmlspecialchars($cancha['descripcion']) ?>">
              </div>
              <div class="cancha-details">
                <h3 class="cancha-nombre"><?= htmlspecialchars($cancha['descripcion']) ?></h3>
                <p class="cancha-tipo"><?= htmlspecialchars($cancha['tipo_cancha'] ?? 'Tipo no especificado') ?></p>
                <p class="cancha-ubicacion"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($cancha['ubicacion'] ?? 'Ubicación no especificada') ?></p>
              </div>
            </div>
          </div>
          
          <div class="col-estado">
            <span id="status-<?= htmlspecialchars($cancha['codigo_cancha']) ?>" class="estado-badge estado-<?= $estadoClase ?>">
              <i class="bi bi-<?= $estadoIcon ?>-circle-fill"></i>
              <span><?= $estadoTexto ?></span>
            </span>
            <?php if ($cancha['camera_status']['grabando']): ?>
              <span class="recording-indicator">
                <i class="bi bi-record-circle-fill"></i> Grabando
              </span>
            <?php endif; ?>
          </div>
          
          <div class="col-acciones">
            <div class="cancha-actions">
              <button class="btn-action btn-record" id="btn-rec-<?= htmlspecialchars($cancha['codigo_cancha']) ?>" onclick="toggleGrabacion('<?= htmlspecialchars($cancha['codigo_cancha']) ?>', <?= $cancha['camera_status']['grabando'] ? 'true' : 'false' ?>)" title="<?= $cancha['camera_status']['grabando'] ? 'Detener grabación' : 'Iniciar grabación' ?>">
                <i class="bi bi-<?= $cancha['camera_status']['grabando'] ? 'stop-circle' : 'record-circle' ?>"></i>
              </button>
              <button class="btn-action btn-preview" onclick="verPreview('<?= htmlspecialchars($cancha['codigo_cancha']) ?>', '<?= htmlspecialchars($cancha['go2rtc_stream'] ?? '') ?>')" title="Vista previa">
                <i class="bi bi-eye"></i>
              </button>
              <div class="btn-menu-more">
                <button class="btn-action btn-more" onclick="toggleMenu(this)">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <div class="dropdown-menu" style="display:none;">
                  <a href="#" onclick="editarCancha('<?= htmlspecialchars($cancha['codigo_cancha']) ?>')">Editar</a>
                  <a href="#" onclick="eliminarCancha('<?= htmlspecialchars($cancha['codigo_cancha']) ?>')">Eliminar</a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div id="previewModal" class="modal-preview" style="display:none;">
    <div class="modal-preview-content">
      <div class="modal-preview-header">
        <h3 id="previewTitle">Vista Previa en Vivo</h3>
        <button onclick="cerrarPreview()" class="close-btn">&times;</button>
      </div>
      <div class="modal-preview-body">
        <video id="modal-preview-video" controls autoplay muted style="width: 100%; border-radius: 8px; background: #000;"></video>
        <div id="previewLoader" style="text-align:center; padding: 20px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p style="color: #fff; margin-top: 10px;">Conectando con la cámara...</p>
        </div>
        <div id="previewError" style="display:none; text-align:center; padding: 20px; color: #ec4237;">
            <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
            <p style="margin-top: 10px;">No se pudo conectar con la cámara</p>
        </div>
      </div>
    </div>
  </div>
</main>

<style>
.modal-preview {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(5px);
}
.modal-preview-content {
  background: var(--dk-bg-card, #11132d);
  width: 90%;
  max-width: 800px;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}
.modal-preview-header {
  padding: 15px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-preview-header h3 { margin: 0; font-size: 1.2rem; color: #fff; }
.close-btn { background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1; }
.modal-preview-body { padding: 20px; position: relative; min-height: 300px; }

.spinner-border {
  display: inline-block;
  width: 3rem;
  height: 3rem;
  vertical-align: text-bottom;
  border: 0.25em solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
  to { transform: rotate(360deg); }
}

.text-primary { color: #667eea; }
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}
</style>


<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Chequea estado de todas las cámaras al cargar
  document.querySelectorAll('.cancha-row[data-id]').forEach(row => {
    checkStatus(row.dataset.id);
  });

  // Refresca cada 30 segundos
  setInterval(() => {
    document.querySelectorAll('.cancha-row[data-id]').forEach(row => {
      checkStatus(row.dataset.id);
    });
  }, 30000);

  // Cierra menús al hacer click fuera
  document.addEventListener('click', function(e){
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
      if (!menu.contains(e.target) && !menu.previousElementSibling?.contains(e.target)) {
        menu.style.display = 'none';
      }
    });
  });
});

function toggleMenu(btn) {
  const wrapper = btn.closest('.btn-menu-more');
  const menu = wrapper.querySelector('.dropdown-menu');
  // ocultar otros
  document.querySelectorAll('.dropdown-menu').forEach(m => { if(m !== menu) m.style.display = 'none'; });
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

function editarCancha(codigo) {
  // Redirigir a página de edición (implementar según rutas)
  window.location.href = `../admin/canchas/edit.php?codigo=${encodeURIComponent(codigo)}`;
}

function eliminarCancha(codigo) {
  if (!confirm('¿Eliminar cancha ' + codigo + '? Esta acción no se puede deshacer.')) return;
  // Llamada AJAX para eliminar (implementar endpoint)
  fetch(`api/cameras.php?action=delete&codigo=${encodeURIComponent(codigo)}`, { method: 'POST' })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        document.querySelector(`.cancha-row[data-id="${codigo}"]`).remove();
        mostrarToast('Cancha eliminada', 'info');
      } else mostrarToast('Error: ' + (res.error || 'No se pudo eliminar'), 'error');
    }).catch(err => mostrarToast('Error de comunicación', 'error'));
}

function checkStatus(id) {
  fetch(`api/cameras.php?action=status&codigo=${id}`)
    .then(r => r.json())
    .then(data => {
      const badge = document.querySelector(`#status-${id}`);
      if (badge) {
        badge.className = 'badge ' + (data.activa ? 'badge-success' : 'badge-danger');
        badge.style.backgroundColor = data.activa ? 'rgba(46, 213, 115, 0.1)' : 'rgba(236, 66, 55, 0.1)';
        badge.style.color = data.activa ? '#2ed573' : '#ec4237';
        badge.style.border = data.activa ? '1px solid rgba(46, 213, 115, 0.3)' : '1px solid rgba(236, 66, 55, 0.3)';
        badge.innerHTML = `<i class="bi bi-${data.activa ? 'check' : 'x'}-circle-fill" style="margin-right: 4px;"></i>${data.grabando ? 'Grabando' : (data.activa ? 'Conectada' : 'Desconectada')}`;
      }

      // Actualizar botón de grabación
      const btnRec = document.querySelector(`#btn-rec-${id}`);
      if (btnRec) {
        btnRec.onclick = () => toggleGrabacion(id, data.grabando);
        btnRec.title = data.grabando ? 'Detener grabación' : 'Iniciar grabación';
        btnRec.innerHTML = `<i class="bi bi-${data.grabando ? 'stop' : 'record'}-circle" style="color: #ec4237;"></i>`;
      }
    })
    .catch(err => console.error('Error al verificar estado:', err));
}

let webrtcPeer = null;

function verPreview(id, stream) {
    if (!stream) {
        alert('Esta cancha no tiene cámara configurada');
        return;
    }

    const modal  = document.getElementById('previewModal');
    const video  = document.getElementById('modal-preview-video');
    const loader = document.getElementById('previewLoader');
    const error  = document.getElementById('previewError');

    modal.style.display = 'flex';
    loader.style.display = 'block';
    error.style.display  = 'none';
    video.style.display  = 'none';

    const GO2RTC = 'https://cctv.pomplay.com.pe/go2rtc';

    const pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });

    pc.addTransceiver('video', { direction: 'recvonly' });
    pc.addTransceiver('audio', { direction: 'recvonly' });

    pc.ontrack = e => {
        video.srcObject = e.streams[0];
        video.style.display = 'block';
        loader.style.display = 'none';
    };

    pc.createOffer()
        .then(offer => pc.setLocalDescription(offer).then(() => offer))
        .then(offer => fetch(`${GO2RTC}/api/webrtc?src=${stream}`, {
            method: 'POST',
            body: offer.sdp,
            headers: { 'Content-Type': 'application/sdp' }
        }))
        .then(r => {
            if (r.status !== 200 && r.status !== 201) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(sdp => pc.setRemoteDescription({ type: 'answer', sdp: sdp }))
        .catch(err => {
            console.error('WebRTC error:', err);
            loader.style.display = 'none';
            error.style.display  = 'block';
        });

    webrtcPeer = pc;
}

function cerrarPreview() {
    const modal = document.getElementById('previewModal');
    const video = document.getElementById('modal-preview-video');

    video.pause();
    video.srcObject = null;

    if (webrtcPeer) {
        webrtcPeer.close();
        webrtcPeer = null;
    }

    modal.style.display = 'none';
}

function toggleGrabacion(id, grabando) {
  const action = grabando ? 'stop_rec' : 'start_rec';
  const btnRec = document.querySelector(`#btn-rec-${id}`);
  
  // Deshabilitar botón mientras procesa
  if (btnRec) btnRec.disabled = true;

  fetch(`api/cameras.php?action=${action}&codigo=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        checkStatus(id);
        // Toast en vez de alert
        mostrarToast(grabando ? '⏹ Grabación detenida' : '⏺ Grabación iniciada', grabando ? 'info' : 'success');
      } else {
        mostrarToast('Error: ' + (data.error || 'No se pudo realizar la acción'), 'error');
      }
    })
    .catch(err => {
      mostrarToast('Error al comunicarse con la cámara', 'error');
    })
    .finally(() => {
      if (btnRec) btnRec.disabled = false;
    });
}

function mostrarToast(mensaje, tipo = 'success') {
  const colores = {
    success: '#2ed573',
    error:   '#ec4237',
    info:    '#667eea'
  };

  const toast = document.createElement('div');
  toast.textContent = mensaje;
  toast.style.cssText = `
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: ${colores[tipo]};
    color: #fff;
    padding: 14px 24px;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    z-index: 99999;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    animation: fadeInUp 0.3s ease;
  `;

  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function actualizarEstadoCamaras() {
  document.querySelectorAll('tr[data-id]').forEach(row => {
    checkStatus(row.dataset.id);
  });
  alert('Estados actualizados');
}
</script>
