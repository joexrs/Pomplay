<?php include __DIR__ . '/../admin/shared/header_admin.php'; ?>
<?php include __DIR__ . '/../conexion.php'; ?>

<!-- Owner Membership CSS -->
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-membership.css">

<style>
/* Hover effects para las cards */
.adm-form-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

/* Responsive grid */
@media (max-width: 768px) {
  .adm-form-wrap > div:first-child {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php
$userId = $_SESSION['user_id'] ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;

if (!$userId || !$propietarioId) {
    die('Acceso denegado');
}

try {
    // Obtener locales del propietario con sus membresías
    $stmtLocales = $pdo->prepare("CALL GetOwnerLocalList(:id_propietario)");
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
} catch (PDOException $e) {
    die('Error al obtener locales: ' . $e->getMessage());
}

if (empty($locales)) {
    die('No tienes locales asignados');
}

// Obtener membresías para cada local
$localesConMembresia = [];
foreach ($locales as $local) {
    try {
        $stmtMembership = $pdo->prepare("CALL GetMembershipByUserLocal(:user_id, :local_id)");
        $stmtMembership->execute([':user_id' => $userId, ':local_id' => $local['id_local']]);
        $membership = $stmtMembership->fetch(PDO::FETCH_ASSOC);
        $stmtMembership->closeCursor();
        
        $isActive = $membership && new DateTime($membership['fecha_vencimiento']) > new DateTime();
        $daysRemaining = 0;
        
        if ($membership && $isActive) {
            $expiry = new DateTime($membership['fecha_vencimiento']);
            $today = new DateTime();
            $daysRemaining = $expiry->diff($today)->days;
        }
        
        $localesConMembresia[] = [
            'local' => $local,
            'membership' => $membership,
            'isActive' => $isActive,
            'daysRemaining' => $daysRemaining
        ];
    } catch (PDOException $e) {
        $localesConMembresia[] = [
            'local' => $local,
            'membership' => null,
            'isActive' => false,
            'daysRemaining' => 0
        ];
    }
}

$success = $_GET['success'] ?? null;
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mis Membresías</h1>
      <p class="page-subtitle">Estado y renovación de tus membresías por local</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Membresías</span>
      </div>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="membership-success-alert">
      <i class="fas fa-check-circle membership-success-icon"></i>
      <span class="membership-success-text">Membresía renovada exitosamente</span>
    </div>
  <?php endif; ?>

  <div class="adm-form-wrap" style="max-width:1200px;">
    <!-- Grid de locales -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px;">
      <?php foreach ($localesConMembresia as $item): 
        $local = $item['local'];
        $membership = $item['membership'];
        $isActive = $item['isActive'];
        $daysRemaining = $item['daysRemaining'];
      ?>
        <!-- Card compacta por cada local -->
        <div class="adm-form-card" style="padding: 0; overflow: hidden; border: 2px solid <?= $membership ? ($isActive ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)') : 'rgba(156,163,175,0.3)' ?>;">
          
          <!-- Header compacto del local -->
          <div style="background: <?= $membership ? ($isActive ? 'linear-gradient(135deg, rgba(34,197,94,0.1) 0%, rgba(16,185,129,0.1) 100%)' : 'linear-gradient(135deg, rgba(239,68,68,0.1) 0%, rgba(220,38,38,0.1) 100%)') : 'linear-gradient(135deg, rgba(156,163,175,0.1) 0%, rgba(107,114,128,0.1) 100%)' ?>; padding: 16px 20px; border-bottom: 1px solid <?= $membership ? ($isActive ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)') : 'rgba(156,163,175,0.2)' ?>;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
              <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                <i class="fas fa-store" style="font-size: 1.2rem; color: <?= $membership ? ($isActive ? '#22c55e' : '#ef4444') : '#9ca3af' ?>; flex-shrink: 0;"></i>
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--dk-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($local['nombre_local']) ?></h3>
              </div>
              <?php if ($membership): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: <?= $isActive ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)' ?>; color: <?= $isActive ? '#22c55e' : '#ef4444' ?>; white-space: nowrap;">
                  <i class="fas fa-<?= $isActive ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                  <?= $isActive ? 'Activa' : 'Vencida' ?>
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Contenido compacto -->
          <div style="padding: 20px;">
            <?php if ($membership): ?>
              <!-- Detalles en grid compacto -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                  <p style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.5px;">Tipo</p>
                  <p style="font-size: 0.9rem; font-weight: 600; color: var(--dk-text); margin: 0; display: flex; align-items: center; gap: 6px;">
                    <?= htmlspecialchars($membership['tipo_membresia']) ?>
                  </p>
                </div>
                <div>
                  <p style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.5px;">Vencimiento</p>
                  <p style="font-size: 0.9rem; font-weight: 600; color: var(--dk-text); margin: 0;">
                    <?= date('d/m/Y', strtotime($membership['fecha_vencimiento'])) ?>
                  </p>
                </div>
              </div>

              <?php if ($isActive): ?>
                <!-- Barra de progreso compacta -->
                <div style="margin-bottom: 16px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">Progreso</span>
                    <span style="font-size: 0.85rem; font-weight: 700; color: #22c55e;"><?= $daysRemaining ?> días</span>
                  </div>
                  <div style="height: 6px; background: rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden;">
                    <div style="height: 100%; background: linear-gradient(90deg, #22c55e 0%, #10b981 100%); width: <?= min(100, ($daysRemaining / 30) * 100) ?>%; transition: width 0.3s ease;"></div>
                  </div>
                  <?php if ($daysRemaining <= 7): ?>
                    <p style="font-size: 0.75rem; color: #f59e0b; margin: 6px 0 0 0; display: flex; align-items: center; gap: 4px;">
                      <i class="fas fa-exclamation-triangle"></i>
                      Próximo a vencer
                    </p>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <!-- Alerta de vencimiento -->
                <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                  <p style="font-size: 0.85rem; color: #ef4444; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Membresía expirada</span>
                  </p>
                </div>
              <?php endif; ?>

              <!-- Botón de acción compacto -->
              <a href="renew_membership.php?local_id=<?= $local['id_local'] ?>" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; text-decoration: none; transition: all 0.2s; background: <?= $isActive ? 'rgba(59,130,246,0.1)' : '#ef4444' ?>; color: <?= $isActive ? '#3b82f6' : '#fff' ?>; border: <?= $isActive ? '1px solid rgba(59,130,246,0.3)' : 'none' ?>;">
                <i class="fas fa-sync-alt"></i>
                <?= $isActive ? 'Extender' : 'Renovar Ahora' ?>
              </a>

            <?php else: ?>
              <!-- Sin membresía -->
              <div style="text-align: center; padding: 20px 0;">
                <i class="fas fa-crown" style="font-size: 2.5rem; color: rgba(255,255,255,0.2); opacity: 0.5; margin-bottom: 12px;"></i>
                <p style="font-size: 0.9rem; color: rgba(255,255,255,0.5); margin: 0;">Sin membresía asignada</p>
                <p style="font-size: 0.8rem; color: rgba(255,255,255,0.4); margin: 8px 0 0 0;">Contacta al administrador</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Botón volver -->
    
  </div>
</main>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>

