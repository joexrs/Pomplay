<?php
require __DIR__ . '/../../bootstrap/autoload.php';

use App\Core\Auth;

// Prevenir caché de páginas autenticadas
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Calcular la ruta base del proyecto de forma dinámica
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$baseUrl = '';

if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}

$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');

// Si $baseUrl es solo '/', dejarlo vacío para producción
if ($baseUrl === '/') {
    $baseUrl = '';
}

if (!Auth::isAuthenticated()) {
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

$supportUrl = $baseUrl . '/admin/support.php';
$supportTarget = '';
$supportRel = '';
$supportIcon = 'bi bi-chat-dots';
$contactConfigPath = __DIR__ . '/../../config/contact_channels.json';

if (is_readable($contactConfigPath)) {
    $contactConfig = json_decode(file_get_contents($contactConfigPath), true);
    $supportConfig = $contactConfig['support'] ?? [];
    $primaryChannel = $supportConfig['primary'] ?? 'whatsapp';
    $channels = $supportConfig['channels'] ?? [];

    if ($primaryChannel === 'whatsapp' && !empty($channels['whatsapp']['enabled'])) {
        $phone = preg_replace('/\D+/', '', (string) ($channels['whatsapp']['phone'] ?? ''));
        if ($phone !== '') {
            $message = (string) ($supportConfig['message'] ?? 'Hola, necesito ayuda con Pomplay.');
            $supportUrl = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
            $supportTarget = ' target="_blank"';
            $supportRel = ' rel="noopener noreferrer"';
            $supportIcon = 'bi bi-whatsapp';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Panel de Administración – Pomplay" />
    <title>Panel · Pomplay</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Vali Admin CSS base -->
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl ?>/public/css/main.css" />

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" type="text/css"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />

    <!-- DataTables -->
    <link href="<?= $baseUrl ?>/public/datatables/datatables.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/fontawesome/css/all.min.css?v=1.0" />

    <!-- Admin Modern v2 (Light Theme) -->
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl ?>/public/css/admin-modern-v2.css?v=2.0" />

    <!-- Responsive Consolidated - Sidebar Mobile (DEBE IR AL FINAL) -->
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl ?>/public/css/responsive-consolidated.css?v=1.6" />

    <!-- 📅 Flatpickr Date/Time Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl ?>/public/css/flatpickr-custom.css?v=1.3" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= $baseUrl ?>/public/images/pomplay%20logo.png" />

    
</head>

<body class="app sidebar-mini">

    <?php $userRole = $_SESSION['rol'] ?? 'ADMIN'; ?>

    <!-- Navbar -->
    <header class="app-header">
        <!-- Sidebar toggle button (left side) -->
        <a class="app-sidebar__toggle" href="#" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
        </a>

        <a class="app-header__logo" href="<?= $baseUrl ?>/">
            <img src="<?= $baseUrl ?>/public/images/pomplay%20sin%20fondo.png" alt="Pomplay Logo" class="logo-full" style="height: 28px; width: auto;">
        </a>

        <!-- Right menu -->
        <ul class="app-nav">
            <li class="app-nav-item">
                <a href="<?= $baseUrl ?>/" target="_blank" class="app-nav-link" title="Ver sitio público">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Ver sitio</span>
                </a>
            </li>
            <!-- User menu -->
            <li class="dropdown app-nav-item">
                <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Perfil de usuario">
                   <div class="user-avatar">
                       <i class="bi bi-person-circle"></i>
                   </div>
                </a>
                <ul class="dropdown-menu settings-menu dropdown-menu-right">
                    <li>
                        <a class="dropdown-item" href="<?= $userRole === 'DUEÑO' ? $baseUrl . '/owner/profile.php' : $baseUrl . '/admin/profile.php' ?>">
                            <i class="bi bi-person mr-2"></i> Mi Perfil
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= $baseUrl ?>/admin/shared/logout.php">
                            <i class="bi bi-box-arrow-right mr-2"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </header>

    <!-- Sidebar -->
    <aside class="app-sidebar" id="appSidebar">
        <ul class="app-menu">

            <?php if ( $userRole === 'DUEÑO'): ?>
                <!-- Sidebar para Propietarios -->
                <li>
                    <a class="app-menu__item <?= basename($_SERVER['PHP_SELF']) == 'canchas.php' ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/owner/canchas.php">
                       <i class="app-menu__icon bi bi-geo-alt"></i>
                       <span class="app-menu__label">Canchas</span>
                    </a>
                </li>

                <li>
                    <a class="app-menu__item <?= basename($_SERVER['PHP_SELF']) == 'videos.php' ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/owner/videos.php">
                       <i class="app-menu__icon bi bi-play-circle"></i>
                       <span class="app-menu__label">Mis Videos</span>
                    </a>
                </li>

                <li>
                    <a class="app-menu__item <?= basename($_SERVER['PHP_SELF']) == 'membership.php' ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/owner/membership.php">
                       <i class="app-menu__icon bi bi-people"></i>
                       <span class="app-menu__label">Mi Membresía</span>
                    </a>
                </li>

                <li>
                    <a class="app-menu__item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/owner/profile.php">
                        <i class="app-menu__icon bi bi-person-gear"></i>
                        <span class="app-menu__label">Mi Perfil</span>
                    </a>
                </li>
            <?php else: ?>
                <!-- Sidebar para Admins -->
                <li>
                    <a class="app-menu__item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/admin/dashboard.php">
                       <i class="app-menu__icon bi bi-speedometer2"></i>
                       <span class="app-menu__label">Dashboard</span>
                    </a>
                </li>

                <li class="treeview">
                    <a class="app-menu__item" href="#" data-toggle="treeview">
                        <i class="app-menu__icon bi bi-collection"></i>
                        <span class="app-menu__label">Contenido</span>
                        <i class="treeview-indicator bi bi-chevron-down"></i>
                    </a>
                    <ul class="treeview-menu">
                        <li>
                            <a class="treeview-item <?= strpos($_SERVER['PHP_SELF'], '/videos/') !== false ? 'active' : '' ?>"
                               href="<?= $baseUrl ?>/admin/videos/index.php">
                               <i class="icon bi bi-play-circle"></i> Videos
                            </a>
                        </li>
                        <li>
                            <a class="treeview-item <?= strpos($_SERVER['PHP_SELF'], '/canchas/') !== false ? 'active' : '' ?>"
                               href="<?= $baseUrl ?>/admin/canchas/index.php">
                               <i class="icon bi bi-geo-alt"></i> Canchas
                            </a>
                        </li>
                        <li>
                            <a class="treeview-item <?= strpos($_SERVER['PHP_SELF'], '/locales/') !== false ? 'active' : '' ?>"
                               href="<?= $baseUrl ?>/admin/locales/index.php">
                               <i class="icon bi bi-shop"></i> Locales
                            </a>
                        </li>
                        
                    </ul>
                </li>

                <li>
                    <a class="app-menu__item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>"
                       href="<?= $baseUrl ?>/admin/profile.php">
                        <i class="app-menu__icon bi bi-person-gear"></i>
                        <span class="app-menu__label">Configuración</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <?php if (strpos((string) $userRole, 'DUE') === 0): ?>
        <!-- Help card at bottom of owner sidebar -->
        <div class="sidebar-help">
          <div class="help-content">
            <h4>¿Necesitas ayuda?</h4>
            <p>Estamos para ayudarte con lo que necesites.</p>
            <a href="<?= htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8') ?>" class="help-btn"<?= $supportTarget ?><?= $supportRel ?>><i class="<?= htmlspecialchars($supportIcon, ENT_QUOTES, 'UTF-8') ?>"></i> Contactar soporte</a>
          </div>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Sidebar overlay (mobile) -->
    <div class="app-sidebar__overlay" id="sidebarOverlay"></div>
