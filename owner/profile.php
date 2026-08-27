<?php include __DIR__ . '/../admin/shared/header_admin.php'; ?>
<?php include __DIR__ . '/../conexion.php'; ?>

<!-- Owner Profile CSS -->
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-profile.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/forms-enhanced.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/modals.css">

<?php
$userId = $_SESSION['user_id'] ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;

if (!$userId || !$propietarioId) {
    die('Acceso denegado');
}

try {
    // Obtener datos del usuario y propietario
    $stmt = $pdo->prepare("CALL GetOwnerProfileForEdit(:id)");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} catch (PDOException $e) {
    die('Error al obtener perfil: ' . $e->getMessage());
}

try {
    // Obtener locales del propietario
    $stmtLocales = $pdo->prepare("CALL GetOwnerLocalList(:id_propietario)");
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
} catch (PDOException $e) {
    $locales = [];
}

$error = null;
$success = null;

// Solo procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        if (!empty($_POST['password'])) {
            $stmtPass = $pdo->prepare("CALL UpdateUserPassword(:id, :password)");
            $stmtPass->execute([
                ':password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
                ':id' => $userId,
            ]);
            $stmtPass->closeCursor();
            $success = 'Contraseña actualizada exitosamente';
        }
    } catch (PDOException $e) {
        $error = 'Error al actualizar la contraseña: ' . $e->getMessage();
    }
}
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mi Perfil</h1>
      <p class="page-subtitle">Información de tu cuenta</p>
    </div>
  </div>

  <div class="profile-container">
    <?php if ($error): ?>
      <div class="alert-form alert-form-error mb-3">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert-form alert-form-success mb-3">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>

    <!-- Hero Card con Avatar / Logo propietario -->
    <div class="profile-hero-card">
      <div class="profile-avatar-large">
        <?php if (!empty($propietarioId)): ?>
          <img
            id="owner-logo-img"
            src="<?= $baseUrl ?>/public/logo.php?id=<?= (int)$propietarioId ?>"
            alt="Logo propietario"
            style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
            onerror="this.style.display='none';document.getElementById('owner-logo-fallback').style.display='flex';"
          >
          <i id="owner-logo-fallback" class="fas fa-user" style="display:none;"></i>
        <?php else: ?>
          <i class="fas fa-user"></i>
        <?php endif; ?>
      </div>
      <div class="profile-hero-info">
        <h2 class="profile-name"><?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h2>
        <div class="profile-role-badge">
         
          <span>Propietario</span>
        </div>
        <div class="profile-stats">
          <div class="profile-stat-item">
            <i class="fas fa-store"></i>
            <span><?= count($locales) ?> <?= count($locales) === 1 ? 'Local' : 'Locales' ?></span>
          </div>
          <div class="profile-stat-item">
            <i class="fas fa-envelope"></i>
            <span><?= htmlspecialchars($user['email']) ?></span>
          </div>
        </div>
      </div>
      <div class="profile-actions">
        <button type="button" class="btn-profile-action btn-profile-action-primary" onclick="abrirModal()">
          <i class="fas fa-lock"></i>
          Cambiar Contraseña
        </button>
      </div>
    </div>

    <!-- Card: Mis Locales -->
    <div class="form-card-enhanced">
      <div class="form-card-header-enhanced">
        <h2 class="form-card-title-enhanced">
          <i class="fas fa-store"></i>
          Mis Locales Deportivos
        </h2>
        <p class="form-card-subtitle-enhanced">Locales asignados a tu cuenta</p>
      </div>

      <?php if (empty($locales)): ?>
        <div class="empty-state">
          <i class="fas fa-store-slash"></i>
          <p>No tienes locales asignados actualmente</p>
        </div>
      <?php else: ?>
        <div class="locales-grid">
          <?php foreach ($locales as $loc): ?>
            <div class="local-badge">
              <i class="fas fa-store"></i>
              <span><?= htmlspecialchars($loc['nombre_local']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Modal: Cambiar Contraseña -->
<div class="modal-overlay" id="passwordModal">
  <div class="modal-container">
    <div class="modal-header">
      <h2 class="modal-title">
        <i class="fas fa-lock"></i>
        Cambiar Contraseña
      </h2>
      <button type="button" class="modal-close" onclick="cerrarModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form method="POST" id="passwordForm">
      <input type="hidden" name="change_password" value="1">
      
      <div class="modal-body">
        <div class="modal-field">
          <label class="modal-label" for="modal_password">Nueva Contraseña</label>
          <input type="password" id="modal_password" name="password" class="modal-input" 
                 placeholder="Mínimo 6 caracteres" required>
        </div>

        <div class="modal-field">
          <label class="modal-label" for="modal_confirm_password">Confirmar Contraseña</label>
          <input type="password" id="modal_confirm_password" name="confirm_password" class="modal-input" 
                 placeholder="Repite la nueva contraseña" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="modal-btn-primary">
          <i class="fas fa-check"></i> Cambiar Contraseña
        </button>
        <button type="button" onclick="cerrarModal()" class="modal-btn-secondary">
          <i class="fas fa-times"></i> Cancelar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirModal() {
  var modal = document.getElementById('passwordModal');
  if (modal) {
    modal.style.display = 'flex';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    console.log('Modal abierto');
  } else {
    console.error('Modal no encontrado');
  }
}

function cerrarModal() {
  var modal = document.getElementById('passwordModal');
  if (modal) {
    modal.style.display = 'none';
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    var form = document.getElementById('passwordForm');
    if (form) form.reset();
  }
}

// Cerrar al hacer clic fuera
setTimeout(function() {
  var modal = document.getElementById('passwordModal');
  if (modal) {
    modal.onclick = function(e) {
      if (e.target === modal) {
        cerrarModal();
      }
    };
  }
}, 100);

// Validación
setTimeout(function() {
  var form = document.getElementById('passwordForm');
  if (form) {
    form.onsubmit = function(e) {
      var password = document.getElementById('modal_password').value;
      var confirmPassword = document.getElementById('modal_confirm_password').value;
      
      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Las contraseñas no coinciden');
        return false;
      }
      
      if (password.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres');
        return false;
      }
    };
  }
}, 100);
</script>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>
