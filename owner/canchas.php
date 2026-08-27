<?php
// ═══════════════════════════════════════════════════════════════
//  canchas.php — Panel de Administración de Canchas con CCTV
// ═══════════════════════════════════════════════════════════════
session_start();
include __DIR__ . '/../conexion.php';

$userId        = $_SESSION['user_id']        ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;
$localId       = $_SESSION['id_local']       ?? null;

if (!$userId || !$propietarioId) {
    header('Location: ../login.php');
    exit;
}

// ── Locales del propietario ──────────────────────────────────────
try {
    $stmtLocales = $pdo->prepare("CALL GetOwnerLocalList(:id_propietario)");
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
} catch (PDOException $e) {
    die('Error al obtener locales: ' . $e->getMessage());
}

if (!$localId && !empty($locales)) {
    $localId = $locales[0]['id_local'];
    $_SESSION['id_local'] = $localId;
}
if (!$localId) die('No tienes locales asignados');

// ── Cambiar local ────────────────────────────────────────────────
if (!empty($_GET['change_local'])) {
    $newId = (int)$_GET['change_local'];
    foreach ($locales as $loc) {
        if ($loc['id_local'] == $newId) {
            $_SESSION['id_local'] = $newId;
            header('Location: canchas.php');
            exit;
        }
    }
}

// ── Canchas del local ────────────────────────────────────────────
try {
    $stmtCanchas = $pdo->prepare("CALL GetCanchasByLocal(:p_id_local)");
    $stmtCanchas->execute([':p_id_local' => $localId]);
    $canchas = $stmtCanchas->fetchAll(PDO::FETCH_ASSOC);
    $stmtCanchas->closeCursor();
} catch (PDOException $e) {
    $canchas    = [];
    $errorCanchas = 'Error al obtener canchas: ' . $e->getMessage();
}

// ── Cámaras por cancha (tabla camara, max 2) ─────────────────────
$camarasPorCancha = [];
try {
    if (!empty($canchas)) {
        $codigos     = array_column($canchas, 'codigo_cancha');
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $stmtCams    = $pdo->prepare("
            SELECT id_camara, codigo_cancha, nombre, go2rtc_stream, posicion, activa, grabando
            FROM camara
            WHERE codigo_cancha IN ($placeholders)
            ORDER BY codigo_cancha, posicion
        ");
        $stmtCams->execute($codigos);
        foreach ($stmtCams->fetchAll(PDO::FETCH_ASSOC) as $cam) {
            $camarasPorCancha[$cam['codigo_cancha']][] = $cam;
        }
    }
} catch (PDOException) {
    // Tabla camara puede no existir → fallback a cancha.go2rtc_stream
}
?>
<?php include __DIR__ . '/../admin/shared/header_admin.php'; ?>
<?php $placeholderCancha = $baseUrl . '/public/images/pomplay logo.png'; ?>
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/tables.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-canchas.css">



<div id="toast-container"></div>

<main class="app-content">
  <?php if (isset($errorCanchas)): ?>
  <div style="background:rgba(236,66,55,.1);border:1px solid rgba(236,66,55,.3);color:#ec4237;padding:12px 18px;border-radius:8px;margin:20px;">
    <i class="bi bi-exclamation-circle" style="margin-right:8px;"></i><?= htmlspecialchars($errorCanchas) ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mis Canchas</h1>
      <p class="page-subtitle">Gestión de canchas y cámaras en tiempo real</p>
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
  <div class="adm-form-card" style="margin-bottom:20px;padding:18px 22px;">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <label style="font-size:0.85rem;color:var(--dk-muted);white-space:nowrap;font-weight:600;">
        <i class="bi bi-shop" style="margin-right:6px;"></i>Local activo
      </label>
      <select class="form-control" style="max-width:280px;" onchange="window.location.href='canchas.php?change_local='+this.value">
        <?php foreach ($locales as $loc): ?>
          <option value="<?= $loc['id_local'] ?>" <?= $localId == $loc['id_local'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($loc['nombre_local']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <?php endif; ?>

  <div class="videos-table-card">
    <?php if (empty($canchas)): ?>
      <div class="empty-state">
        <i class="bi bi-geo-alt" style="font-size:3rem;color:rgba(255,255,255,0.2);"></i>
        <p style="margin-top:12px;color:rgba(255,255,255,0.4);">No hay canchas registradas en este local.</p>
      </div>
    <?php else: ?>
      <div class="canchas-table-wrapper">
        <div class="canchas-table-header">
          <div>CÓDIGO</div>
          <div>DESCRIPCIÓN</div>
          <div>ESTADO CÁMARA</div>
          <div style="text-align:right;">ACCIONES</div>
        </div>

        <?php foreach ($canchas as $cancha):
          $cod  = $cancha['codigo_cancha'];
          $cams = $camarasPorCancha[$cod] ?? [];
          // Fallback: si no hay cámaras en tabla camara pero la cancha tiene go2rtc_stream
          if (empty($cams) && !empty($cancha['go2rtc_stream'])) {
              $cams = [[
                  'id_camara'    => 0,
                  'nombre'       => 'Cámara Principal',
                  'go2rtc_stream'=> $cancha['go2rtc_stream'],
                  'posicion'     => 1,
                  'activa'       => 1,
                  'grabando'     => 0,
              ]];
          }
          $hasCams    = !empty($cams);
          $numCams    = count($cams);
          $camStreams  = json_encode(array_values($cams), JSON_UNESCAPED_UNICODE);
        ?>
        <div class="cancha-row" data-id="<?= htmlspecialchars($cod) ?>" data-cams='<?= htmlspecialchars($camStreams) ?>'>
          <!-- Código -->
          <div class="col-codigo">
            <div class="codigo-badge"><?= htmlspecialchars($cod) ?></div>
          </div>

          <!-- Descripción -->
          <div class="col-descripcion">
            <div class="cancha-info">
              <?php
                $imgSrc = trim((string)($cancha['imagen_url'] ?? ''));
                $usaLogo = ($imgSrc === '');
                if ($usaLogo) {
                    $imgSrc = $placeholderCancha;
                }
              ?>
              <div class="cancha-imagen<?= $usaLogo ? ' cancha-imagen--logo' : '' ?>">
                <img src="<?= htmlspecialchars($imgSrc) ?>"
                     alt="<?= htmlspecialchars($cancha['descripcion']) ?>"
                     onerror="this.onerror=null;this.src='<?= htmlspecialchars($placeholderCancha, ENT_QUOTES) ?>';this.closest('.cancha-imagen')?.classList.add('cancha-imagen--logo');">
              </div>
              <div class="cancha-details">
                <h3 class="cancha-nombre"><?= htmlspecialchars($cancha['descripcion']) ?></h3>
                <p class="cancha-tipo"><?= htmlspecialchars($cancha['tipo_cancha'] ?? 'Tipo no especificado') ?></p>
                <p class="cancha-ubicacion">
                  <i class="bi bi-geo-alt-fill"></i>
                  <?= htmlspecialchars($cancha['ubicacion'] ?? 'Ubicación no especificada') ?>
                </p>
                <?php if ($numCams > 0): ?>
                <div class="cam-badges">
                  <?php foreach ($cams as $c): ?>
                    <span class="cam-badge">
                      <i class="bi bi-camera-video"></i>
                      <?= htmlspecialchars($c['nombre'] ?? 'Cam ' . $c['posicion']) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Estado -->
          <div class="col-estado">
            <span id="status-<?= htmlspecialchars($cod) ?>" class="estado-badge estado-cargando">
              <span class="spin" style="width:10px;height:10px;border-width:2px;display:inline-block;border-radius:50%;border:2px solid rgba(255,255,255,0.15);border-top-color:rgba(255,255,255,0.5);animation:spin .8s linear infinite;"></span>
              <span>Verificando…</span>
            </span>
            <span id="rec-indicator-<?= htmlspecialchars($cod) ?>" class="recording-badge" style="display:none;">
              <span class="rec-dot"></span> Grabando
            </span>
          </div>

          <!-- Acciones -->
          <div class="col-acciones">
            <div class="cancha-actions">
              <!-- Grabar (principal) -->
              <button
                class="btn-cam-action btn-record-main"
                id="btn-rec-<?= htmlspecialchars($cod) ?>"
                data-codigo="<?= htmlspecialchars($cod) ?>"
                data-grabando="false"
                onclick="toggleGrabacion('<?= htmlspecialchars($cod) ?>')"
                title="Iniciar grabación"
                <?= !$hasCams ? 'disabled' : '' ?>
              >
                <i class="bi bi-record-circle"></i>
              </button>

              <!-- Preview -->
              <button
                class="btn-cam-action btn-preview"
                onclick="abrirPreview('<?= htmlspecialchars($cod) ?>')"
                title="Vista previa en vivo"
                <?= !$hasCams ? 'disabled' : '' ?>
              >
                <i class="bi bi-eye"></i>
              </button>

              <!-- Más opciones -->
              <div class="btn-menu-more" style="position:relative;">
                <button class="btn-cam-action btn-more" onclick="toggleMenu(this)" title="Más opciones">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <div class="dropdown-menu" style="display:none;position:absolute;right:0;top:48px;background:#11132d;border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:6px 0;min-width:160px;z-index:50;box-shadow:0 8px 32px rgba(0,0,0,0.5);">
                  <a href="videos.php?cancha=<?= urlencode($cod) ?>" style="display:flex;align-items:center;gap:9px;padding:9px 14px;color:rgba(255,255,255,0.8);font-size:0.88rem;transition:background 150ms;">
                    <i class="bi bi-play-circle" style="color:#667eea;"></i> Ver grabaciones
                  </a>
                  <?php if (!$hasCams): ?>
                  <a href="#" onclick="alert('Configure las cámaras desde el panel de administración.');return false;" style="display:flex;align-items:center;gap:9px;padding:9px 14px;color:rgba(255,255,255,0.8);font-size:0.88rem;">
                    <i class="bi bi-camera-video-off" style="color:#ec4237;"></i> Sin cámaras
                  </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- ═══ MODAL PREVIEW CCTV ════════════════════════════════════════════════════ -->
<div id="cctvModal" class="cctv-modal-overlay" style="display:none;" onclick="if(event.target===this)cerrarPreview()">
  <div class="cctv-modal">
    <div class="cctv-modal-header">
      <div class="cctv-modal-title">
        <span class="cctv-modal-live-dot"></span>
        <span id="modal-title-text">Vista Previa en Vivo</span>
        <span id="modal-cam-count" style="font-size:0.75rem;font-weight:400;color:rgba(255,255,255,0.4);"></span>
      </div>
      <button class="cctv-modal-close" onclick="cerrarPreview()" title="Cerrar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="cctv-modal-body">
      <!-- Grid de streams (single o dual) -->
      <div id="streams-grid" class="streams-grid single">
        <!-- Cam 1 -->
        <div class="stream-container" id="stream-slot-1">
          <span class="stream-label" id="stream-label-1">Cámara 1</span>
          <div class="stream-loader" id="stream-loader-1">
            <div class="spin"></div>
            <span>Conectando…</span>
          </div>
          <div class="stream-error" id="stream-error-1">
            <i class="bi bi-exclamation-triangle"></i>
            <span>No se pudo conectar con la cámara</span>
            <button onclick="reconectarCam(1)" style="margin-top:8px;padding:6px 14px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;border-radius:6px;cursor:pointer;font-size:0.8rem;">Reintentar</button>
          </div>
          <video id="stream-video-1" class="stream-video" autoplay muted playsinline></video>
        </div>

        <!-- Cam 2 (se muestra solo si hay 2 cámaras) -->
        <div class="stream-container" id="stream-slot-2" style="display:none;">
          <span class="stream-label" id="stream-label-2">Cámara 2</span>
          <div class="stream-loader" id="stream-loader-2">
            <div class="spin"></div>
            <span>Conectando…</span>
          </div>
          <div class="stream-error" id="stream-error-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>No se pudo conectar con la cámara</span>
            <button onclick="reconectarCam(2)" style="margin-top:8px;padding:6px 14px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;border-radius:6px;cursor:pointer;font-size:0.8rem;">Reintentar</button>
          </div>
          <video id="stream-video-2" class="stream-video" autoplay muted playsinline></video>
        </div>
      </div>

      <!-- Controles del modal -->
      <div class="cctv-modal-controls">
        <div class="modal-cam-info" id="modal-go2rtc-info">
          <i class="bi bi-broadcast" style="color:#ec4237;margin-right:4px;"></i>
          <span id="modal-stream-name">—</span>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;" id="modal-rec-buttons"></div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>

<script>
/* ═══════════════════════════════════════════════════════════════
   CCTV Panel — JavaScript
═══════════════════════════════════════════════════════════════ */

// Estado global de peers WebRTC (indexed por slot 1/2)
const peers   = { 1: null, 2: null };
let modalCodigo = null;
let modalCams   = [];  // [{go2rtc_stream, nombre, posicion, grabando, ...}]

const GO2RTC = 'https://cctv.pomplay.com.pe/go2rtc';

// Evita solicitudes duplicadas mientras una verificación está en curso
const statusInFlight = new Set();

// ── Status polling ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.cancha-row[data-id]').forEach(row => {
    checkStatus(row.dataset.id);
  });
  setInterval(() => {
    document.querySelectorAll('.cancha-row[data-id]').forEach(row => {
      checkStatus(row.dataset.id);
    });
  }, 30000);

  // Cerrar dropdowns al hacer click fuera
  document.addEventListener('click', e => {
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
      const btn = menu.previousElementSibling;
      if (!menu.contains(e.target) && !btn?.contains(e.target)) {
        menu.style.display = 'none';
      }
    });
  });

  // Cerrar modal con Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarPreview();
  });
});

function toggleMenu(btn) {
  const wrapper = btn.closest('.btn-menu-more');
  const menu    = wrapper.querySelector('.dropdown-menu');
  document.querySelectorAll('.dropdown-menu').forEach(m => { if (m !== menu) m.style.display = 'none'; });
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

// ── Verificar estado de cámara ────────────────────────────────
function checkStatus(cod) {
  if (!cod || statusInFlight.has(cod)) return;
  statusInFlight.add(cod);

  fetch(`api/cameras.php?action=status&codigo=${encodeURIComponent(cod)}`)
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      const badge    = document.querySelector(`#status-${cod}`);
      const recInd   = document.querySelector(`#rec-indicator-${cod}`);
      const btnRec   = document.querySelector(`#btn-rec-${cod}`);

      if (!badge) return;

      const activa   = data.activa ?? data.ok ?? false;
      // grabando_en_bd es la fuente más confiable: si la BD sabe que está grabando,
      // priorizar ese dato sobre el VPS (que puede fallar por red/capacidad)
      const grabando = data.grabando || data.grabando_en_bd || false;

      // Badge de estado
      badge.className = 'estado-badge ' + (activa ? 'estado-conectada' : 'estado-desconectada');
      badge.innerHTML = `
        <i class="bi bi-${activa ? 'check' : 'x'}-circle-fill"></i>
        <span>${activa ? 'Conectada' : 'Desconectada'}</span>
      `;

      // Indicador de grabación
      if (recInd) recInd.style.display = grabando ? 'inline-flex' : 'none';

      // Botón grabar: solo se deshabilita si la cámara está DESCONECTADA
      if (btnRec) {
        btnRec.dataset.grabando = grabando ? 'true' : 'false';
        btnRec.classList.toggle('recording', grabando);
        btnRec.title    = grabando ? 'Detener grabación' : 'Iniciar grabación';
        btnRec.innerHTML = `<i class="bi bi-${grabando ? 'stop-circle-fill' : 'record-circle'}"></i>`;
        btnRec.disabled = !activa; // solo deshabilitado si desconectada
      }
    })
    .catch(() => {
      const badge = document.querySelector(`#status-${cod}`);
      if (badge) {
        badge.className = 'estado-badge estado-desconectada';
        badge.innerHTML = '<i class="bi bi-wifi-off"></i><span>Sin respuesta</span>';
      }
      // NO modificar el botón de grabación si hay error de red:
      // evita que el usuario vea "no grabando" por un fallo temporal de conexión
    })
    .finally(() => {
      statusInFlight.delete(cod);
    });
}

// ── Toggle grabación ──────────────────────────────────────────
function toggleGrabacion(cod) {
  const btnRec   = document.querySelector(`#btn-rec-${cod}`);
  const grabando = btnRec?.dataset.grabando === 'true';
  const action   = grabando ? 'stop_rec' : 'start_rec';

  if (!btnRec) return;

  // Guardar estado anterior para restaurar si falla
  const htmlAnterior = btnRec.innerHTML;

  // Mostrar spinner en el botón SIN deshabilitarlo (para que siga siendo interactuable si falla)
  // Temporalmente bloqueamos solo el tiempo del request para evitar doble click
  btnRec.disabled = true;
  btnRec.innerHTML = '<div class="spin" style="width:14px;height:14px;border-width:2px;display:inline-block;border-radius:50%;border:2px solid rgba(255,255,255,0.15);border-top-color:#ec4237;animation:spin .8s linear infinite;"></div>';

  // Safety timeout: si el VPS no responde en 12s, restaurar botón
  const safetyTimer = setTimeout(() => {
    btnRec.disabled = false;
    btnRec.innerHTML = htmlAnterior;
    mostrarToast('Tiempo de espera agotado. Verifica la conexión con la cámara.', 'error');
  }, 12000);

  fetch(`api/cameras.php?action=${action}&codigo=${encodeURIComponent(cod)}`)
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      clearTimeout(safetyTimer);
      if (data.ok) {
        // Actualizar el botón inmediatamente al nuevo estado sin esperar el polling
        const nuevoGrabando = !grabando;
        btnRec.dataset.grabando = nuevoGrabando ? 'true' : 'false';
        btnRec.classList.toggle('recording', nuevoGrabando);
        btnRec.title    = nuevoGrabando ? 'Detener grabación' : 'Iniciar grabación';
        btnRec.innerHTML = `<i class="bi bi-${nuevoGrabando ? 'stop-circle-fill' : 'record-circle'}"></i>`;
        btnRec.disabled = false; // re-habilitar: la cámara está conectada

        mostrarToast(
          grabando ? '⏹ Grabación detenida' : '⏺ Grabación iniciada',
          grabando ? 'info' : 'success'
        );
        // Polling para sincronizar otros indicadores (badge, rec-indicator)
        let intentos = 0;
        const poll = setInterval(() => {
          intentos++;
          checkStatus(cod);
          if (intentos >= 3) clearInterval(poll);
        }, 1500);
      } else if (data.already_recording) {
        // Caso especial: el servidor sabe que ya está grabando aunque el botón no lo reflejaba
        // Actualizar el botón a estado "grabando" en lugar de mostrar error
        btnRec.dataset.grabando = 'true';
        btnRec.classList.add('recording');
        btnRec.title    = 'Detener grabación';
        btnRec.innerHTML = '<i class="bi bi-stop-circle-fill"></i>';
        btnRec.disabled = false;
        mostrarToast('⚠️ Ya hay una grabación en curso. El botón fue actualizado.', 'warning');
        checkStatus(cod);
      } else {
        mostrarToast('Error: ' + (data.error || 'No se pudo realizar la acción'), 'error');
        btnRec.disabled = false;
        btnRec.innerHTML = htmlAnterior;
        checkStatus(cod);
      }
    })
    .catch(err => {
      clearTimeout(safetyTimer);
      mostrarToast('Error de comunicación con la cámara', 'error');
      btnRec.disabled = false;
      btnRec.innerHTML = htmlAnterior;
      checkStatus(cod);
    });
}

// ── Modal de Preview ──────────────────────────────────────────
function abrirPreview(cod) {
  const row  = document.querySelector(`.cancha-row[data-id="${cod}"]`);
  if (!row) return;

  const camsData = JSON.parse(row.dataset.cams || '[]');
  if (!camsData.length) {
    mostrarToast('Esta cancha no tiene cámaras configuradas', 'warning');
    return;
  }

  modalCodigo = cod;
  modalCams   = camsData;

  // Configurar título
  document.getElementById('modal-title-text').textContent  = row.querySelector('.cancha-nombre')?.textContent || cod;
  document.getElementById('modal-cam-count').textContent   = camsData.length > 1 ? ` · ${camsData.length} cámaras` : '';
  document.getElementById('modal-stream-name').textContent = camsData.map(c => c.go2rtc_stream).join(', ');

  // Layout
  const grid = document.getElementById('streams-grid');
  const dual = camsData.length >= 2;
  grid.className = 'streams-grid ' + (dual ? 'dual' : 'single');

  const slot2 = document.getElementById('stream-slot-2');
  slot2.style.display = dual ? '' : 'none';

  // Labels
  camsData.forEach((cam, i) => {
    const slot = i + 1;
    const lbl  = document.getElementById(`stream-label-${slot}`);
    if (lbl) lbl.textContent = cam.nombre || `Cámara ${slot}`;
  });

  // Mostrar modal (con botón en estado de carga mientras verificamos)
  document.getElementById('cctvModal').style.display = 'flex';

  // Conectar streams
  camsData.forEach((cam, i) => {
    conectarStream(i + 1, cam.go2rtc_stream);
  });

  // Renderizar botón con estado provisional y luego actualizar con dato real del API
  renderModalRecButtons();
  actualizarEstadoModal(cod);
}

// Consulta el estado real al VPS y actualiza el botón del modal
function actualizarEstadoModal(cod) {
  if (!cod) return;
  const btn = document.getElementById('modal-btn-rec');
  if (!btn) return;

  fetch(`api/cameras.php?action=status&codigo=${encodeURIComponent(cod)}`)
    .then(r => r.json())
    .then(data => {
      // Sincronizar grabando en modalCams con la respuesta real del VPS
      if (Array.isArray(data.cameras)) {
        data.cameras.forEach(camStatus => {
          const cam = modalCams.find(c => (c.posicion ?? 1) == (camStatus._cam_pos ?? 1));
          if (cam) cam.grabando = (camStatus.grabando) ? 1 : 0;
        });
      }
      // Re-renderizar botón con estado actualizado
      renderModalRecButtons();
    })
    .catch(() => {
      // En caso de error de red, dejamos el botón con estado provisional
    });
}

function renderModalRecButtons() {
  const container = document.getElementById('modal-rec-buttons');
  container.innerHTML = '';

  // Un único botón: graba/detiene TODAS las cámaras de la cancha
  // El estado es true si AL MENOS UNA cámara está grabando
  const grabando = modalCams.some(c => c.grabando == 1);

  const btn = document.createElement('button');
  btn.className = 'btn-modal-rec ' + (grabando ? 'stop' : 'start');
  btn.id = 'modal-btn-rec';
  btn.dataset.grabando = grabando ? 'true' : 'false';
  btn.innerHTML = grabando
    ? `<i class="bi bi-stop-circle-fill"></i> Detener grabación`
    : `<i class="bi bi-record-circle-fill"></i> Iniciar grabación`;
  btn.onclick = () => toggleGrabacionModal(modalCodigo, btn);
  container.appendChild(btn);
}

function toggleGrabacionModal(cod, btn) {
  const grabando = btn.dataset.grabando === 'true';
  const action   = grabando ? 'stop_rec' : 'start_rec';

  const htmlAnterior = btn.innerHTML;
  btn.disabled  = true;
  btn.innerHTML = '<div class="spin" style="width:14px;height:14px;border-width:2px;display:inline-block;border-radius:50%;border:2px solid rgba(255,255,255,0.15);border-top-color:#ec4237;animation:spin .8s linear infinite;"></div> Procesando…';

  fetch(`api/cameras.php?action=${action}&codigo=${encodeURIComponent(cod)}`)
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        const nuevoGrabando = !grabando;
        // Actualizar estado en modalCams para todas las cámaras
        modalCams.forEach(c => { c.grabando = nuevoGrabando ? 1 : 0; });
        renderModalRecButtons();
        mostrarToast(
          nuevoGrabando ? '⏺ Grabación iniciada' : '⏹ Grabación detenida',
          nuevoGrabando ? 'success' : 'info'
        );
        // Refrescar indicador de la tabla
        setTimeout(() => checkStatus(cod), 1200);
      } else {
        mostrarToast('Error: ' + (data.error || data.message || 'Sin respuesta del servidor'), 'error');
        btn.disabled  = false;
        btn.innerHTML = htmlAnterior;
      }
    })
    .catch(() => {
      mostrarToast('Error de comunicación', 'error');
      btn.disabled  = false;
      btn.innerHTML = htmlAnterior;
    });
}

// ── WebRTC ────────────────────────────────────────────────────
function conectarStream(slot, streamName) {
  // Resetear UI
  document.getElementById(`stream-loader-${slot}`).style.display = 'flex';
  document.getElementById(`stream-error-${slot}`).style.display  = 'none';
  document.getElementById(`stream-video-${slot}`).style.display  = 'none';

  // Cerrar peer anterior
  if (peers[slot]) {
    peers[slot].close();
    peers[slot] = null;
  }

  if (!streamName) {
    document.getElementById(`stream-loader-${slot}`).style.display = 'none';
    document.getElementById(`stream-error-${slot}`).style.display  = 'flex';
    return;
  }

  const pc = new RTCPeerConnection({
    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
  });
  peers[slot] = pc;

  pc.addTransceiver('video', { direction: 'recvonly' });
  pc.addTransceiver('audio', { direction: 'recvonly' });

  const video = document.getElementById(`stream-video-${slot}`);

  pc.ontrack = e => {
    video.srcObject = e.streams[0];
    video.style.display = 'block';
    document.getElementById(`stream-loader-${slot}`).style.display = 'none';
  };

  pc.oniceconnectionstatechange = () => {
    if (['disconnected', 'failed', 'closed'].includes(pc.iceConnectionState)) {
      mostrarStreamError(slot);
    }
  };

  pc.createOffer()
    .then(offer => pc.setLocalDescription(offer).then(() => offer))
    .then(offer => fetch(`${GO2RTC}/api/webrtc?src=${encodeURIComponent(streamName)}`, {
      method:  'POST',
      body:    offer.sdp,
      headers: { 'Content-Type': 'application/sdp' }
    }))
    .then(r => {
      if (r.status !== 200 && r.status !== 201) throw new Error('HTTP ' + r.status);
      return r.text();
    })
    .then(sdp => pc.setRemoteDescription({ type: 'answer', sdp }))
    .catch(err => {
      console.warn(`WebRTC slot ${slot} error:`, err);
      mostrarStreamError(slot);
    });
}

function mostrarStreamError(slot) {
  document.getElementById(`stream-loader-${slot}`).style.display = 'none';
  document.getElementById(`stream-error-${slot}`).style.display  = 'flex';
  document.getElementById(`stream-video-${slot}`).style.display  = 'none';
}

function reconectarCam(slot) {
  const cam = modalCams[slot - 1];
  if (cam) conectarStream(slot, cam.go2rtc_stream);
}

function cerrarPreview() {
  // Detener streams
  [1, 2].forEach(slot => {
    const v = document.getElementById(`stream-video-${slot}`);
    if (v) { v.pause(); v.srcObject = null; }
    if (peers[slot]) { peers[slot].close(); peers[slot] = null; }
  });

  document.getElementById('cctvModal').style.display = 'none';
  modalCodigo = null;
  modalCams   = [];
}

// ── Toast notifications ───────────────────────────────────────
function mostrarToast(mensaje, tipo = 'success') {
  const iconMap = {
    success: 'bi-check-circle-fill',
    error:   'bi-x-circle-fill',
    info:    'bi-info-circle-fill',
    warning: 'bi-exclamation-triangle-fill'
  };
  const container = document.getElementById('toast-container');
  const toast     = document.createElement('div');
  toast.className = `toast-item toast-${tipo}`;
  toast.innerHTML = `<i class="bi ${iconMap[tipo] || iconMap.info} toast-icon-${tipo}" style="font-size:1.1rem;flex-shrink:0;"></i><span>${mensaje}</span>`;
  container.appendChild(toast);


  setTimeout(() => {
    toast.style.transition = 'opacity 300ms, transform 300ms';
    toast.style.opacity    = '0';
    toast.style.transform  = 'translateX(30px)';
    setTimeout(() => toast.remove(), 320);
  }, 3500);
}

// ── Heartbeat de sesión ─────────────────────────────────────────────────────
// Mantiene la sesión PHP activa mientras haya cámaras grabando.
// Sin esto, la sesión expira (~24 min por defecto en XAMPP) y el próximo
// llamado a stop_rec falla con "Sesión inválida".
(function initSessionHeartbeat() {
  const INTERVAL_MS = 4 * 60 * 1000; // ping cada 4 minutos
  let heartbeatTimer = null;

  function hayGrabacionActiva() {
    // Revisar todos los botones de grabación en la página
    return Array.from(document.querySelectorAll('[data-grabando="true"]')).length > 0;
  }

  function pingSession() {
    if (!hayGrabacionActiva()) return; // no desperdiciar recursos

    fetch('api/cameras.php?action=keepalive')
      .then(r => r.json())
      .then(data => {
        if (!data.ok) {
          // La sesión expiró incluso con el ping — avisar al usuario
          mostrarToast('⚠ Tu sesión ha expirado. Guarda la grabación y vuelve a iniciar sesión.', 'warning');
          clearInterval(heartbeatTimer);
        }
      })
      .catch(() => {
        // Sin conexión — no hacer nada, el usuario verá el error al detener
      });
  }

  // Iniciar el intervalo al cargar la página
  heartbeatTimer = setInterval(pingSession, INTERVAL_MS);

  // También renovar inmediatamente si el usuario vuelve a la pestaña tras inactividad
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && hayGrabacionActiva()) {
      pingSession();
    }
  });
})();
</script>
