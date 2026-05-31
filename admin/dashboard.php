<?php include 'shared/header_admin.php'; ?>
<?php include '../conexion.php'; ?>

<?php
// Stats del sistema
$stmtStats = $pdo->query("CALL GetAdminDashboardStats()");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];
$stmtStats->closeCursor();
$totalVideos  = (int) ($stats['total_videos'] ?? 0);
$totalCanchas = (int) ($stats['total_canchas'] ?? 0);
$totalAdmins  = (int) ($stats['total_admins'] ?? 0);
$totalLocales = (int) ($stats['total_locales'] ?? 0);
$totalOwners  = (int) ($stats['total_owners'] ?? 0);

// Últimos 6 videos
$stmtRecent = $pdo->prepare("CALL GetAdminRecentVideos(:limit_val)");
$stmtRecent->execute([':limit_val' => 6]);
$recentVideos = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
$stmtRecent->closeCursor();

// Videos por mes del año actual (para mini gráfico)
$stmtMonth = $pdo->query("CALL GetAdminVideosByMonth()");
$monthData = $stmtMonth->fetchAll(PDO::FETCH_ASSOC);
$stmtMonth->closeCursor();
$mesesNombre = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$chartMonths = [];
$chartValues = [];
foreach ($monthData as $m) {
    $chartMonths[] = $mesesNombre[(int)$m['mes']];
    $chartValues[] = (int)$m['total'];
}

// Canchas con más videos
$stmtTop = $pdo->prepare("CALL GetAdminTopCanchas(:limit_val)");
$stmtTop->execute([':limit_val' => 5]);
$topCanchas = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
$stmtTop->closeCursor();
$maxTop = !empty($topCanchas) ? max(array_column($topCanchas, 'total')) : 1;

$meses = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
?>

<main class="app-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Dashboard</h1>
      <p class="page-subtitle">Panel de administración Pomplay</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Dashboard</span>
      </div>
    </div>
  </div>

    <!-- ── STAT CARDS ── -->
    <div class="dash-stats">
      <a href="videos/index.php" class="dash-stat-card">
        <div class="dash-stat-icon">
          <i class="bi bi-play-circle"></i>
        </div>
        <div class="dash-stat-info">
          <h3><?= $totalVideos ?></h3>
          <p>Videos</p>
        </div>
      </a>
      <a href="canchas/index.php" class="dash-stat-card">
        <div class="dash-stat-icon">
          <i class="bi bi-geo-alt"></i>
        </div>
        <div class="dash-stat-info">
          <h3><?= $totalCanchas ?></h3>
          <p>Canchas</p>
        </div>
      </a>

      <a href="locales/index.php" class="dash-stat-card">
        <div class="dash-stat-icon">
          <i class="bi bi-shop"></i>
        </div>
        <div class="dash-stat-info">
          <h3><?= $totalLocales ?></h3>
          <p>Locales</p>
        </div>
      </a>
      
    </div>

    <!-- ── CHART + TOP CANCHAS ── -->
    <div class="dashboard-grid">

      <!-- Videos por mes -->
      <div class="chart-card">
        <div class="card-header">
          <h3 class="card-title">Videos este año</h3>
          <div class="card-subtitle">Tendencia mensual</div>
        </div>
        <div class="chart-container">
          <canvas id="videosChart"></canvas>
        </div>
      </div>

      <!-- Top canchas -->
      <div class="top-canchas-card">
        <div class="card-header">
          <h3 class="card-title">Canchas destacadas</h3>
          <div class="card-subtitle">Por número de videos</div>
        </div>
        <div class="canchas-list">
          <?php foreach ($topCanchas as $tc):
            $pct = $maxTop > 0 ? round(($tc['total'] / $maxTop) * 100) : 0;
          ?>
          <div class="cancha-item">
            <div class="cancha-info">
              <span class="cancha-name"><?= htmlspecialchars($tc['descripcion']) ?></span>
              <span class="cancha-count"><?= $tc['total'] ?></span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($topCanchas)): ?>
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>Sin datos aún</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── RECENT VIDEOS ── -->
    <div class="recent-videos-card">
      <div class="card-header">
        <h3 class="card-title">Videos recientes</h3>
        <a href="videos.php" class="view-all-link">
          Ver todos <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <?php if (empty($recentVideos)): ?>
        <div class="empty-state">
          <i class="bi bi-play-circle"></i>
          <p>No hay videos registrados aún.</p>
        </div>
      <?php else: ?>
      <div class="table-container">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Descripción</th>
              <th>Cancha</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentVideos as $rv):
              $fd = str_replace(array_keys($meses), array_values($meses), date('d M Y', strtotime($rv['fecha_partido'])));
            ?>
            <tr>
              <td data-label="Código"><span class="td-id"><?= htmlspecialchars($rv['codigo_video']) ?></span></td>
              <td data-label="Descripción" class="td-description"><?= htmlspecialchars($rv['descripcion']) ?></td>
              <td data-label="Cancha"><span class="badge-active"><?= htmlspecialchars($rv['cancha_nombre'] ?? $rv['codigo_cancha']) ?></span></td>
              <td data-label="Fecha" class="td-date"><?= $fd ?></td>
              <td data-label="Acciones">
                <div class="table-actions">
                  <a href="edit_video.php?codigo_video=<?= urlencode($rv['codigo_video']) ?>" class="btn-icon btn-icon-edit" title="Editar">
                    <i class="bi bi-pencil"></i>
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

    <!-- ── QUICK ACTIONS ── -->
    <div class="quick-actions">
      <h3 class="section-title">Acciones rápidas</h3>
      <div class="actions-grid">
        <?php
        $actions = [
          ['href'=>'videos/add.php','icon'=>'bi bi-plus-circle','label'=>'Nuevo Video'],
          ['href'=>'canchas/add.php',         'icon'=>'bi bi-geo-alt','label'=>'Nueva Cancha'],
          ['href'=>'videos/index.php',             'icon'=>'bi bi-list-ul',    'label'=>'Ver Videos'],
        ];
        foreach ($actions as $a): ?>
        <a href="<?= $a['href'] ?>" class="action-card">
          <div class="action-icon">
            <i class="<?= $a['icon'] ?>"></i>
          </div>
          <span class="action-label"><?= $a['label'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
</main>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Chart.js – Videos por mes
const months  = <?= json_encode($chartMonths) ?>;
const values  = <?= json_encode($chartValues) ?>;

// Rellena meses sin datos con 0
const allMonths = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const fullValues = allMonths.map(m => {
  const idx = months.indexOf(m);
  return idx >= 0 ? values[idx] : 0;
});

const ctx = document.getElementById('videosChart').getContext('2d');

const gradient = ctx.createLinearGradient(0, 0, 0, 160);
gradient.addColorStop(0,   'rgba(236, 66, 55, 0.4)');
gradient.addColorStop(1,   'rgba(236, 66, 55, 0)');

new Chart(ctx, {
  type: 'line',
  data: {
    labels: allMonths,
    datasets: [{
      data: fullValues,
      borderColor: '#ec4237',
      borderWidth: 3,
      backgroundColor: gradient,
      fill: true,
      tension: 0.45,
      pointBackgroundColor: '#ec4237',
      pointRadius: 4,
      pointHoverRadius: 6,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0F0E1E',
        borderColor: 'rgba(236, 66, 55, 0.3)',
        borderWidth: 1,
        titleColor: 'rgba(255,255,255,0.7)',
        bodyColor: '#ffffff',
        bodyFont: { family: 'Inter', weight: '600' },
        padding: 12,
        callbacks: {
          label: ctx => ` ${ctx.parsed.y} video${ctx.parsed.y !== 1 ? 's' : ''}`
        }
      }
    },
    scales: {
      x: {
        grid: { color: 'rgba(255,255,255,.05)' },
        ticks: { color: 'rgba(168,168,200,.55)', font: { family: 'Inter', size: 11 } },
        border: { color: 'transparent' }
      },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(255,255,255,.05)' },
        ticks: {
          color: 'rgba(168,168,200,.55)',
          font: { family: 'Inter', size: 11 },
          stepSize: 1,
          precision: 0
        },
        border: { color: 'transparent' }
      }
    }
  }
});
</script>
<?php include 'shared/footer_admin.php'; ?>
