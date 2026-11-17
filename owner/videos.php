<?php
// ═══════════════════════════════════════════════════════════════
//  videos.php — Panel de Grabaciones del Propietario
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

$_SESSION['video_password_csrf'] ??= bin2hex(random_bytes(32));

// ── Cambiar local ────────────────────────────────────────────────
if (!empty($_GET['change_local'])) {
    $newId = (int)$_GET['change_local'];
    foreach ($locales as $loc) {
        if ($loc['id_local'] == $newId) {
            $_SESSION['id_local'] = $newId;
            header('Location: videos.php');
            exit;
        }
    }
}

// ── Filtros ──────────────────────────────────────────────────────
$filtroFecha  = !empty($_GET['fecha'])  ? $_GET['fecha']  : null;
$filtroCancha = !empty($_GET['cancha']) ? $_GET['cancha'] : null;
$paginaActual = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina    = 20;
$offset       = ($paginaActual - 1) * $porPagina;

// ── Canchas del local (para filtro) ─────────────────────────────
try {
    $stmtCanchas = $pdo->prepare("CALL GetCanchasByLocal(:id_local)");
    $stmtCanchas->execute([':id_local' => $localId]);
    $canchas = $stmtCanchas->fetchAll(PDO::FETCH_ASSOC);
    $stmtCanchas->closeCursor();
} catch (PDOException) {
    $canchas = [];
}

$protectedVideoCodes = [];
$stmtProtectedVideos = $pdo->prepare(
    "SELECT codigo_video FROM video
     WHERE id_local = :id_local AND password_hash IS NOT NULL AND password_hash <> ''"
);
$stmtProtectedVideos->execute([':id_local' => $localId]);
$protectedVideoCodes = array_fill_keys($stmtProtectedVideos->fetchAll(PDO::FETCH_COLUMN), true);
$stmtProtectedVideos->closeCursor();

// ── Videos con paginación ────────────────────────────────────────
try {
    $stmtV = $pdo->prepare("CALL GetOwnerVideosFiltered(:id_local, :fecha, :codigo_cancha)");
    $stmtV->execute([
        ':id_local'      => $localId,
        ':fecha'         => $filtroFecha,
        ':codigo_cancha' => $filtroCancha,
    ]);
    $todosVideos  = $stmtV->fetchAll(PDO::FETCH_ASSOC);
    $stmtV->closeCursor();

    $todosVideos = array_values(array_filter(
        $todosVideos,
        static fn(array $v): bool => (int)($v['estado'] ?? 0) === 1
    ));

    $totalVideos  = count($todosVideos);
    $totalPaginas = $totalVideos > 0 ? (int)ceil($totalVideos / $porPagina) : 1;
    $videos       = array_slice($todosVideos, $offset, $porPagina);
} catch (PDOException) {
    $videos = $todosVideos = [];
    $totalVideos  = 0;
    $totalPaginas = 1;
}

// ── Helper: formatear duración en segundos ───────────────────────
function formatDuracion(int $seg): string {
    if ($seg <= 0) return '—';
    $h = intdiv($seg, 3600);
    $m = intdiv($seg % 3600, 60);
    $s = $seg % 60;
    if ($h > 0) return sprintf('%dh %02dm %02ds', $h, $m, $s);
    if ($m > 0) return sprintf('%dm %02ds', $m, $s);
    return sprintf('%ds', $s);
}

$meses = [
    'January' => 'enero', 'February' => 'febrero', 'March'     => 'marzo',
    'April'   => 'abril', 'May'      => 'mayo',    'June'      => 'junio',
    'July'    => 'julio', 'August'   => 'agosto',  'September' => 'septiembre',
    'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
];
?>
<?php include __DIR__ . '/../admin/shared/header_admin.php'; ?>
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/modals.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/tables.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-videos.css">




<main class="app-content">

  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mis Grabaciones</h1>
      <p class="page-subtitle">Historial de videos grabados por tus cámaras</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item"><a href="canchas.php" style="color:inherit;">Canchas</a></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Videos</span>
      </div>
    </div>
  </div>

  <!-- Selector de local (reutiliza .adm-form-card + .local-selector-* de owner-videos.css) -->
  <?php if (count($locales) > 1): ?>
  <div class="adm-form-card local-selector-card">
    <div class="local-selector-wrapper">
      <div class="local-selector-field">
        <label class="local-selector-label">
          <i class="bi bi-shop" style="margin-right:5px;"></i>Local activo
        </label>
        <select class="form-control local-selector-select"
                onchange="window.location.href='videos.php?change_local='+this.value">
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>"
              <?= $localId == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filtros (reutiliza .adm-form-card + .filters-card + clases de owner-videos.css) -->
  <div class="adm-form-card filters-card">
    <form method="GET" class="filters-form">
      <div class="filter-field">
        <label class="filter-label">Fecha</label>
        <input type="date" name="fecha" class="form-control filter-input"
               value="<?= htmlspecialchars($filtroFecha ?? '') ?>" />
      </div>
      <div class="filter-field">
        <label class="filter-label">Cancha</label>
        <select name="cancha" class="form-control filter-select">
          <option value="">Todas las canchas</option>
          <?php foreach ($canchas as $c): ?>
            <option value="<?= htmlspecialchars($c['codigo_cancha']) ?>"
              <?= $filtroCancha === $c['codigo_cancha'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['descripcion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-filter">
          <i class="bi bi-funnel-fill"></i> Filtrar
        </button>
        <a href="videos.php" class="btn-filter-clear">
          <i class="bi bi-x-lg"></i> Limpiar
        </a>
      </div>
    </form>
  </div>

  <!-- Stats -->
  <div class="stats-bar">
    <p class="stats-text">
      <strong><?= $totalVideos ?></strong> grabaciones encontradas
      <?php if ($filtroFecha || $filtroCancha): ?>
        &nbsp;·&nbsp;
        <?php if ($filtroFecha): ?>
          Fecha: <strong><?= htmlspecialchars(date('d/m/Y', strtotime($filtroFecha))) ?></strong>
        <?php endif; ?>
        <?php if ($filtroCancha): ?>
          &nbsp;Cancha: <strong><?= htmlspecialchars($filtroCancha) ?></strong>
        <?php endif; ?>
      <?php endif; ?>
    </p>
    <a href="canchas.php" class="btn-filter-clear" style="font-size:0.82rem;padding:7px 13px;">
      <i class="bi bi-camera-video"></i> Ver cámaras en vivo
    </a>
  </div>

  <!-- Tabla (reutiliza .adm-table + .table-actions + .table-btn-icon + .td-id + .badge-active de admin-modern-v2.css) -->
  <div class="videos-table-wrap">
    <?php if (empty($videos)): ?>
      <!-- Reutiliza .videos-empty-state, .videos-empty-icon, .videos-empty-text de owner-videos.css -->
      <div class="videos-empty-state">
        <div class="videos-empty-icon"><i class="bi bi-play-circle"></i></div>
        <p class="videos-empty-text">
          No se encontraron grabaciones<?= ($filtroFecha || $filtroCancha) ? ' con los filtros aplicados' : '' ?>.
        </p>
        <?php if ($filtroFecha || $filtroCancha): ?>
          <a href="videos.php" class="btn-filter-clear" style="margin-top:12px;">
            <i class="bi bi-x-lg"></i> Limpiar filtros
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- .adm-table de admin-modern-v2.css -->
      <table class="adm-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Cancha</th>
            <th>Fecha</th>
            <th class="th-duracion">Duración</th>
            <th style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($videos as $video):
            $fd  = str_replace(array_keys($meses), array_values($meses), date('d M Y', strtotime($video['fecha_partido'])));
            $dur = (int)($video['duracion'] ?? 0);
            $esDescargable = (bool)($video['es_descargable'] ?? false);
            $videoCode = (string) $video['codigo_video'];
            $videoUrl = $baseUrl . '/public/video-proxy.php?codigo_video=' . rawurlencode($videoCode);
            $isVideoProtected = isset($protectedVideoCodes[$videoCode]);
            $descr    = $video['descripcion'] ?? '—';
            $codCancha = $video['cancha_nombre'] ?? $video['codigo_cancha'] ?? '—';
            $hora = substr($video['hora_partido'] ?? '', 0, 5);
          ?>
          <tr>
            <!-- .td-id de admin-modern-v2.css -->
            <td><span class="td-id"><?= htmlspecialchars($video['codigo_video']) ?></span></td>

            <td>
              <div class="td-desc" title="<?= htmlspecialchars($descr) ?>">
                <?= htmlspecialchars($descr) ?>
              </div>
              <?php if ($hora): ?>
                <div style="font-size:0.75rem;color:var(--color-text-muted);margin-top:3px;">
                  <i class="bi bi-clock" style="font-size:0.7rem;"></i> <?= $hora ?>
                </div>
              <?php endif; ?>
            </td>

            <!-- .badge-active de admin-modern-v2.css -->
            <td><span class="badge-active"><?= htmlspecialchars($codCancha) ?></span></td>

            <td class="td-fecha"><?= $fd ?></td>

            <td class="td-duracion">
              <span class="dur-badge">
                <i class="bi bi-stopwatch"></i>
                <?= formatDuracion($dur) ?>
              </span>
            </td>

            <td>
              <!-- .table-actions + .table-btn-icon de tables.css -->
              <div class="table-actions" style="justify-content:flex-end;">
                <button class="table-btn-icon btn-vid-play"
                  title="Reproducir"
                  data-vid-url="<?= htmlspecialchars($videoUrl, ENT_QUOTES) ?>"
                  data-vid-code="<?= htmlspecialchars($videoCode, ENT_QUOTES) ?>"
                  data-vid-protected="<?= $isVideoProtected ? '1' : '0' ?>"
                  data-vid-titulo="<?= htmlspecialchars($descr, ENT_QUOTES) ?>"
                  data-vid-cancha="<?= htmlspecialchars($codCancha, ENT_QUOTES) ?>"
                  data-vid-dur="<?= (int)$dur ?>"
                  data-vid-fecha="<?= htmlspecialchars($fd . ($hora ? ' · ' . $hora : ''), ENT_QUOTES) ?>">
                  <i class="bi bi-play-fill"></i>
                </button>
                <?php if ($esDescargable && $videoUrl): ?>
                <a class="table-btn-icon btn-vid-dl"
                   href="<?= htmlspecialchars($videoUrl) ?>"
                   download="<?= htmlspecialchars($video['codigo_video']) ?>.mp4"
                   target="_blank"
                   data-vid-code="<?= htmlspecialchars($videoCode, ENT_QUOTES) ?>"
                   data-vid-protected="<?= $isVideoProtected ? '1' : '0' ?>"
                   title="Descargar video">
                  <i class="bi bi-download"></i>
                </a>
                <?php endif; ?>
                <button class="table-btn-icon btn-video-password"
                        title="<?= $isVideoProtected ? 'Cambiar o quitar contraseña' : 'Configurar contraseña' ?>"
                        data-password-video="<?= htmlspecialchars($videoCode, ENT_QUOTES) ?>"
                        data-password-protected="<?= $isVideoProtected ? '1' : '0' ?>">
                  <i class="bi bi-key-fill"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Paginación -->
      <?php if ($totalPaginas > 1): ?>
      <div class="pagination-bar">
        <a class="btn-page <?= $paginaActual <= 1 ? 'disabled' : '' ?>"
           href="?pagina=<?= $paginaActual - 1 ?>&fecha=<?= urlencode($filtroFecha ?? '') ?>&cancha=<?= urlencode($filtroCancha ?? '') ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php
        for ($p = max(1, $paginaActual - 2); $p <= min($totalPaginas, $paginaActual + 2); $p++):
        ?>
          <a class="btn-page <?= $p === $paginaActual ? 'active' : '' ?>"
             href="?pagina=<?= $p ?>&fecha=<?= urlencode($filtroFecha ?? '') ?>&cancha=<?= urlencode($filtroCancha ?? '') ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
        <a class="btn-page <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>"
           href="?pagina=<?= $paginaActual + 1 ?>&fecha=<?= urlencode($filtroFecha ?? '') ?>&cancha=<?= urlencode($filtroCancha ?? '') ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</main>

<!-- ═══ MODAL DE REPRODUCCIÓN ════════════════════════════════════════
     Usa .modal-overlay + .modal-container de modals.css
     Solo override: max-width y padding=0 (vid-modal-wrap)
════════════════════════════════════════════════════════════════════ -->
<div id="vidModal" class="modal-overlay" onclick="if(event.target===this)cerrarVideo()">
  <div class="modal-container vid-modal-wrap">

    <!-- .modal-header de modals.css -->
    <div class="modal-header" style="padding:14px 20px;margin-bottom:0;">
      <h3 class="modal-title" style="font-size:1rem;">
        <i class="bi bi-play-circle"></i>
        <span id="vid-modal-title-text">Reproducción</span>
      </h3>
      <!-- .modal-close de modals.css -->
      <button class="modal-close" onclick="cerrarVideo()" title="Cerrar">
        <i class="bi bi-x-lg" style="font-size:1rem;"></i>
      </button>
    </div>

    <!-- Cuerpo del modal: video sin padding -->
    <div class="vid-modal-body-inner">
      <video id="vid-player" controls preload="metadata" playsinline>
        <source id="vid-player-src" src="" type="video/mp4">
        Tu navegador no soporta HTML5 video.
      </video>
      <!-- Metadatos (solo en este contexto) -->
      <div class="vid-modal-meta" id="vid-modal-meta"></div>
    </div>

  </div>
</div>

<div id="videoPasswordModal" class="modal-overlay" aria-hidden="true">
  <div class="modal-container video-password-modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="bi bi-shield-lock"></i> Contraseña del video</h3>
      <button type="button" class="modal-close" data-password-modal-close aria-label="Cerrar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <form id="videoPasswordForm" class="video-password-form">
      <div class="video-password-row">
        <label class="video-password-toggle" for="owner_video_password_enabled">
          <input id="owner_video_password_enabled" class="video-password-switch" type="checkbox">
          <span>Proteger con contraseña</span>
        </label>
        <div id="owner_video_password_control" class="video-password-control" hidden>
          <input id="owner_video_password" type="password" minlength="8" maxlength="72"
                 pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                 title="Usa al menos 8 caracteres e incluye una mayúscula, una minúscula, un número y un símbolo."
                 autocomplete="new-password" placeholder="Ingrese la contraseña">
          <button type="button" class="video-password-eye" aria-label="Mostrar contraseña" aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
          </button>
        </div>
      </div>
      <p id="owner_video_password_help" class="video-password-help"></p>
      <p id="owner_video_password_message" class="video-password-message" role="status"></p>
      <div class="video-password-actions">
        <button type="button" class="btn-filter-clear" data-password-modal-close>Cancelar</button>
        <button type="submit" id="owner_video_password_save" class="btn-filter">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>

<script>window.POMPLAY_BASE = '<?= $baseUrl ?>';</script>
<script>window.VIDEO_PASSWORD_CSRF = <?= json_encode($_SESSION['video_password_csrf']) ?>;</script>
<script>
/* ── Video Modal ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

  // Delegated click: un solo listener para todos los botones de reproducción
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-vid-play');
    if (!btn) return;
    e.preventDefault();
    const url     = btn.dataset.vidUrl    || '';
    const titulo  = btn.dataset.vidTitulo || 'Grabación';
    const cancha  = btn.dataset.vidCancha || '';
    const duracion = parseInt(btn.dataset.vidDur, 10) || 0;
    const fecha   = btn.dataset.vidFecha  || '';
    abrirVideoModal(
      url,
      titulo,
      cancha,
      duracion,
      fecha,
      btn.dataset.vidCode || '',
      btn.dataset.vidProtected === '1'
    );
  });

  document.addEventListener('click', async function (event) {
    const link = event.target.closest('.btn-vid-dl[data-vid-protected="1"]');
    if (!link) return;
    event.preventDefault();
    const downloadWindow = window.open('about:blank', '_blank');
    if (!downloadWindow) {
      window.alert('Permite las ventanas emergentes para descargar este video.');
      return;
    }

    const password = window.prompt('Este video está protegido. Ingresa su contraseña:');
    if (password === null) {
      downloadWindow.close();
      return;
    }

    try {
      const response = await fetch(
        `${window.POMPLAY_BASE}/api/videos/${encodeURIComponent(link.dataset.vidCode)}/verificar-contrasena`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password }),
        }
      );
      const result = await response.json();
      if (!response.ok || result.valid !== true) {
        downloadWindow.close();
        window.alert(result.message || 'Contraseña incorrecta.');
        return;
      }
      downloadWindow.location = link.href;
    } catch (error) {
      downloadWindow.close();
      window.alert('No se pudo verificar la contraseña. Inténtalo nuevamente.');
    }
  });

  const passwordModal = document.getElementById('videoPasswordModal');
  const passwordForm = document.getElementById('videoPasswordForm');
  const passwordEnabled = document.getElementById('owner_video_password_enabled');
  const passwordControl = document.getElementById('owner_video_password_control');
  const passwordInput = document.getElementById('owner_video_password');
  const passwordHelp = document.getElementById('owner_video_password_help');
  const passwordMessage = document.getElementById('owner_video_password_message');
  const passwordSave = document.getElementById('owner_video_password_save');
  const passwordEye = document.querySelector('#videoPasswordModal .video-password-eye');
  let activePasswordButton = null;
  let videoWasProtected = false;
  let passwordSaving = false;

  function closePasswordModal() {
    if (passwordSaving) return;
    passwordModal.classList.remove('active');
    passwordModal.setAttribute('aria-hidden', 'true');
    passwordInput.value = '';
    passwordInput.type = 'password';
    passwordMessage.textContent = '';
    passwordMessage.className = 'video-password-message';
    passwordEye.setAttribute('aria-pressed', 'false');
    passwordEye.setAttribute('aria-label', 'Mostrar contraseña');
    passwordEye.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
    activePasswordButton = null;
  }

  passwordEnabled.addEventListener('change', function () {
    passwordControl.hidden = !this.checked;
    passwordInput.disabled = !this.checked;
    passwordInput.required = this.checked && !videoWasProtected;
    passwordMessage.textContent = '';
    passwordMessage.className = 'video-password-message';
    if (!this.checked) {
      passwordInput.value = '';
      passwordInput.type = 'password';
      passwordEye.setAttribute('aria-pressed', 'false');
      passwordEye.setAttribute('aria-label', 'Mostrar contraseña');
      passwordEye.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
    }
  });

  passwordEye.addEventListener('click', function () {
    const show = passwordInput.type === 'password';
    passwordInput.type = show ? 'text' : 'password';
    passwordEye.setAttribute('aria-pressed', String(show));
    passwordEye.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    passwordEye.innerHTML = `<i class="bi bi-eye${show ? '-slash' : ''}" aria-hidden="true"></i>`;
    passwordInput.focus();
  });

  passwordModal.addEventListener('click', function (event) {
    if (event.target === passwordModal || event.target.closest('[data-password-modal-close]')) {
      closePasswordModal();
    }
  });

  document.addEventListener('click', function (event) {
    const btn = event.target.closest('[data-password-video]');
    if (!btn || passwordSaving) return;
    activePasswordButton = btn;
    videoWasProtected = btn.dataset.passwordProtected === '1';
    passwordEnabled.checked = videoWasProtected;
    passwordControl.hidden = !videoWasProtected;
    passwordInput.disabled = !videoWasProtected;
    passwordInput.required = false;
    passwordInput.value = '';
    passwordHelp.textContent = videoWasProtected
      ? 'El video está protegido. Ingresa otra contraseña con mayúscula, minúscula, número y símbolo para cambiarla, o desactiva la opción para quitarla.'
      : 'Activa la opción para asignar una contraseña de mínimo 8 caracteres con mayúscula, minúscula, número y símbolo.';
    passwordMessage.textContent = '';
    passwordMessage.className = 'video-password-message';
    passwordModal.classList.add('active');
    passwordModal.setAttribute('aria-hidden', 'false');
    if (videoWasProtected) passwordInput.focus();
  });

  passwordForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!activePasswordButton || passwordSave.disabled) return;

    const value = passwordInput.value;
    const remove = !passwordEnabled.checked;
    if (!remove && value === '' && !videoWasProtected) {
      passwordMessage.className = 'video-password-message error';
      passwordMessage.textContent = 'Ingresa una contraseña para proteger el video.';
      passwordInput.focus();
      return;
    }
    const strongPassword = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}$/;
    if (!remove && value !== '' && (!strongPassword.test(value) || new TextEncoder().encode(value).length > 72)) {
      passwordMessage.className = 'video-password-message error';
      passwordMessage.textContent = 'La contraseña debe tener al menos 8 caracteres e incluir mayúscula, minúscula, número y símbolo (máximo 72 bytes).';
      passwordInput.focus();
      return;
    }
    if (!remove && value === '' && videoWasProtected) {
      closePasswordModal();
      return;
    }

    const triggerButton = activePasswordButton;
    const code = triggerButton.dataset.passwordVideo;
    passwordSaving = true;
    passwordSave.disabled = true;
    triggerButton.disabled = true;
    passwordMessage.textContent = 'Guardando...';
    try {
      const response = await fetch(
        `${window.POMPLAY_BASE}/api/videos/${encodeURIComponent(code)}/contrasena`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            password: value,
            remove,
            csrf_token: window.VIDEO_PASSWORD_CSRF,
          }),
        }
      );
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo actualizar');

      triggerButton.dataset.passwordProtected = result.protected ? '1' : '0';
      triggerButton.title = result.protected ? 'Cambiar o quitar contraseña' : 'Configurar contraseña';
      document.querySelectorAll('[data-vid-code]').forEach(function (item) {
        if (item.dataset.vidCode === code) {
          item.dataset.vidProtected = result.protected ? '1' : '0';
        }
      });
      passwordSaving = false;
      closePasswordModal();
      window.alert(result.message);
    } catch (error) {
      passwordMessage.className = 'video-password-message error';
      passwordMessage.textContent = error.message || 'No se pudo actualizar la contraseña.';
    } finally {
      passwordSaving = false;
      passwordSave.disabled = false;
      triggerButton.disabled = false;
    }
  });

  // Cerrar al hacer click en el overlay (fuera del contenedor)
  const overlay = document.getElementById('vidModal');
  if (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) cerrarVideo();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarVideo();
  });
});

async function abrirVideoModal(url, titulo, cancha, duracion, fecha, codigoVideo, protectedVideo) {
  const modal  = document.getElementById('vidModal');
  const player = document.getElementById('vid-player');
  const source = document.getElementById('vid-player-src');
  const meta   = document.getElementById('vid-modal-meta');

  if (!modal || !player || !source) {
    console.error('vidModal: elementos no encontrados en el DOM');
    return;
  }

  if (protectedVideo) {
    const password = window.prompt('Este video está protegido. Ingresa su contraseña:');
    if (password === null) return;
    try {
      const response = await fetch(
        `${window.POMPLAY_BASE}/api/videos/${encodeURIComponent(codigoVideo)}/verificar-contrasena`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password }),
        }
      );
      const result = await response.json();
      if (!response.ok || result.valid !== true) {
        window.alert(result.message || 'Contraseña incorrecta.');
        return;
      }
    } catch (error) {
      window.alert('No se pudo verificar la contraseña. Inténtalo nuevamente.');
      return;
    }
  }

  document.getElementById('vid-modal-title-text').textContent = titulo;

  // Cargar el video
  source.src = url;
  player.load();

  // Metadatos
  meta.innerHTML = [
    fecha  ? `<div class="vid-modal-meta-item"><i class="bi bi-calendar3"></i> ${fecha}</div>`   : '',
    cancha ? `<div class="vid-modal-meta-item"><i class="bi bi-geo-alt"></i> ${cancha}</div>`    : '',
    `<div class="vid-modal-meta-item"><i class="bi bi-stopwatch"></i> ${formatDurJS(duracion)}</div>`,
  ].join('');

  // Mostrar modal
  modal.style.display = 'flex';
  // Forzar reflow para que la transición CSS arranque
  modal.offsetHeight;
  modal.classList.add('active');

  // Intentar reproducir (puede fallar por política del navegador, está controlado)
  player.play().catch(() => {});
}

function cerrarVideo() {
  const modal  = document.getElementById('vidModal');
  const player = document.getElementById('vid-player');
  const source = document.getElementById('vid-player-src');
  if (!modal) return;
  player.pause();
  source.src = '';
  player.load();
  modal.classList.remove('active');
  // Ocultar tras la animación de salida
  setTimeout(() => { modal.style.display = 'none'; }, 310);
}

function formatDurJS(seg) {
  if (!seg || seg <= 0) return '—';
  const h = Math.floor(seg / 3600);
  const m = Math.floor((seg % 3600) / 60);
  const s = seg % 60;
  if (h > 0) return `${h}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
  if (m > 0) return `${m}m ${String(s).padStart(2,'0')}s`;
  return `${s}s`;
}
</script>
