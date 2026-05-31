<?php include 'shared/header_admin.php'; ?>
<?php include '../conexion.php'; ?>

<!-- Incluir nuevos CSS -->
<link rel="stylesheet" href="/public/css/forms-enhanced.css">
<link rel="stylesheet" href="/public/css/modals.css">

<style>
/* Estilos mejorados para el perfil del admin */
.profile-container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Hero Card con Avatar */
.profile-hero-card {
  background: linear-gradient(135deg, rgba(61, 240, 194, 0.15) 0%, rgba(61, 240, 194, 0.05) 100%);
  border: 1px solid rgba(61, 240, 194, 0.2);
  border-radius: 20px;
  padding: 40px;
  margin-bottom: 32px;
  display: flex;
  align-items: center;
  gap: 32px;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.profile-hero-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var( #91e81e), #5ff5d1);
}

.profile-hero-card:hover {
  border-color: rgba(61, 240, 194, 0.4);
  box-shadow: 0 12px 50px rgba(0, 0, 0, 0.4);
  transform: translateY(-4px);
}

.profile-avatar-large {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: linear-gradient(135deg, var( #91e81e) 0%, #5ff5d1 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 4rem;
  color: #0a0a0f;
  font-weight: 800;
  box-shadow: 0 10px 30px rgba(61, 240, 194, 0.5);
  border: 5px solid rgba(61, 240, 194, 0.15);
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.profile-avatar-large::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.3) 100%);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.profile-hero-card:hover .profile-avatar-large::before {
  opacity: 1;
}

.profile-hero-info {
  flex: 1;
}

.profile-name {
  font-size: 2.2rem;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 12px 0;
  letter-spacing: -0.02em;
}

.profile-role-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  background: rgba(61, 240, 194, 0.25);
  color: var( #91e81e);
  border: 2px solid rgba(61, 240, 194, 0.5);
  border-radius: 25px;
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 16px;
  text-transform: uppercase;
}

.profile-role-badge i {
  font-size: 1.1rem;
}

.profile-stats {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
}

.profile-stat-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.95rem;
  font-weight: 500;
}

.profile-stat-item i {
  color: var( #91e81e);
  font-size: 1.1rem;
}

.profile-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-profile-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 14px 28px;
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.9);
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 50px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  white-space: nowrap;
}

.btn-profile-action:hover {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.4);
  color: #ffffff;
  transform: translateY(-2px);
}

.btn-profile-action-primary {
  background: linear-gradient(135deg, var( #3df0c2) 0%, #5ff5d1 10%);
  border-color: transparent;
  color: #0a0a0f;
  box-shadow: 0 6px 20px rgba(61, 240, 194, 0.4);
}

.btn-profile-action-primary:hover {
  background: linear-gradient(135deg, #2dd9b0 0%, #3df0c2 100%);
  box-shadow: 0 8px 24px rgba(61, 240, 194, 0.5);
  color: #0a0a0f;
}

/* Cards */
.form-card-enhanced {
  background: var(--color-bg-card, #11132d);
  border: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.form-card-enhanced::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var( #91e81e), #5ff5d1);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s ease;
}

.form-card-enhanced:hover::before {
  transform: scaleX(1);
}

.form-card-enhanced:hover {
  border-color: rgba(255, 255, 255, 0.15);
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
  transform: translateY(-2px);
}

.form-card-header-enhanced {
  margin-bottom: 28px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.form-card-title-enhanced {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #ffffff;
  font-size: 1.4rem;
  font-weight: 800;
  margin: 0 0 8px 0;
  letter-spacing: -0.01em;
}

.form-card-title-enhanced i {
  color: var(--dk-accent, #3df0c2);
  font-size: 1.3rem;
}

.form-card-subtitle-enhanced {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.9rem;
  margin: 0;
  font-weight: 400;
}

.locales-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.local-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(61, 240, 194, 0.15), rgba(61, 240, 194, 0.05));
  border: 1px solid rgba(61, 240, 194, 0.3);
  border-radius: 14px;
  color: #ffffff;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.local-badge:hover {
  background: linear-gradient(135deg, rgba(61, 240, 194, 0.25), rgba(61, 240, 194, 0.1));
  border-color: rgba(61, 240, 194, 0.5);
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(61, 240, 194, 0.3);
}

.local-badge i {
  color: var( #91e81e);
  font-size: 1.2rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: rgba(255, 255, 255, 0.5);
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 20px;
  opacity: 0.4;
}

.empty-state p {
  font-size: 1rem;
  margin: 0;
}

/* Responsive */
@media (max-width: 992px) {
  .profile-hero-card {
    flex-direction: column;
    text-align: center;
    padding: 32px;
  }

  .profile-avatar-large {
    width: 120px;
    height: 120px;
    font-size: 3rem;
  }

  .profile-name {
    font-size: 1.8rem;
  }

  .profile-stats {
    justify-content: center;
  }

  .profile-actions {
    width: 100%;
  }

  .btn-profile-action {
    width: 100%;
  }
}

@media (max-width: 768px) {
  .form-card-enhanced {
    padding: 24px;
  }

  .locales-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .profile-hero-card {
    padding: 24px;
  }

  .profile-avatar-large {
    width: 100px;
    height: 100px;
    font-size: 2.5rem;
  }

  .profile-name {
    font-size: 1.5rem;
  }

  .form-card-enhanced {
    padding: 20px;
  }

  .form-card-title-enhanced {
    font-size: 1.2rem;
  }
}

/* Estilos del Modal de Contraseña */
#passwordModal .modal-container {
  background: linear-gradient(135deg, #11132d, #11132d);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 16px;
  padding: 28px;
  max-width: 420px;
  width: 90%;
  box-shadow: 0 25px 70px rgba(0, 0, 0, 0.7);
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-30px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

#passwordModal .modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 22px;
  padding-bottom: 16px;
  border-bottom: 2px solid rgba(61, 240, 194, 0.3);
}

#passwordModal .modal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 1.3rem;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.01em;
}

#passwordModal .modal-title i {
  color: var( #91e81e);
  font-size: 1.2rem;
}

#passwordModal .modal-close {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.7);
  font-size: 1.1rem;
  cursor: pointer;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.3s ease;
  padding: 0;
}

#passwordModal .modal-close:hover {
  background: rgba(61, 240, 194, 0.2);
  border-color: var(#91e81e);
  color: #ffffff;
  transform: rotate(90deg);
}

#passwordModal .modal-body {
  margin-bottom: 22px;
}

#passwordModal .modal-field {
  margin-bottom: 18px;
}

#passwordModal .modal-field:last-child {
  margin-bottom: 0;
}

#passwordModal .modal-label {
  display: block;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 700;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

#passwordModal .modal-input {
  width: 100%;
  background: rgba(255, 255, 255, 0.08);
  border: 2px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  padding: 12px 16px;
  color: #ffffff;
  font-size: 0.95rem;
  font-family: 'Inter', sans-serif;
  transition: all 0.3s ease;
  outline: none;
}

#passwordModal .modal-input::placeholder {
  color: rgba(255, 255, 255, 0.4);
}

#passwordModal .modal-input:focus {
  background: rgba(255, 255, 255, 0.12);
  border-color: var(#91e81e);
  box-shadow: 0 0 0 4px rgba(61, 240, 194, 0.2);
  transform: translateY(-1px);
}

#passwordModal .modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

#passwordModal .modal-btn-primary {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  background: linear-gradient(135deg, var(#91e81e) 0%, #5ff5d1 100%);
  color: #0a0a0f;
  border: none;
  border-radius: 50px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 6px 20px rgba(61, 240, 194, 0.4);
}

#passwordModal .modal-btn-primary:hover {
  background: linear-gradient(135deg, #2dd9b0 0%, #3df0c2 100%);
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(61, 240, 194, 0.5);
}

#passwordModal .modal-btn-primary:active {
  transform: translateY(-1px);
}

#passwordModal .modal-btn-secondary {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 50px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
}

#passwordModal .modal-btn-secondary:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.4);
  transform: translateY(-2px);
}

/* Responsive Modal */
@media (max-width: 768px) {
  #passwordModal .modal-container {
    padding: 24px;
    width: 95%;
  }

  #passwordModal .modal-title {
    font-size: 1.2rem;
  }

  #passwordModal .modal-footer {
    flex-direction: column;
  }

  #passwordModal .modal-btn-primary,
  #passwordModal .modal-btn-secondary {
    width: 100%;
  }
}

@media (max-width: 480px) {
  #passwordModal .modal-container {
    padding: 20px;
    max-width: 95%;
  }

  #passwordModal .modal-title {
    font-size: 1.1rem;
  }

  #passwordModal .modal-input {
    font-size: 0.9rem;
    padding: 10px 14px;
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

    <!-- Hero Card con Avatar -->
    <div class="profile-hero-card">
      <div class="profile-avatar-large">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="profile-hero-info">
        <h2 class="profile-name"><?= htmlspecialchars($displayName) ?></h2>
        <div class="profile-role-badge">
          <i class="fas fa-shield-halved"></i>
          <span><?= htmlspecialchars($user['rol'] ?? 'Administrador') ?></span>
        </div>
        <div class="profile-stats">
          <div class="profile-stat-item">
            <i class="fas fa-user"></i>
            <span><?= htmlspecialchars($user['usuario']) ?></span>
          </div>
          <?php if ($propietarioId && !empty($locales)): ?>
          <div class="profile-stat-item">
            <i class="fas fa-store"></i>
            <span><?= count($locales) ?> <?= count($locales) === 1 ? 'Local' : 'Locales' ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="profile-actions">
        <button type="button" class="btn-profile-action btn-profile-action-primary" onclick="abrirModal()">
          <i class="fas fa-lock"></i>
          Cambiar Contraseña
        </button>
      </div>
    </div>

    <!-- Card: Mis Locales (si tiene) -->
    <?php if ($propietarioId && !empty($locales)): ?>
    <div class="form-card-enhanced">
      <div class="form-card-header-enhanced">
        <h2 class="form-card-title-enhanced">
          <i class="fas fa-store"></i>
          Mis Locales Deportivos
        </h2>
        <p class="form-card-subtitle-enhanced">Locales asignados a tu cuenta</p>
      </div>

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
