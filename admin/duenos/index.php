<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
// Obtener usuarios con rol de dueno y los datos basicos del propietario.
$stmtOwners = $pdo->query("CALL GetAdminOwners()");
$owners = $stmtOwners->fetchAll(PDO::FETCH_ASSOC);
$stmtOwners->closeCursor();
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Gestion de Usuarios</h1>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Gestion</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Usuarios</span>
      </div>
    </div>
  </div>


    <?php if (empty($owners)): ?>
      <div class="empty-state" style="text-align:center;padding:60px 20px;">
        <i class="fas fa-user-tie" style="font-size:4rem;color:var(--dk-muted);margin-bottom:20px;"></i>
        <p style="font-size:1.1rem;color:var(--dk-muted);">No hay usuarios registrados aun.</p>
        <p style="color:var(--dk-muted);margin-top:10px;">Los usuarios  se crean al registrar un nuevo local.</p>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table class="adm-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Propietario</th>
              <th>Numero</th>
              <th>Email</th>
              <th>Usuario</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($owners as $owner): ?>
            <?php $ownerName = trim(($owner['nombres'] ?? '') . ' ' . ($owner['apellidos'] ?? '')); ?>
            <tr>
              <td data-label="ID"><span class="td-id"><?= $owner['id_usuario'] ?></span></td>
              <td data-label="Propietario" class="td-description"><?= htmlspecialchars($ownerName ?: '-') ?></td>
              <td data-label="Número"><?= htmlspecialchars($owner['telefono'] ?? '-') ?></td>
              <td data-label="Email"><?= htmlspecialchars($owner['email'] ?? '-') ?></td>
              <td data-label="Usuario"><?= htmlspecialchars($owner['usuario'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include '../shared/footer_admin.php'; ?>
