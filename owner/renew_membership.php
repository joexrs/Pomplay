<?php include __DIR__ . '/../admin/shared/header_admin.php'; ?>
<?php include __DIR__ . '/../conexion.php'; ?>

<!-- Owner Renew Membership CSS -->
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-renew-membership.css">

<?php
$userId = $_SESSION['user_id'] ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;
$localId = isset($_GET['local_id']) ? (int)$_GET['local_id'] : ($_SESSION['id_local'] ?? null);

if (!$userId || !$propietarioId) {
    die('Acceso denegado');
}

// Verificar que el local pertenezca al propietario
try {
    $stmtVerify = $pdo->prepare("SELECT id_local, nombre_local FROM locales WHERE id_local = :id_local AND id_propietario = :id_propietario");
    $stmtVerify->execute([':id_local' => $localId, ':id_propietario' => $propietarioId]);
    $localInfo = $stmtVerify->fetch(PDO::FETCH_ASSOC);
    
    if (!$localInfo) {
        die('Local no encontrado o no tienes permisos para acceder a él');
    }
} catch (PDOException $e) {
    die('Error al verificar el local: ' . $e->getMessage());
}

// Obtener precios desde la base de datos
try {
    $stmt = $pdo->query("SELECT * FROM precios_membresias WHERE activo = 1 ORDER BY duracion_meses ASC");
    $planes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $planes = [];
    $error = 'Error al cargar los planes: ' . $e->getMessage();
}

// Iconos y colores para cada tipo
$iconos = [
    'BASICA' => ['icon' => 'fa-star', 'color' => '#60a5fa'],
    'PREMIUM' => ['icon' => 'fa-gem', 'color' => '#A855F7'],
    'ANUAL' => ['icon' => 'fa-crown', 'color' => '#ec4237']
];
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Renovar Membresía</h1>
      <p class="page-subtitle">Local: <strong><?= htmlspecialchars($localInfo['nombre_local']) ?></strong></p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Membresía</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Renovar</span>
      </div>
    </div>
  </div>

  <?php if (isset($error)): ?>
    <div class="renew-error-alert">
      <i class="fas fa-exclamation-circle renew-error-icon"></i><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div class="adm-form-wrap renew-form-wrapper">
    

    <?php if (empty($planes)): ?>
      <div class="renew-empty-state">
        <i class="fas fa-exclamation-triangle renew-empty-icon"></i>
        <p class="renew-empty-text">No hay planes disponibles en este momento.</p>
      </div>
    <?php else: ?>
      <div class="adm-form">
        <div class="renew-plans-grid">
          <?php 
          $firstPlan = true;
          foreach ($planes as $plan): 
            $icono = $iconos[$plan['tipo_membresia']] ?? ['icon' => 'fa-tag', 'color' => '#888'];
          ?>
            <label class="membership-plan-owner">
              <input 
                type="radio" 
                name="tipo_membresia" 
                value="<?= htmlspecialchars($plan['tipo_membresia']) ?>" 
                data-monto="<?= number_format($plan['precio'], 2) ?>"
                data-nombre="<?= htmlspecialchars($plan['nombre_display']) ?>"
                <?= $firstPlan ? 'checked' : '' ?>>
              <div class="plan-card-owner" style="--plan-color: <?= $icono['color'] ?>;">
                <div class="plan-icon">
                  <i class="fas <?= $icono['icon'] ?>"></i>
                </div>
                <h3 class="plan-title"><?= htmlspecialchars($plan['nombre_display']) ?></h3>
                <p class="plan-duration"><?= $plan['duracion_meses'] ?> <?= $plan['duracion_meses'] == 1 ? 'mes' : 'meses' ?></p>
                <p class="plan-price">S/ <?= number_format($plan['precio'], 2) ?></p>
              </div>
            </label>
          <?php 
            $firstPlan = false;
          endforeach; 
          ?>
        </div>

        <div class="form-actions renew-form-actions">
          <a href="membership.php" class="btn-secondary renew-btn-cancel">
            <i class="fas fa-times"></i> Cancelar
          </a>
          <button type="button" id="btnConfirmarRenovacion" class="btn-primary renew-btn-continue">
            <i class="fas fa-check"></i> Continuar con el Pago
          </button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Modal de Pago -->
<div id="modalPago" class="modal-pago-overlay">
  <div class="modal-pago-content">
    
    <button id="btnCerrarModal" class="modal-close-btn">
      <i class="fas fa-times"></i>
    </button>

    <div class="modal-grid">
      <!-- Columna Izquierda: QR y Título -->
      <div class="modal-left">
        <div class="modal-header-compact">
          <div class="modal-icon-small">
            <i class="fas fa-qrcode"></i>
          </div>
          <div>
            <h2>Completa tu Pago</h2>
            <p id="montoSeleccionado" class="monto-text"></p>
          </div>
        </div>
        
        <div class="qr-container">
          <img src="<?= $baseUrl ?>/public/img/qr.png" alt="Código QR de Pago">
          <p class="qr-hint">Escanea con tu app de pago</p>
        </div>
      </div>

      <!-- Columna Derecha: Pasos -->
      <div class="modal-right">
        <h3 class="pasos-title">Pasos para completar</h3>
        
        <div class="pasos-list">
          <div class="paso-item-modern">
            <div class="paso-numero-modern">1</div>
            <div class="paso-content-modern">
              <h4>Escanea el QR</h4>
              <p>Usa tu aplicación de pago preferida</p>
            </div>
          </div>

          <div class="paso-item-modern">
            <div class="paso-numero-modern">2</div>
            <div class="paso-content-modern">
              <h4>Envía el comprobante</h4>
              <p>Contacta al dueño por WhatsApp</p>
              <a href="https://wa.me/51999999999?text=Hola,%20acabo%20de%20realizar%20el%20pago%20de%20mi%20membresía" target="_blank" class="btn-whatsapp-modern">
                <i class="fab fa-whatsapp"></i>
                Enviar Comprobante
              </a>
            </div>
          </div>

          <div class="paso-item-modern">
            <div class="paso-numero-modern">3</div>
            <div class="paso-content-modern">
              <h4>Espera confirmación</h4>
              <p>Tu membresía será activada pronto</p>
            </div>
          </div>
        </div>

        <div class="aviso-info-modern">
          <i class="fas fa-shield-check"></i>
          <p>Tu pago es seguro y será verificado por el administrador</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const btnConfirmar = document.getElementById('btnConfirmarRenovacion');
  const modalPago = document.getElementById('modalPago');
  const btnCerrar = document.getElementById('btnCerrarModal');
  const montoTexto = document.getElementById('montoSeleccionado');

  btnConfirmar.addEventListener('click', function() {
    const planSeleccionado = document.querySelector('input[name="tipo_membresia"]:checked');
    if (planSeleccionado) {
      const monto = planSeleccionado.dataset.monto;
      const nombre = planSeleccionado.dataset.nombre;
      montoTexto.textContent = `Membresía ${nombre} - S/ ${monto}`;
      modalPago.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
  });

  btnCerrar.addEventListener('click', function() {
    modalPago.classList.remove('show');
    document.body.style.overflow = '';
  });

  // Cerrar modal al hacer clic fuera
  modalPago.addEventListener('click', function(e) {
    if (e.target === modalPago) {
      modalPago.classList.remove('show');
      document.body.style.overflow = '';
    }
  });

  // Cerrar con tecla ESC
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modalPago.classList.contains('show')) {
      modalPago.classList.remove('show');
      document.body.style.overflow = '';
    }
  });
});
</script>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>
