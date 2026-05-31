<?php
// Incluir el header primero (que maneja la sesión y autenticación)
include __DIR__ . '/../shared/header_admin.php';

// Incluir conexión
require __DIR__ . '/../../conexion.php';

// Incluir configuración
include __DIR__ . '/../config.php';
require __DIR__ . '/../../conexion.php';

// Verificar que se reciba el ID del local
$localId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$localId) {
    $_SESSION['error'] = 'ID de local no proporcionado';
    header('Location: ../locales/index.php');
    exit;
}

try {
    // Obtener información del local
    $stmtLocal = $pdo->prepare("CALL GetLocalById(:id_local)");
    $stmtLocal->execute([':id_local' => $localId]);
    $local = $stmtLocal->fetch(PDO::FETCH_ASSOC);
    $stmtLocal->closeCursor();

    if (!$local) {
        $_SESSION['error'] = 'Local no encontrado';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener propietario del local
    $stmtProp = $pdo->prepare("SELECT * FROM propietarios WHERE id_propietario = :id_propietario");
    $stmtProp->execute([':id_propietario' => $local['id_propietario']]);
    $propietario = $stmtProp->fetch(PDO::FETCH_ASSOC);

    if (!$propietario) {
        $_SESSION['error'] = 'Este local no tiene un propietario asignado';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener usuario del propietario
    $stmtUser = $pdo->prepare("CALL GetOwnerUserByPropietario(:id_propietario)");
    $stmtUser->execute([':id_propietario' => $propietario['id_propietario']]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $stmtUser->closeCursor();

    if (!$usuario) {
        $_SESSION['error'] = 'Este propietario no tiene un usuario asignado';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener membresía del local
    $stmtMembership = $pdo->prepare("CALL GetMembershipByUserLocal(:user_id, :local_id)");
    $stmtMembership->execute([
        ':user_id' => $usuario['id_usuario'],
        ':local_id' => $localId
    ]);
    $membership = $stmtMembership->fetch(PDO::FETCH_ASSOC);
    $stmtMembership->closeCursor();

    if (!$membership) {
        $_SESSION['error'] = 'Este local no tiene una membresía registrada';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener información del precio de la membresía actual
    $stmtPrecio = $pdo->prepare("SELECT * FROM precios_membresias WHERE tipo_membresia = :tipo AND activo = 1");
    $stmtPrecio->execute([':tipo' => $membership['tipo_membresia']]);
    $precioMembresia = $stmtPrecio->fetch(PDO::FETCH_ASSOC);

    if (!$precioMembresia) {
        $_SESSION['error'] = 'No se encontró información de precio para esta membresía';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener todas las membresías disponibles para el selector
    $stmtTodasMembresias = $pdo->query("SELECT * FROM precios_membresias WHERE activo = 1 ORDER BY duracion_meses ASC");
    $todasMembresias = $stmtTodasMembresias->fetchAll(PDO::FETCH_ASSOC);

    // Verificar si la membresía está vencida
    $fechaVencimiento = new DateTime($membership['fecha_vencimiento']);
    $fechaActual = new DateTime();
    $estaVencida = $fechaVencimiento < $fechaActual;

} catch (PDOException $e) {
    $_SESSION['error'] = 'Error al obtener información: ' . $e->getMessage();
    header('Location: ../locales/index.php');
    exit;
}
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Renovar Membresía</h1>
      <p class="page-subtitle">Renovar membresía del local</p>
    </div>
  </div>

  <div class="renew-container">
    <div class="renew-card">
      <div class="renew-header">
        <div class="renew-icon <?= $estaVencida ? 'expired' : 'active' ?>">
          <i class="fas fa-crown"></i>
        </div>
        <h2>Información de Membresía</h2>
      </div>

      <div class="info-grid">
        <div class="info-item">
          <label>Local</label>
          <p><?= htmlspecialchars($local['nombre_local']) ?></p>
        </div>

        <div class="info-item">
          <label>Propietario</label>
          <p><?= htmlspecialchars($propietario['nombres'] . ' ' . $propietario['apellidos']) ?></p>
        </div>

        <div class="info-item">
          <label>Email</label>
          <p><?= htmlspecialchars($propietario['email']) ?></p>
        </div>

        <div class="info-item">
          <label>Tipo de Membresía</label>
          <p><span class="badge-type"><?= htmlspecialchars($membership['tipo_membresia']) ?></span></p>
        </div>

        <div class="info-item">
          <label>Duración</label>
          <p><?= $precioMembresia['duracion_meses'] ?> <?= $precioMembresia['duracion_meses'] == 1 ? 'mes' : 'meses' ?></p>
        </div>

        <div class="info-item">
          <label>Precio</label>
          <p class="price-highlight">S/ <?= number_format($precioMembresia['precio'], 2) ?></p>
        </div>

        <div class="info-item">
          <label>Fecha de Inicio</label>
          <p><?= date('d/m/Y', strtotime($membership['fecha_inicio'])) ?></p>
        </div>

        <div class="info-item">
          <label>Fecha de Vencimiento</label>
          <p class="<?= $estaVencida ? 'text-expired' : 'text-active' ?>">
            <?= date('d/m/Y', strtotime($membership['fecha_vencimiento'])) ?>
          </p>
        </div>

        <div class="info-item full-width">
          <label>Estado Actual</label>
          <p>
            <?php if ($estaVencida): ?>
              <span class="badge-expired">
                <i class="fas fa-exclamation-triangle"></i> VENCIDA
              </span>
              <span class="days-info">
                Vencida hace <?= $fechaActual->diff($fechaVencimiento)->days ?> días
              </span>
            <?php else: ?>
              <span class="badge-active-status">
                <i class="fas fa-check-circle"></i> ACTIVA
              </span>
              <span class="days-info">
                Vence en <?= $fechaVencimiento->diff($fechaActual)->days ?> días
              </span>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <form method="POST" action="procesos/procesar_renovacion.php" id="formRenovar">
        <input type="hidden" name="id_membresia" value="<?= $membership['id_membresia'] ?>">
        <input type="hidden" name="id_local" value="<?= $localId ?>">
        <input type="hidden" name="esta_vencida" value="<?= $estaVencida ? '1' : '0' ?>">

        <!-- Selector de Membresía -->
        <div class="membership-selector-section">
          <h3 class="selector-title">
            <i class="fas fa-crown"></i>
            Seleccionar Membresía a Renovar
          </h3>
          <p class="selector-subtitle">Por defecto se muestra tu membresía actual, pero puedes cambiarla si lo deseas</p>
          
          <div class="membership-options">
            <?php foreach ($todasMembresias as $membresia): ?>
              <label class="membership-option <?= $membresia['tipo_membresia'] == $membership['tipo_membresia'] ? 'selected' : '' ?>">
                <input 
                  type="radio" 
                  name="tipo_membresia" 
                  value="<?= $membresia['tipo_membresia'] ?>"
                  data-duracion="<?= $membresia['duracion_meses'] ?>"
                  data-precio="<?= $membresia['precio'] ?>"
                  data-nombre="<?= htmlspecialchars($membresia['nombre_display']) ?>"
                  <?= $membresia['tipo_membresia'] == $membership['tipo_membresia'] ? 'checked' : '' ?>
                  required
                >
                <div class="option-content">
                  <div class="option-header">
                    <span class="option-badge"><?= htmlspecialchars($membresia['nombre_display']) ?></span>
                    <?php if ($membresia['tipo_membresia'] == $membership['tipo_membresia']): ?>
                      <span class="current-badge">
                        <i class="fas fa-check-circle"></i> Actual
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="option-details">
                    <div class="option-price">S/ <?= number_format($membresia['precio'], 2) ?></div>
                    <div class="option-duration">
                      <i class="fas fa-calendar-alt"></i>
                      <?= $membresia['duracion_meses'] ?> <?= $membresia['duracion_meses'] == 1 ? 'mes' : 'meses' ?>
                    </div>
                  </div>
                  <?php if (!empty($membresia['descripcion'])): ?>
                    <div class="option-description"><?= htmlspecialchars($membresia['descripcion']) ?></div>
                  <?php endif; ?>
                </div>
                
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="renew-info-box">
          <i class="fas fa-info-circle"></i>
          <div>
            <strong>Información de Renovación</strong>
            <p id="infoRenovacion">
              Al confirmar la renovación, la membresía se activará por un período de 
              <span id="duracionTexto"><?= $precioMembresia['duracion_meses'] ?> <?= $precioMembresia['duracion_meses'] == 1 ? 'mes' : 'meses' ?></span>
              a partir de la fecha actual. El estado cambiará automáticamente de VENCIDA a ACTIVA.
            </p>
          </div>
        </div>

        <div class="form-actions">
          <a href="../locales/index.php" class="btn-cancel">
            <i class="fas fa-times"></i> Cancelar
          </a>
          <button type="button" class="btn-renew" id="btnRenovar">
            <i class="fas fa-sync-alt"></i> Renovar Membresía
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal de Confirmación -->
  <div id="modalConfirmacion" class="modal-confirmacion" style="display: none;">
    <div class="modal-confirmacion-overlay"></div>
    <div class="modal-confirmacion-content">
      <div class="modal-confirmacion-icon">
        <i class="fas fa-question-circle"></i>
      </div>
      <h3>Confirmar Renovación</h3>
      <p>¿Está seguro de que desea renovar esta membresía?</p>
      <div class="modal-confirmacion-info">
        <i class="fas fa-info-circle"></i>
        <span>La membresía se activará por <strong><?= $precioMembresia['duracion_meses'] ?> <?= $precioMembresia['duracion_meses'] == 1 ? 'mes' : 'meses' ?></strong></span>
      </div>
      <div class="modal-confirmacion-actions">
        <button type="button" class="btn-modal-cancel" id="btnModalCancelar">
          <i class="fas fa-times"></i> Cancelar
        </button>
        <button type="button" class="btn-modal-confirm" id="btnModalConfirmar">
          <i class="fas fa-check"></i> Sí, Renovar
        </button>
      </div>
    </div>
  </div>
</main>

<style>
/* Modal de Confirmación */
.modal-confirmacion {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-confirmacion-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(8px);
}

.modal-confirmacion-content {
  position: relative;
  background: linear-gradient(135deg, #11132d 0%, #11132d 100%);
  border-radius: 20px;
  padding: 40px;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 25px 70px rgba(0,0,0,0.6);
  border: 1px solid rgba(255,255,255,0.15);
  text-align: center;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-confirmacion-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  background: linear-gradient(135deg, rgba(96,165,250,0.25), rgba(96,165,250,0.15));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 32px rgba(96,165,250,0.3);
}

.modal-confirmacion-icon i {
  font-size: 2.5rem;
  color: #60a5fa;
}

.modal-confirmacion-content h3 {
  margin: 0 0 16px;
  color: #fff;
  font-size: 1.8rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.modal-confirmacion-content > p {
  margin: 0 0 24px;
  color: rgba(255,255,255,0.8);
  font-size: 1.1rem;
  line-height: 1.6;
}

.modal-confirmacion-info {
  background: rgba(96,165,250,0.1);
  border: 1px solid rgba(96,165,250,0.3);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 32px;
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: center;
}

.modal-confirmacion-info i {
  color: #60a5fa;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.modal-confirmacion-info span {
  color: rgba(255,255,255,0.9);
  font-size: 0.95rem;
  line-height: 1.5;
}

.modal-confirmacion-actions {
  display: flex;
  gap: 14px;
  justify-content: center;
}

.btn-modal-cancel,
.btn-modal-confirm {
  padding: 14px 32px;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.3s ease;
  min-width: 140px;
}

.btn-modal-cancel {
  background: rgba(255,255,255,0.08);
  color: #fff;
  border: 2px solid rgba(255,255,255,0.2);
}

.btn-modal-cancel:hover {
  background: rgba(255,255,255,0.14);
  border-color: rgba(255,255,255,0.3);
  transform: translateY(-2px);
}

.btn-modal-confirm {
  background: linear-gradient(135deg, #3DF0C2, #2BC9A0);
  color: #000;
  box-shadow: 0 4px 20px rgba(61,240,194,0.4);
  border: 2px solid transparent;
}

.btn-modal-confirm:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(61,240,194,0.6);
  background: linear-gradient(135deg, #4FFFD4, #3DD9B2);
}

.btn-modal-cancel:active,
.btn-modal-confirm:active {
  transform: translateY(0);
}

/* Responsive Modal */
@media (max-width: 768px) {
  .modal-confirmacion {
    padding: 16px;
  }

  .modal-confirmacion-content {
    padding: 32px 24px;
  }

  .modal-confirmacion-icon {
    width: 70px;
    height: 70px;
    margin-bottom: 20px;
  }

  .modal-confirmacion-icon i {
    font-size: 2rem;
  }

  .modal-confirmacion-content h3 {
    font-size: 1.5rem;
  }

  .modal-confirmacion-content > p {
    font-size: 1rem;
  }

  .modal-confirmacion-actions {
    flex-direction: column;
    gap: 12px;
  }

  .btn-modal-cancel,
  .btn-modal-confirm {
    width: 100%;
    min-width: unset;
  }
}

@media (max-width: 480px) {
  .modal-confirmacion-content {
    padding: 28px 20px;
  }

  .modal-confirmacion-icon {
    width: 60px;
    height: 60px;
  }

  .modal-confirmacion-icon i {
    font-size: 1.8rem;
  }

  .modal-confirmacion-content h3 {
    font-size: 1.3rem;
  }

  .modal-confirmacion-info {
    padding: 14px;
    font-size: 0.9rem;
  }
}
    </div>
  </div>
</main>

<style>
/* Container Principal */
.renew-container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 20px 40px;
}

/* Card Principal */
.renew-card {
  background: rgba(0,0,0,.3);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 40px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Header */
.renew-header {
  text-align: center;
  margin-bottom: 40px;
  padding-bottom: 30px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.renew-icon {
  width: 90px;
  height: 90px;
  margin: 0 auto 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.8rem;
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  transition: transform 0.3s ease;
}

.renew-icon:hover {
  transform: scale(1.05);
}

.renew-icon.active {
  background: linear-gradient(135deg, rgba(61,240,194,0.25), rgba(61,240,194,0.15));
  color: #3DF0C2;
  box-shadow: 0 8px 32px rgba(61,240,194,0.3);
}

.renew-icon.expired {
  background: linear-gradient(135deg, rgba(236,66,55,0.25), rgba(236,66,55,0.15));
  color: #ec4237;
  box-shadow: 0 8px 32px rgba(236,66,55,0.3);
}

.renew-header h2 {
  margin: 0;
  color: #fff;
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

/* Grid de Información */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.info-item {
  background: rgba(255,255,255,0.04);
  padding: 24px;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.1);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.info-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 3px;
  background: linear-gradient(90deg, rgba(96,165,250,0.5), rgba(168,85,247,0.5));
  opacity: 0;
  transition: opacity 0.3s ease;
}

.info-item:hover {
  background: rgba(255,255,255,0.06);
  border-color: rgba(255,255,255,0.2);
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

.info-item:hover::before {
  opacity: 1;
}

.info-item.full-width {
  grid-column: 1 / -1;
}

.info-item label {
  display: block;
  color: rgba(255,255,255,0.6);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 10px;
}

.info-item p {
  margin: 0;
  color: #fff;
  font-size: 1.15rem;
  font-weight: 600;
  line-height: 1.4;
  word-break: break-word;
}

/* Badges */
.badge-type {
  display: inline-block;
  padding: 8px 18px;
  background: linear-gradient(135deg, rgba(96,165,250,0.25), rgba(96,165,250,0.15));
  color: #60a5fa;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 700;
  border: 1px solid rgba(96,165,250,0.3);
}

.price-highlight {
  color: #3DF0C2 !important;
  font-size: 1.8rem !important;
  font-weight: 900 !important;
  text-shadow: 0 2px 8px rgba(61,240,194,0.3);
}

.text-expired {
  color: #ec4237 !important;
  font-weight: 700 !important;
}

.text-active {
  color: #3DF0C2 !important;
  font-weight: 700 !important;
}

.badge-expired,
.badge-active-status {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 1.05rem;
  border: 1px solid;
}

.badge-expired {
  background: rgba(236,66,55,0.15);
  color: #ec4237;
  border-color: rgba(236,66,55,0.3);
}

.badge-active-status {
  background: rgba(61,240,194,0.15);
  color: #3DF0C2;
  border-color: rgba(61,240,194,0.3);
}

.days-info {
  display: inline-block;
  margin-left: 12px;
  color: rgba(255,255,255,0.7);
  font-size: 1rem;
  font-weight: 500;
}

/* Info Box */
.renew-info-box {
  background: linear-gradient(135deg, rgba(96,165,250,0.12), rgba(96,165,250,0.06));
  border: 1px solid rgba(96,165,250,0.3);
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 32px;
  display: flex;
  gap: 18px;
  align-items: flex-start;
  box-shadow: 0 4px 16px rgba(96,165,250,0.1);
}

.renew-info-box i {
  font-size: 1.8rem;
  color: #60a5fa;
  flex-shrink: 0;
  margin-top: 2px;
}

.renew-info-box strong {
  display: block;
  color: #fff;
  font-size: 1.1rem;
  margin-bottom: 10px;
  font-weight: 700;
}

.renew-info-box p {
  margin: 0;
  color: rgba(255,255,255,0.85);
  line-height: 1.7;
  font-size: 0.98rem;
}

/* Selector de Membresía */
.membership-selector-section {
  margin-bottom: 32px;
  padding: 32px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
}

.selector-title {
  margin: 0 0 8px;
  color: #fff;
  font-size: 1.4rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 12px;
}

.selector-title i {
  color: #3DF0C2;
  font-size: 1.3rem;
}

.selector-subtitle {
  margin: 0 0 24px;
  color: rgba(255,255,255,0.7);
  font-size: 0.95rem;
  line-height: 1.5;
}

.membership-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

.membership-option {
  position: relative;
  display: block;
  background: rgba(255,255,255,0.04);
  border: 2px solid rgba(255,255,255,0.15);
  border-radius: 12px;
  padding: 24px;
  cursor: pointer;
  transition: all 0.3s ease;
  overflow: hidden;
}

.membership-option::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, rgba(96,165,250,0.5), rgba(168,85,247,0.5));
  opacity: 0;
  transition: opacity 0.3s ease;
}

.membership-option:hover {
  background: rgba(255,255,255,0.06);
  border-color: rgba(96,165,250,0.4);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

.membership-option:hover::before {
  opacity: 1;
}

.membership-option.selected {
  background: rgba(61,240,194,0.08);
  border-color: rgba(61,240,194,0.5);
  box-shadow: 0 8px 32px rgba(61,240,194,0.2);
}

.membership-option.selected::before {
  background: linear-gradient(90deg, #3DF0C2, #2BC9A0);
  opacity: 1;
}

.membership-option input[type="radio"] {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.option-content {
  position: relative;
  z-index: 1;
}

.option-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  gap: 12px;
}

.option-badge {
  display: inline-block;
  padding: 6px 14px;
  background: rgba(96,165,250,0.2);
  color: #60a5fa;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 700;
  border: 1px solid rgba(96,165,250,0.3);
}

.membership-option.selected .option-badge {
  background: rgba(61,240,194,0.2);
  color: #3DF0C2;
  border-color: rgba(61,240,194,0.4);
}

.current-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: rgba(61,240,194,0.15);
  color: #3DF0C2;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  border: 1px solid rgba(61,240,194,0.3);
}

.option-details {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 12px;
  gap: 16px;
}

.option-price {
  color: #fff;
  font-size: 2rem;
  font-weight: 900;
  line-height: 1;
}

.membership-option.selected .option-price {
  color: #3DF0C2;
  text-shadow: 0 2px 8px rgba(61,240,194,0.3);
}

.option-duration {
  display: flex;
  align-items: center;
  gap: 6px;
  color: rgba(255,255,255,0.7);
  font-size: 0.95rem;
  font-weight: 600;
}

.option-duration i {
  font-size: 0.9rem;
}

.option-description {
  color: rgba(255,255,255,0.6);
  font-size: 0.9rem;
  line-height: 1.5;
  margin-top: 8px;
}

.option-checkmark {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 28px;
  height: 28px;
  background: rgba(255,255,255,0.1);
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: scale(0.8);
  transition: all 0.3s ease;
}

.option-checkmark i {
  color: #fff;
  font-size: 0.9rem;
}

.membership-option.selected .option-checkmark {
  opacity: 1;
  transform: scale(1);
  background: #3DF0C2;
  border-color: #3DF0C2;
}

.membership-option.selected .option-checkmark i {
  color: #000;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 16px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
}

.btn-cancel,
.btn-renew {
  padding: 16px 36px;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1.05rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  transition: all 0.3s ease;
  text-decoration: none;
  white-space: nowrap;
  min-width: 160px;
}

.btn-cancel {
  background: rgba(255,255,255,0.08);
  color: #fff;
  border: 2px solid rgba(255,255,255,0.2);
}

.btn-cancel:hover {
  background: rgba(255,255,255,0.14);
  border-color: rgba(255,255,255,0.3);
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

.btn-renew {
  background: linear-gradient(135deg, #3DF0C2, #2BC9A0);
  color: #000;
  box-shadow: 0 4px 20px rgba(61,240,194,0.4);
  border: 2px solid transparent;
}

.btn-renew:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(61,240,194,0.6);
  background: linear-gradient(135deg, #4FFFD4, #3DD9B2);
}

.btn-renew:active,
.btn-cancel:active {
  transform: translateY(0);
}

/* Responsive Design */

/* Tablets */
@media (max-width: 992px) {
  .renew-container {
    padding: 0 16px 32px;
  }

  .renew-card {
    padding: 32px 24px;
  }

  .renew-header h2 {
    font-size: 1.7rem;
  }

  .info-grid {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
  }

  .info-item p {
    font-size: 1.05rem;
  }

  .price-highlight {
    font-size: 1.6rem !important;
  }

  .membership-selector-section {
    padding: 24px;
  }

  .selector-title {
    font-size: 1.3rem;
  }

  .membership-options {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  }
}

/* Mobile Large */
@media (max-width: 768px) {
  .renew-container {
    padding: 0 12px 24px;
  }

  .renew-card {
    padding: 24px 20px;
    border-radius: 12px;
  }

  .renew-header {
    margin-bottom: 28px;
    padding-bottom: 24px;
  }

  .renew-icon {
    width: 75px;
    height: 75px;
    font-size: 2.3rem;
    margin-bottom: 20px;
  }

  .renew-header h2 {
    font-size: 1.5rem;
  }

  .info-grid {
    grid-template-columns: 1fr;
    gap: 14px;
    margin-bottom: 24px;
  }

  .info-item {
    padding: 18px;
  }

  .info-item label {
    font-size: 0.75rem;
    margin-bottom: 8px;
  }

  .info-item p {
    font-size: 1rem;
  }

  .badge-type {
    padding: 6px 14px;
    font-size: 0.9rem;
  }

  .price-highlight {
    font-size: 1.5rem !important;
  }

  .badge-expired,
  .badge-active-status {
    padding: 8px 16px;
    font-size: 0.95rem;
    gap: 8px;
  }

  .days-info {
    display: block;
    margin-left: 0;
    margin-top: 8px;
    font-size: 0.9rem;
  }

  .membership-selector-section {
    padding: 20px;
    margin-bottom: 24px;
  }

  .selector-title {
    font-size: 1.2rem;
    gap: 10px;
  }

  .selector-title i {
    font-size: 1.1rem;
  }

  .selector-subtitle {
    font-size: 0.9rem;
    margin-bottom: 20px;
  }

  .membership-options {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .membership-option {
    padding: 20px;
  }

  .option-header {
    margin-bottom: 14px;
  }

  .option-badge {
    padding: 5px 12px;
    font-size: 0.85rem;
  }

  .current-badge {
    padding: 3px 8px;
    font-size: 0.75rem;
    gap: 5px;
  }

  .option-price {
    font-size: 1.6rem;
  }

  .option-duration {
    font-size: 0.88rem;
  }

  .option-description {
    font-size: 0.85rem;
  }

  .option-checkmark {
    width: 24px;
    height: 24px;
    top: 16px;
    right: 16px;
  }

  .option-checkmark i {
    font-size: 0.8rem;
  }

  .renew-info-box {
    padding: 18px;
    gap: 14px;
    border-radius: 12px;
    margin-bottom: 24px;
  }

  .renew-info-box i {
    font-size: 1.5rem;
  }

  .renew-info-box strong {
    font-size: 1rem;
    margin-bottom: 8px;
  }

  .renew-info-box p {
    font-size: 0.92rem;
    line-height: 1.6;
  }

  .form-actions {
    flex-direction: column;
    gap: 12px;
    padding-top: 16px;
  }

  .btn-cancel,
  .btn-renew {
    width: 100%;
    padding: 14px 24px;
    font-size: 1rem;
    min-width: unset;
  }
}

/* Mobile Small */
@media (max-width: 480px) {
  .renew-container {
    padding: 0 8px 20px;
  }

  .renew-card {
    padding: 20px 16px;
  }

  .renew-icon {
    width: 65px;
    height: 65px;
    font-size: 2rem;
  }

  .renew-header h2 {
    font-size: 1.3rem;
  }

  .info-item {
    padding: 16px;
  }

  .info-item label {
    font-size: 0.7rem;
  }

  .info-item p {
    font-size: 0.95rem;
  }

  .price-highlight {
    font-size: 1.3rem !important;
  }

  .badge-expired,
  .badge-active-status {
    padding: 7px 14px;
    font-size: 0.88rem;
  }

  .membership-selector-section {
    padding: 16px;
  }

  .selector-title {
    font-size: 1.1rem;
    gap: 8px;
  }

  .selector-title i {
    font-size: 1rem;
  }

  .selector-subtitle {
    font-size: 0.85rem;
  }

  .membership-option {
    padding: 16px;
  }

  .option-badge {
    padding: 4px 10px;
    font-size: 0.8rem;
  }

  .current-badge {
    padding: 2px 6px;
    font-size: 0.7rem;
  }

  .option-price {
    font-size: 1.4rem;
  }

  .option-duration {
    font-size: 0.82rem;
  }

  .option-description {
    font-size: 0.8rem;
  }

  .option-checkmark {
    width: 22px;
    height: 22px;
    top: 14px;
    right: 14px;
  }

  .option-checkmark i {
    font-size: 0.75rem;
  }

  .renew-info-box {
    padding: 16px;
    flex-direction: column;
    gap: 12px;
  }

  .renew-info-box i {
    font-size: 1.3rem;
  }

  .renew-info-box strong {
    font-size: 0.95rem;
  }

  .renew-info-box p {
    font-size: 0.88rem;
  }

  .btn-cancel,
  .btn-renew {
    padding: 13px 20px;
    font-size: 0.95rem;
  }
}

/* Landscape Mobile */
@media (max-width: 768px) and (orientation: landscape) {
  .info-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .form-actions {
    flex-direction: row;
  }

  .btn-cancel,
  .btn-renew {
    width: auto;
    flex: 1;
  }
}

/* Print Styles */
@media print {
  .renew-card {
    box-shadow: none;
    border: 1px solid #ccc;
  }

  .form-actions {
    display: none;
  }

  .renew-info-box {
    border: 1px solid #ccc;
  }
}
</style>

<script>
// Referencias a elementos
const btnRenovar = document.getElementById('btnRenovar');
const modalConfirmacion = document.getElementById('modalConfirmacion');
const btnModalCancelar = document.getElementById('btnModalCancelar');
const btnModalConfirmar = document.getElementById('btnModalConfirmar');
const formRenovar = document.getElementById('formRenovar');
const modalOverlay = document.querySelector('.modal-confirmacion-overlay');
const membershipOptions = document.querySelectorAll('.membership-option');
const radioButtons = document.querySelectorAll('input[name="tipo_membresia"]');
const duracionTexto = document.getElementById('duracionTexto');
const modalInfoDuracion = document.querySelector('.modal-confirmacion-info strong');

// Manejar cambio de membresía
radioButtons.forEach(radio => {
  radio.addEventListener('change', function() {
    // Remover clase selected de todas las opciones
    membershipOptions.forEach(option => option.classList.remove('selected'));
    
    // Agregar clase selected a la opción seleccionada
    this.closest('.membership-option').classList.add('selected');
    
    // Actualizar información de duración
    const duracion = parseInt(this.dataset.duracion);
    const duracionText = duracion === 1 ? '1 mes' : `${duracion} meses`;
    duracionTexto.textContent = duracionText;
    
    // Actualizar modal de confirmación
    if (modalInfoDuracion) {
      modalInfoDuracion.textContent = duracionText;
    }
  });
});

// Hacer clic en la opción también selecciona el radio
membershipOptions.forEach(option => {
  option.addEventListener('click', function(e) {
    if (e.target.type !== 'radio') {
      const radio = this.querySelector('input[type="radio"]');
      if (radio) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change'));
      }
    }
  });
});

// Mostrar modal al hacer clic en renovar
btnRenovar.addEventListener('click', function() {
  modalConfirmacion.style.display = 'flex';
  document.body.style.overflow = 'hidden';
});

// Cerrar modal al hacer clic en cancelar
btnModalCancelar.addEventListener('click', function() {
  cerrarModal();
});

// Cerrar modal al hacer clic en el overlay
modalOverlay.addEventListener('click', function() {
  cerrarModal();
});

// Confirmar y enviar formulario
btnModalConfirmar.addEventListener('click', function() {
  formRenovar.submit();
});

// Función para cerrar el modal
function cerrarModal() {
  modalConfirmacion.style.display = 'none';
  document.body.style.overflow = '';
}

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && modalConfirmacion.style.display === 'flex') {
    cerrarModal();
  }
});
</script>

<?php include __DIR__ . '/../shared/footer_admin.php'; ?>
