<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
$localFilter = $_GET['local'] ?? null;
$stmt = $pdo->prepare("CALL GetAdminCanchas(:id_local)");
$stmt->bindValue(':id_local', $localFilter ?: null, $localFilter ? PDO::PARAM_INT : PDO::PARAM_NULL);
$stmt->execute();
$canchas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();
?>

<main class="app-content">
<div class="page-container">
  <?php if (!empty($_SESSION['success'])): ?>
    <div role="status" style="background:#166534;color:#fff;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
      <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
    <div role="alert" style="background:#7f1d1d;color:#fff;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
      <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>
  <?php $_SESSION['court_delete_csrf'] ??= bin2hex(random_bytes(32)); ?>

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Canchas</h1>
      <p class="page-subtitle">Gestión de canchas deportivas por local</p>
    </div>
    <div class="page-header__actions">
      <a href="<?= $baseUrl ?>/admin/canchas/add.php" class="btn-adm-add">
        <i class="bi bi-plus-circle"></i> Nueva Cancha
      </a>
    </div>
  </div>

  <!-- Filter by Local -->
  <div class="adm-form-card" style="margin-bottom: 20px; padding: 20px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: flex-end;">
      <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; color: var(--dk-muted);">Filtrar por Local</label>
        <select name="local" class="form-control" style="width: 100%;">
          <option value="">Todos los locales</option>
          <?php
          $stmtLocales = $pdo->prepare("CALL GetLocalesPaginadoSimple(:p_limit, :p_offset)");
          $stmtLocales->execute([':p_limit' => 200, ':p_offset' => 0]);
          $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
          $stmtLocales->closeCursor();
          foreach ($locales as $loc):
          ?>
            <option value="<?= $loc['id_local'] ?>" <?= $localFilter == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--dk-accent);color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">
          <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/admin/canchas/index.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:rgba(255,255,255,.1);color:#fff;border:none;border-radius:6px;text-decoration:none;font-weight:600;margin-left:8px;">
          <i class="fas fa-times"></i> Limpiar
        </a>
      </div>
    </form>
  </div>

  <!-- Table Card -->
  <div class="videos-table-card"> 
      <table class="adm-table" id="canchasTable">
        <thead>
          <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Local</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($canchas as $row):
            $local = htmlspecialchars($row['nombre_local'] ?? 'Sin local');
          ?>
          <tr>
            <td data-label="Código"><span class="td-id"><?= htmlspecialchars($row['codigo_cancha']) ?></span></td>
            <td data-label="Descripción" class="td-description"><?= htmlspecialchars($row['descripcion']) ?></td>
            <td data-label="Local"><span class="badge-active"><?= $local ?></span></td>
            <td data-label="Acciones">
              <div class="table-actions">
                <a href="<?= $baseUrl ?>/admin/canchas/edit.php?id_cancha=<?= $row['id_cancha'] ?>"
                   class="btn-icon btn-icon-edit" title="Editar">
                   <i class="bi bi-pencil"></i>
                </a>
                <form action="<?= $baseUrl ?>/admin/canchas/procesos/eliminar_cancha.php" method="POST"
                      class="court-delete-form" style="display:inline">
                  <input type="hidden" name="id_cancha" value="<?= (int) $row['id_cancha'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['court_delete_csrf'], ENT_QUOTES) ?>">
                  <button type="button" class="btn-icon btn-icon-delete js-confirm-court-delete" title="Eliminar cancha y videos"
                          aria-label="Eliminar cancha <?= htmlspecialchars($row['codigo_cancha'], ENT_QUOTES) ?> y sus videos">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    
  </div>

  </div>

  <dialog id="court-delete-modal" class="court-delete-modal"
          aria-labelledby="court-delete-title" aria-describedby="court-delete-description">
  <div class="court-delete-modal__icon" aria-hidden="true">
    <i class="bi bi-trash3"></i>
  </div>
  <h2 id="court-delete-title">Eliminar cancha</h2>
  <p id="court-delete-description">
    Se eliminarán permanentemente esta cancha, todos sus videos y la carpeta de grabaciones del VPS.
    ¿Deseas continuar?
  </p>
  <div class="court-delete-modal__actions">
    <button type="button" class="court-delete-modal__cancel" id="cancel-court-delete">Cancelar</button>
    <button type="button" class="court-delete-modal__confirm" id="confirm-court-delete">
      <i class="bi bi-trash3" aria-hidden="true"></i> Sí, eliminar
    </button>
  </div>
</dialog>

<?php include '../shared/footer_admin.php'; ?>

<style>
  .court-delete-modal {
    position: fixed;
    inset: 50% auto auto 50%;
    transform: translate(-50%, -50%);
    margin: 0;
    width: min(440px, calc(100% - 32px));
    padding: 28px;
    color: #fff;
    background: #1a1d2e;
    border: 1px solid rgba(238, 62, 70, .3);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
  }

  .court-delete-modal::backdrop {
    background: rgba(0, 0, 0, .65);
    backdrop-filter: blur(4px);
  }

  .court-delete-modal__icon {
    display: grid;
    width: 56px;
    height: 56px;
    margin-bottom: 16px;
    place-items: center;
    color: #ee3e46;
    background: rgba(238, 62, 70, .15);
    border: 1px solid rgba(238, 62, 70, .3);
    border-radius: 50%;
    font-size: 1.4rem;
  }

  .court-delete-modal h2 {
    margin: 0 0 10px;
    color: #fff;
    font-size: 1.2rem;
  }

  .court-delete-modal p {
    margin: 0;
    color: rgba(255, 255, 255, .72);
    line-height: 1.55;
  }

  .court-delete-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
  }

  .court-delete-modal__actions button {
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    font: inherit;
    font-weight: 600;
  }

  .court-delete-modal__cancel {
    color: rgba(255, 255, 255, .8);
    background: rgba(255, 255, 255, .06);
    border: 1px solid rgba(255, 255, 255, .16);
  }

  .court-delete-modal__confirm {
    color: #fff;
    background: #c92f38;
    border: 1px solid #c92f38;
  }

  .court-delete-modal__confirm:hover {
    background: #a9232c;
  }

  @media (max-width: 480px) {
    .court-delete-modal__actions {
      flex-direction: column-reverse;
    }

    .court-delete-modal__actions button {
      width: 100%;
    }
  }
</style>

<script>
  (function () {
    const modal = document.getElementById('court-delete-modal');
    const confirmButton = document.getElementById('confirm-court-delete');
    let activeForm = null;

    document.querySelectorAll('.js-confirm-court-delete').forEach(function (button) {
      button.addEventListener('click', function () {
        activeForm = button.closest('.court-delete-form');
        if (activeForm) {
          modal.showModal();
        }
      });
    });

    document.getElementById('cancel-court-delete').addEventListener('click', function () {
      modal.close();
      activeForm = null;
    });

    confirmButton.addEventListener('click', function () {
      if (activeForm) {
        activeForm.requestSubmit();
      }
    });

    modal.addEventListener('close', function () {
      activeForm = null;
    });
  })();
</script>

<script>
$(document).ready(function () {
  $('#canchasTable').DataTable({
    language: {
      decimal: ',',
      thousands: '.',
      emptyTable: 'No hay canchas disponibles',
      info: 'Mostrando _START_ a _END_ de _TOTAL_ canchas',
      infoEmpty: 'Mostrando 0 a 0 de 0 canchas',
      infoFiltered: '(filtrado de _MAX_ canchas en total)',
      lengthMenu: 'Mostrar _MENU_ canchas',
      loadingRecords: 'Cargando...',
      processing: 'Procesando...',
      search: 'Buscar:',
      zeroRecords: 'No se encontraron canchas',
      paginate: {
        first: 'Primera',
        last: 'Última',
        next: 'Siguiente',
        previous: 'Anterior'
      },
      aria: {
        orderable: 'Ordenar por esta columna',
        orderableReverse: 'Invertir el orden de esta columna'
      }
    },
    pageLength: 25,
    lengthChange: false,
    searching: false,
    ordering: true,
    info: false,
    paging: false,
    autoWidth: false,
    responsive: true
  });
});
</script>