<?php include 'shared/header_admin.php'; ?>
<?php include '../conexion.php'; ?>

<!-- Incluir nuevos CSS -->
<link rel="stylesheet" href="/public/css/forms-enhanced.css">
<link rel="stylesheet" href="/public/css/modals.css">

<style>
/* Rediseño: una sola card unificada con perfil + locales */
.profile-container {
  max-width: 760px;
  margin: 0 auto;
  padding: 0 1rem 3rem;
}

.profile-card {
  position: relative;
  background: linear-gradient(180deg, rgba(236, 66, 55, 0.05) 0%, rgba(255, 255, 255, 0.02) 140px);
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 18px;
  padding: 32px;
  overflow: hidden;
  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
}

.profile-card::before {
  content: '';
  position: absolute;
  top: -80px;
  left: -60px;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, rgba(236, 66, 55, 0.16) 0%, rgba(236, 66, 55, 0) 70%);
  pointer-events: none;
}

/* Encabezado */
.profile-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 20px;
  padding-bottom: 26px;
  margin-bottom: 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.profile-avatar-large {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: rgba(236, 66, 55, 0.1);
  display: grid;
  place-items: center;
  color: #ec4237;
  font-size: 1.9rem;
  flex-shrink: 0;
  border: 1px solid rgba(236, 66, 55, 0.3);
  box-shadow: 0 0 0 6px rgba(236, 66, 55, 0.06);
}

.profile-header-info {
  flex: 1;
  min-width: 0;
}

.profile-name-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.profile-name {
  font-size: 1.45rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

.profile-role-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background: rgba(236, 66, 55, 0.12);
  border: 1px solid rgba(236, 66, 55, 0.35);
  color: #ec4237;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.profile-username-sub {
  color: rgba(255, 255, 255, 0.45);
  font-size: 0.88rem;
  margin: 8px 0 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.profile-username-sub i {
  color: rgba(255, 255, 255, 0.3);
  font-size: 0.78rem;
}

.profile-header-action {
  flex-shrink: 0;
}

.btn-profile-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 20px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #ec4237;
  background: rgba(236, 66, 55, 0.08);
  border: 1px solid rgba(236, 66, 55, 0.35);
  border-radius: 999px;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  text-decoration: none;
  white-space: nowrap;
}

.btn-profile-action:hover {
  background: rgba(236, 66, 55, 0.16);
  border-color: rgba(236, 66, 55, 0.55);
}

/* Secciones dentro de la card */
.settings-section {
  position: relative;
  padding: 22px 0 0;
}

.settings-section + .settings-section {
  margin-top: 22px;
  padding-top: 22px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.settings-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.55);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 4px;
}

.settings-section-title i {
  color: rgba(236, 66, 55, 0.8);
  font-size: 0.72rem;
}

.settings-section-subtitle {
  color: rgba(255, 255, 255, 0.4);
  font-size: 0.84rem;
  margin: 0 0 16px;
}

.settings-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
}

.settings-row + .settings-row {
  border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.settings-row-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.settings-label {
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.9rem;
  font-weight: 500;
}

.settings-value {
  color: rgba(255, 255, 255, 0.4);
  font-size: 0.82rem;
}

.settings-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: none;
  color: #ec4237;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  padding: 6px 0;
  white-space: nowrap;
  transition: opacity 0.15s ease;
}

.settings-link-btn:hover {
  opacity: 0.75;
}

/* Locales: grid de badges dentro de la misma card */
.locales-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.local-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 600;
  font-size: 0.88rem;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.local-badge:hover {
  border-color: rgba(236, 66, 55, 0.3);
  background: rgba(236, 66, 55, 0.05);
}

.local-badge i {
  color: #ec4237;
  min-width: 18px;
  font-size: 0.9rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
  color: rgba(255, 255, 255, 0.35);
}

.empty-state i {
  font-size: 2rem;
  margin-bottom: 12px;
  opacity: 0.5;
}

.empty-state p {
  font-size: 0.9rem;
  margin: 0;
}

@media (max-width: 600px) {
  .profile-card {
    padding: 24px;
  }

  .profile-header {
    flex-wrap: wrap;
  }

  .profile-header-action {
    width: 100%;
  }

  .btn-profile-action {
    width: 100%;
  }

  .settings-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .settings-link-btn {
    align-self: flex-start;
  }

  .locales-grid {
    grid-template-columns: 1fr;
  }
}

/* Modal de cambio de contraseña */
#passwordModal .modal-container {
  background: rgba(15, 17, 34, 1);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 28px;
  max-width: 420px;
  width: 92%;
  box-shadow: none;
  animation: modalSlideIn 0.2s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

#passwordModal .modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

#passwordModal .modal-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #ffffff;
}

#passwordModal .modal-title i {
  color: rgba(236, 66, 55, 0.85);
  font-size: 0.9rem;
}

#passwordModal .modal-close {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.9rem;
  cursor: pointer;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: background 0.15s ease, color 0.15s ease;
  padding: 0;
}

#passwordModal .modal-close:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #ffffff;
}

#passwordModal .modal-body {
  margin-bottom: 20px;
}

#passwordModal .modal-field {
  margin-bottom: 16px;
}

#passwordModal .modal-label {
  display: block;
  color: rgba(255, 255, 255, 0.55);
  font-weight: 500;
  font-size: 0.78rem;
  margin-bottom: 6px;
}

#passwordModal .modal-input {
  width: 100%;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 11px 13px;
  color: #ffffff;
  font-size: 0.88rem;
  transition: border-color 0.15s ease;
  outline: none;
}

#passwordModal .modal-input::placeholder {
  color: rgba(255, 255, 255, 0.28);
}

#passwordModal .modal-input:focus {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(236, 66, 55, 0.55);
  box-shadow: none;
}

#passwordModal .modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

#passwordModal .modal-btn-primary,
#passwordModal .modal-btn-secondary {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.85rem;
  transition: background 0.15s ease, border-color 0.15s ease;
  border: 1px solid transparent;
  cursor: pointer;
}

#passwordModal .modal-btn-primary {
  background: transparent;
  color: #ec4237;
  border-color: #ec4237;
  box-shadow: none;
}

#passwordModal .modal-btn-primary:hover {
  background: rgba(236, 66, 55, 0.08);
}

#passwordModal .modal-btn-secondary {
  background: transparent;
  color: rgba(255, 255, 255, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

#passwordModal .modal-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.04);
}

@media (max-width: 480px) {
  #passwordModal .modal-container {
    padding: 22px;
    max-width: 95%;
  }

  #passwordModal .modal-footer {
    flex-direction: column;
  }
}
</style>

<?php
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;

if (!$userId) {
    die('Acceso denegado');
}

$error = null;
$success = null;

$stmt = $pdo->prepare("CALL GetProfileByUserId(:id)");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if (!$user) {
    die('Usuario no encontrado');
}

$propietarioId = $user['id_propietario'] ?? null;

$stmtLocales = $pdo->prepare("CALL GetOwnerLocales(:id_propietario)");
$locales = [];
if ($propietarioId) {
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
}

// Solo procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '' || $confirmPassword === '') {
            $error = 'Completa la nueva contraseña y su confirmación.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'La contraseña actual no es correcta.';
        } else {
            $stmtPassword = $pdo->prepare("CALL UpdateUserPassword(:id, :password)");
            $stmtPassword->execute([
                ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
                ':id' => $userId,
            ]);
            $stmtPassword->closeCursor();

            $success = 'Contraseña actualizada correctamente.';
        }

        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    } catch (PDOException $e) {
        $error = 'No se pudo actualizar la contraseña: ' . $e->getMessage();
    }
}

$displayName = trim(($user['nombres'] ?? '') . ' ' . ($user['apellidos'] ?? '')) ?: $user['usuario'];
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

    <!-- Card única: perfil + cuenta + locales -->
    <div class="profile-card">
      <div class="profile-header">
        <div class="profile-avatar-large" style="padding: 10px;">
          <img src="<?= $baseUrl ?>/public/images/pomplay%20logo.png" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <div class="profile-header-info">
          <div class="profile-name-row">
            <h2 class="profile-name"><?= htmlspecialchars($displayName) ?></h2>
            <span class="profile-role-badge"><?= htmlspecialchars($user['rol'] ?? 'Administrador') ?></span>
          </div>
          <p class="profile-username-sub">
            <i class="fas fa-user"></i>
            <?= htmlspecialchars($user['usuario']) ?>
          </p>
        </div>
        <div class="profile-header-action">
          <button type="button" class="btn-profile-action" onclick="abrirModal()">
            <i class="fas fa-lock"></i>
            Cambiar Contraseña
          </button>
        </div>
      </div>

      <!-- Cuenta -->
      <div class="settings-section">
        <p class="settings-section-title">
          <i class="fas fa-id-badge"></i>
          Cuenta
        </p>
        <div class="settings-row">
          <div class="settings-row-text">
            <span class="settings-label">Usuario</span>
            <span class="settings-value"><?= htmlspecialchars($user['usuario']) ?></span>
          </div>
        </div>
      </div>

      <!-- Mis Locales (si tiene) -->
      <?php if ($propietarioId && !empty($locales)): ?>
      <div class="settings-section">
        <p class="settings-section-title">
          <i class="fas fa-store"></i>
          Mis Locales Deportivos
        </p>
        <p class="settings-section-subtitle">Locales asignados a tu cuenta</p>
        <div class="locales-grid">
          <?php foreach ($locales as $loc): ?>
            <div class="local-badge">
              <i class="fas fa-store"></i>
              <span><?= htmlspecialchars($loc['nombre_local']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Modal: Cambiar Contraseña -->
<div class="modal-overlay" id="passwordModal" style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; z-index: 999999 !important; align-items: center !important; justify-content: center !important; background: rgba(0, 0, 0, 0.85) !important; backdrop-filter: blur(8px) !important;">
  <div class="modal-container" style="position: relative !important; z-index: 1000000 !important;">
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
          <label class="modal-label" for="current_password">Contraseña Actual</label>
          <input type="password" id="current_password" name="current_password" class="modal-input" 
                 placeholder="Tu contraseña actual" required>
        </div>

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
      var currentPassword = document.getElementById('current_password').value;
      var password = document.getElementById('modal_password').value;
      var confirmPassword = document.getElementById('modal_confirm_password').value;
      
      if (!currentPassword) {
        e.preventDefault();
        alert('Ingresa tu contraseña actual');
        return false;
      }
      
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

<?php include 'shared/footer_admin.php'; ?>