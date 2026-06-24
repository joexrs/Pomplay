<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
// Obtener locales con información de propietarios y membresías
$stmtLocales = $pdo->query("CALL GetAdminLocalesWithMemberships()");
$locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
$stmtLocales->closeCursor();
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Gestión de Locales</h1>
      <p class="page-subtitle">Administra los locales y sus membresías</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Gestión</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Locales</span>
      </div>
    </div>
  </div>

  <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">
    <?php if (isset($_SESSION['success'])): ?>
      <div style="background:rgba(61,240,194,.1);border:1px solid rgba(61,240,194,.3);color:#3DF0C2;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div style="background:rgba(238,62,70,.1);border:1px solid rgba(238,62,70,.3);color:#EE3E46;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
      <div style="background:rgba(255,193,7,.1);border:1px solid rgba(255,193,7,.3);color:#FFC107;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['info']) ?></span>
      </div>
      <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
      <a href="<?= $baseUrl ?>/admin/locales/add.php" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--dk-accent);color:#fff;border:none;border-radius:6px;text-decoration:none;font-weight:600;">
        <i class="fas fa-plus"></i> Nuevo Local
      </a>
    </div>

    <?php if (empty($locales)): ?>
      <div class="empty-state" style="text-align:center;padding:60px 20px;">
        <i class="fas fa-store" style="font-size:4rem;color:var(--dk-muted);margin-bottom:20px;"></i>
        <p style="font-size:1.1rem;color:var(--dk-muted);">No hay locales registrados aún.</p>
        <a href="<?= $baseUrl ?>/admin/locales/add.php" style="color:var(--dk-accent);text-decoration:none;font-weight:600;margin-top:10px;display:inline-block;">
          Registrar primer local
        </a>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table class="adm-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Camara</th>
              <th>Propietario</th>
              <th>Email</th>
              <th>Membresía</th>
              <th>Estado</th>
              <th>Vencimiento</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($locales as $local): ?>
            <tr>
              <td data-label="ID"><span class="td-id"><?= $local['id_local'] ?></span></td>
              <td data-label="Nombre Local" class="td-description"><?= htmlspecialchars($local['nombre_local']) ?></td>
              <td data-label="Propietario"><?= htmlspecialchars(($local['nombres'] ?? '') . ' ' . ($local['apellidos'] ?? '')) ?: 'Sin asignar' ?></td>
              <td data-label="Email"><?= htmlspecialchars($local['propietario_email'] ?? '-') ?></td>
              <td data-label="Membresía">
                <span class="badge-active"><?= htmlspecialchars($local['tipo_membresia'] ?? 'Sin membresía') ?></span>
              </td>
              <td data-label="Estado">
                <?php if (empty($local['tipo_membresia'])): ?>
                  <span class="badge-inactive">Sin membresía</span>
                <?php elseif ($local['estado_membresia'] == 'ACTIVA'): ?>
                  <span class="badge-active">ACTIVA</span>
                <?php else: ?>
                  <span class="badge-inactive">VENCIDA</span>
                <?php endif; ?>
              </td>
              <td data-label="Vencimiento" class="td-date">
                <?= $local['fecha_vencimiento'] ? date('d/m/Y', strtotime($local['fecha_vencimiento'])) : '-' ?>
              </td>
              <td data-label="Acciones">
                <div class="table-actions">
                  <a href="<?= $baseUrl ?>/admin/locales/edit.php?id=<?= $local['id_local'] ?>" class="btn-icon btn-icon-edit" title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="../membresias/renew.php?id=<?= $local['id_local'] ?>" class="btn-icon" style="background:rgba(238,62,70,.1);color:#EE3E46;" title="Renovar Membresía Vencida">
                      <i class="fas fa-sync-alt"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include '../shared/footer_admin.php'; ?>
