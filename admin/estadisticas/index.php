<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>

<?php
require_once __DIR__ . '/../../bootstrap/autoload.php';

use App\Repositories\EstadisticasRepository;

$mesSeleccionado = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesSeleccionado)) {
    $mesSeleccionado = date('Y-m');
}

$estadisticas = new EstadisticasRepository($pdo);

$totalBusquedas   = $estadisticas->totalBusquedasMes($mesSeleccionado);
$totalClips       = $estadisticas->totalClipsMes($mesSeleccionado);
$totalGrabaciones = $estadisticas->totalGrabacionesMes($mesSeleccionado);

$busquedasPorCancha   = $estadisticas->busquedasPorCancha($mesSeleccionado);
$clipsPorCancha       = $estadisticas->clipsPorCancha($mesSeleccionado);
$grabacionesPorCancha = $estadisticas->grabacionesPorCancha($mesSeleccionado);

$mesesNombres = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
];
[$anio, $mesNum] = explode('-', $mesSeleccionado);
$mesLabel = ($mesesNombres[$mesNum] ?? $mesNum) . ' ' . $anio;

/**
 * @param list<array<string, mixed>> $items
 */
function renderCanchaRanking(array $items, string $emptyIcon, string $emptyText): void
{
    if (empty($items)) {
        echo '<div class="empty-state"><i class="bi ' . htmlspecialchars($emptyIcon) . '"></i><p>' . htmlspecialchars($emptyText) . '</p></div>';
        return;
    }

    $max = max(array_column($items, 'total'));
    if ($max <= 0) {
        $max = 1;
    }

    foreach ($items as $row) {
        $total = (int) ($row['total'] ?? 0);
        $pct   = round(($total / $max) * 100);
        $local = (string) ($row['nombre_local'] ?? 'Sin local');
        $cancha = (string) ($row['cancha_nombre'] ?? 'Sin cancha');
        $codigo = (string) ($row['codigo_cancha'] ?? '');
        ?>
        <div class="cancha-item">
          <div class="cancha-info">
            <div>
              <span class="cancha-local"><?= htmlspecialchars($local) ?></span>
              <span class="cancha-name"><?= htmlspecialchars($cancha) ?><?= $codigo !== '' && $codigo !== '—' ? ' (' . htmlspecialchars($codigo) . ')' : '' ?></span>
            </div>
            <span class="cancha-count"><?= $total ?></span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $pct ?>%"></div>
          </div>
        </div>
        <?php
    }
}
?>

<main class="app-content">

  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Estadísticas</h1>
      <p class="page-subtitle">Métricas de uso por local y cancha — <?= htmlspecialchars($mesLabel) ?></p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Contenido</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Estadísticas</span>
      </div>
    </div>
  </div>

  <!-- Filtro por mes -->
  <div class="adm-form-card" style="margin-bottom: 20px; padding: 20px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: end;">
      <div style="min-width: 220px;">
        <label class="adm-label" for="filter_mes">Mes</label>
        <input type="month" name="mes" id="filter_mes" class="adm-input"
               value="<?= htmlspecialchars($mesSeleccionado) ?>" max="<?= date('Y-m') ?>">
      </div>
      <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn-adm-save">
          <i class="bi bi-funnel"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/admin/estadisticas/index.php" class="btn-adm-cancel"
           style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 16px; text-decoration: none;">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- Totales del mes -->
  <div class="dash-stats">
    <div class="dash-stat-card" style="cursor: default;">
      <div class="dash-stat-icon"><i class="bi bi-search"></i></div>
      <div class="dash-stat-info">
        <h3><?= $totalBusquedas ?></h3>
        <p>Búsquedas</p>
      </div>
    </div>
    <div class="dash-stat-card" style="cursor: default;">
      <div class="dash-stat-icon"><i class="bi bi-scissors"></i></div>
      <div class="dash-stat-info">
        <h3><?= $totalClips ?></h3>
        <p>Clips generados</p>
      </div>
    </div>
    <div class="dash-stat-card" style="cursor: default;">
      <div class="dash-stat-icon"><i class="bi bi-camera-video"></i></div>
      <div class="dash-stat-info">
        <h3><?= $totalGrabaciones ?></h3>
        <p>Grabaciones</p>
      </div>
    </div>
  </div>

  <!-- Ranking por cancha (mismo diseño que Canchas destacadas) -->
  <div class="dashboard-grid estadisticas-grid">

    <div class="top-canchas-card">
      <div class="card-header">
        <h3 class="card-title">Búsquedas</h3>
        <div class="card-subtitle">Por local y cancha · <?= htmlspecialchars($mesLabel) ?></div>
      </div>
      <div class="canchas-list">
        <?php renderCanchaRanking(
            $busquedasPorCancha,
            'bi-search',
            'Sin búsquedas registradas este mes'
        ); ?>
      </div>
    </div>

    <div class="top-canchas-card">
      <div class="card-header">
        <h3 class="card-title">Clips generados</h3>
        <div class="card-subtitle">Por local y cancha · <?= htmlspecialchars($mesLabel) ?></div>
      </div>
      <div class="canchas-list">
        <?php renderCanchaRanking(
            $clipsPorCancha,
            'bi-scissors',
            'Sin clips generados este mes'
        ); ?>
      </div>
    </div>

    <div class="top-canchas-card">
      <div class="card-header">
        <h3 class="card-title">Grabaciones</h3>
        <div class="card-subtitle">Por local y cancha · <?= htmlspecialchars($mesLabel) ?></div>
      </div>
      <div class="canchas-list">
        <?php renderCanchaRanking(
            $grabacionesPorCancha,
            'bi-camera-video',
            'Sin grabaciones este mes'
        ); ?>
      </div>
    </div>

  </div>

</main>

<?php include '../shared/footer_admin.php'; ?>
