<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isAuthenticated = !empty($_SESSION['loggedin']) && (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']));
$userRole = $_SESSION['rol'] ?? null;
$isAdmin = in_array($userRole, ['ADMIN', 'SUPER_ADMIN'], true);
$isOwner = in_array($userRole, ['DUENO', 'DUEÑO'], true);

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isAbout = str_contains($currentUri, 'about.php');
$isMembership = str_contains($currentUri, 'membresias') || str_contains($currentUri, 'membership');
$isLogin = str_contains($currentUri, 'login.php');
$isHome = (!$isAbout && !$isLogin && !str_contains($currentUri, 'admin'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Pomplay – Descubre y revive los mejores momentos de tus eventos deportivos. Explora eventos, categorías y videos." />
    <title><?= htmlspecialchars($title ?? 'Pomplay', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/fontawesome/css/all.min.css?v=1.0" />
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/style-modern-v2.css?v=3.1" />
    <?php if ($isAbout || $isMembership): ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/about-page.css?v=1.1" />
    <?php endif; ?>
    <?php if ($isLogin): ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/login-page.css?v=1.1" />
    <?php endif; ?>
    <link rel="icon" type="image/png" href="<?= $baseUrl ?>/public/images/pomplay%20logo.png" />
</head>
<body class="<?= $isAbout ? 'page-about' : ($isMembership ? 'page-memberships' : '') ?> <?= $isLogin ? 'page-login' : '' ?>">
    <div id="loader-wrapper">
        <div class="loader-ring">
            <img src="<?= $baseUrl ?>/public/images/pomplay%20logo.png" alt="Pomplay" class="loader-logo">
        </div>
    </div>
    <nav class="site-nav" id="siteNav">
        <a href="<?= $baseUrl ?>/" class="nav-brand">
            <span class="brand-icon"><img src="<?= $baseUrl ?>/public/images/pomplay%20sin%20fondo.png" alt="Pomplay Logo" style="height: 36px; width: auto; max-width: 160px; object-fit: contain;"></span>
        </a>
        <div class="nav-spacer"></div>
        <!-- Links inline (desktop: ocultos por defecto, aparecen al abrir) -->
        <ul class="nav-links" id="navLinks">
            <li><a href="<?= $baseUrl ?>/index.php" class="nav-link-item <?= $isHome ? 'active' : '' ?>">HOME</a></li>
            <li><a href="<?= $baseUrl ?>/about.php" class="nav-link-item <?= $isAbout ? 'active' : '' ?>">NOSOTROS</a></li>
            <?php if ($isAuthenticated): ?>
                <?php if ($isAdmin): ?>
                    <li><a href="<?= $baseUrl ?>/admin/dashboard.php" class="nav-link-item">PANEL</a></li>
                <?php elseif ($isOwner): ?>
                    <li><a href="<?= $baseUrl ?>/owner/canchas.php" class="nav-link-item">PANEL</a></li>
                <?php endif; ?>
                <li><a href="<?= $baseUrl ?>/admin/shared/logout.php" class="nav-link-item">SALIR</a></li>
            <?php else: ?>
                <li><a href="<?= $baseUrl ?>/login.php" class="nav-link-item <?= $isLogin ? 'active' : '' ?>" title="Acceder"><i class="fas fa-lock"></i></a></li>
            <?php endif; ?>
        </ul>
        <!-- Botón hamburger para mobile -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation"><span></span><span></span><span></span></button>
        <!-- Botón trigger para desktop (siempre visible en desktop) -->
        <button class="nav-desktop-trigger" id="navDesktopTrigger" aria-label="Abrir menú">
            <span class="trigger-bar"></span>
            <span class="trigger-bar"></span>
            <span class="trigger-bar"></span>
        </button>
    </nav>
