<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<style>
  .adm-form-wrap {
    max-width: 920px;
    margin: 0 auto;
    padding: 24px 16px 40px;
  }

  .adm-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 18px;
    color: rgba(255,255,255,.55);
    font-size: .9rem;
  }

  .adm-breadcrumb a {
    color: #ec4237;
    text-decoration: none;
    font-weight: 700;
  }

  .adm-form-heading {
    margin-bottom: 22px;
  }

  .adm-form-heading h1 {
    color: #ffffff;
    font-size: clamp(1.6rem, 4vw, 2.2rem);
    font-weight: 800;
    margin-bottom: 8px;
  }

  .adm-form-heading p {
    color: rgba(255,255,255,.68);
    margin: 0;
  }

  .adm-form-card {
    background: linear-gradient(180deg, rgba(17,19,45,.98), rgba(8,10,30,.98));
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 18px 45px rgba(0,0,0,.28);
  }

  .adm-field {
    margin-bottom: 20px;
  }

  .adm-label {
    display: block;
    color: #ffffff;
    font-weight: 700;
    font-size: .92rem;
    margin-bottom: 8px;
  }

  .adm-input,
  .adm-select {
    width: 100%;
    min-height: 48px;
    background: rgba(255,255,255,.055);
    border: 1px solid rgba(255,255,255,.12);
    color: #ffffff;
    border-radius: 12px;
    padding: 12px 14px;
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
  }

  .adm-input::placeholder {
    color: rgba(255,255,255,.42);
  }

  .adm-select option {
    color: #111;
  }

  .adm-input:focus,
  .adm-select:focus {
    border-color: rgba(236,66,55,.75);
    box-shadow: 0 0 0 4px rgba(236,66,55,.14);
    background: rgba(255,255,255,.075);
  }

  .adm-input[readonly] {
    background: rgba(236,66,55,.08);
    border-color: rgba(236,66,55,.28);
    color: #ffffff;
    font-weight: 800;
    letter-spacing: .04em;
    cursor: not-allowed;
  }

  .adm-help {
    display: block;
    margin-top: 7px;
    color: rgba(255,255,255,.55);
    font-size: .82rem;
    line-height: 1.35;
  }

  .cancha-preview-box {
    width: 160px;
    height: 105px;
    background: #ffffff;
    border-radius: 14px;
    padding: 10px;
    border: 1px solid rgba(236,66,55,.28);
    box-shadow: 0 10px 24px rgba(0,0,0,.20);
    margin-bottom: 12px;
  }

  .cancha-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }

  .adm-divider {
    border: 0;
    border-top: 1px solid rgba(255,255,255,.09);
    margin: 26px 0;
  }

  .adm-form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
  }

  .btn-adm-save,
  .btn-adm-cancel {
    min-height: 46px;
    padding: 12px 18px;
    border-radius: 12px;
    font-weight: 800;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    cursor: pointer;
  }

  .btn-adm-save {
    background: #ec4237;
    color: #ffffff;
    box-shadow: 0 12px 28px rgba(236,66,55,.25);
  }

  .btn-adm-cancel {
    background: rgba(255,255,255,.06);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,.12);
  }

  @media (max-width: 640px) {
    .adm-form-wrap {
      padding: 18px 12px 32px;
    }

    .adm-form-card {
      padding: 20px;
      border-radius: 16px;
    }

    .cancha-preview-box {
      width: 100%;
      height: auto;
      aspect-ratio: 16 / 9;
    }

    .adm-form-actions {
      flex-direction: column;
    }

    .btn-adm-save,
    .btn-adm-cancel {
      width: 100%;
    }
  }
</style>

<main class="app-content">
<?php
if (!isset($_GET['id_cancha']) || !is_numeric($_GET['id_cancha'])) { header("Location: " . $baseUrl . "/admin/canchas/index.php"); exit(); }
$id_cancha = (int) $_GET['id_cancha'];
$stmt = $pdo->prepare("CALL GetAdminCanchaById(:id_cancha)");
$stmt->execute([':id_cancha' => $id_cancha]);
$cancha = $stmt->fetch();
$stmt->closeCursor();
if (!$cancha) { header("Location: " . $baseUrl . "/admin/canchas/index.php"); exit(); }
$stmt_loc = $pdo->prepare("CALL GetLocalesPaginadoSimple(:p_limit, :p_offset)");
$stmt_loc->execute([':p_limit' => 200, ':p_offset' => 0]);
$locales = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);
$stmt_loc->closeCursor();
?>

<div class="adm-form-wrap">

  <div class="adm-form-heading">
    <h1><i class="fas fa-edit" style="color:var(--dk-accent);margin-right:8px;"></i>Editar Cancha</h1>
    <p>Modifique los datos de la cancha seleccionada</p>
  </div>

  <div class="adm-form-card">
    <form action="procesos/procesar_editar.php" method="POST" enctype="multipart/form-data" autocomplete="off" id="formEditCancha">
      <input type="hidden" name="id_cancha" value="<?= htmlspecialchars($cancha['id_cancha']) ?>" />

      <div class="adm-field">
        <label class="adm-label">Código de la cancha</label>
        <input type="text" class="adm-input"
               value="<?= htmlspecialchars($cancha['codigo_cancha']) ?>"
               readonly
               style="opacity:.6;cursor:not-allowed;" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="descripcion">Descripción</label>
        <input type="text" id="descripcion" name="descripcion" class="adm-input"
               value="<?= htmlspecialchars($cancha['descripcion']) ?>" 
               required maxlength="70" minlength="3" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="tipo_cancha">Tipo de cancha</label>
        <input type="text" id="tipo_cancha" name="tipo_cancha" class="adm-input"
              value="<?= htmlspecialchars($cancha['tipo_cancha'] ?? '') ?>"
              placeholder="Ej: Fútbol 7, vóley, sintética"
              maxlength="100" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="ubicacion">Ubicación</label>
        <input type="text" id="ubicacion" name="ubicacion" class="adm-input"
              value="<?= htmlspecialchars($cancha['ubicacion'] ?? '') ?>"
              placeholder="Ej: Primer piso, zona norte, cancha techada"
              maxlength="150" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="imagen_cancha">Imagen de la cancha</label>

        <?php
          $imagenActual = !empty($cancha['imagen_url'])
            ? $cancha['imagen_url']
            : $baseUrl . '/public/images/pomplay logo.png';
        ?>

        <div class="cancha-preview-box">
          <img 
            src="<?= htmlspecialchars($imagenActual) ?>"
            alt="<?= htmlspecialchars($cancha['descripcion']) ?>"
          >
        </div>

        <input type="file" id="imagen_cancha" name="imagen_cancha"
              class="adm-input" accept="image/jpeg,image/png,image/webp,image/jpg" />

        <small class="adm-help">
          Si no subes una nueva imagen, se mantendrá la actual.
        </small>  
      </div>

      <div class="adm-field">
        <label class="adm-label" for="id_local">Local</label>
        <select id="id_local" name="id_local" class="adm-select" required>
          <option value="">Seleccione un local</option>
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>"
              <?= ($cancha['id_local'] == $loc['id_local']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <hr class="adm-divider">
      <div class="adm-form-actions">
        <button type="submit" class="btn-adm-save">
          <i class="fas fa-check"></i> Actualizar Cancha
        </button>
        <a href="<?= $baseUrl ?>/admin/canchas/index.php" class="btn-adm-cancel">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
</div>

<script src="/js/jquery-3.7.0.min.js"></script>
<script src="/js/bootstrap.min.js"></script>
<script src="/js/main.js"></script>
<script>
document.getElementById('formEditCancha').addEventListener('submit', function(e) {
    const descripcion = document.getElementById('descripcion').value.trim();
    const local = document.getElementById('id_local').value;
    const imagen = document.getElementById('imagen_cancha').files[0];

    if (!descripcion || !local) {
        e.preventDefault();
        alert('Por favor complete todos los campos obligatorios');
        return false;
    }

    if (imagen) {
        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/jpg'
        ];

        if (!allowedTypes.includes(imagen.type)) {
            e.preventDefault();
            alert('La imagen debe ser JPG, PNG o WEBP');
            return false;
        }

        if (imagen.size > 3 * 1024 * 1024) {
            e.preventDefault();
            alert('La imagen no puede pesar más de 3MB');
            return false;
        }
    }
});
</script>
</main>
<?php include '../shared/footer_admin.php'; ?>