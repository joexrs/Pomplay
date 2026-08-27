<?php

declare(strict_types=1);

// Configuración de errores para debugging (desactiva en producción)
// Descomenta estas líneas temporalmente para ver errores:
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

// Iniciar sesión de una sola vez
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$controllers = require __DIR__ . '/../bootstrap/app.php';

// Obtener la ruta de la solicitud original (usando ORIGINAL_URI guardada en .htaccess si está disponible)
$requestPath = parse_url($_SERVER['ORIGINAL_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Calcular la carpeta raíz del proyecto de forma ultra-robusta
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$baseUrl = '';

if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}

$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');

// Ajustar requestPath para el ruteo
if ($baseUrl !== '' && $baseUrl !== '/' && str_starts_with($requestPath, $baseUrl)) {
    $requestPath = substr($requestPath, strlen($baseUrl));
}
$requestPath = '/' . ltrim($requestPath, '/');
$requestPath = (in_array($requestPath, ['//index.php', '/public/index.php', '/index.php/'], true)) ? '/index.php' : $requestPath;

$context = ['baseUrl' => $baseUrl];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (($requestPath === '/' || $requestPath === '/index.php') && $method === 'GET') {
    $controllers['home']->index($context);
    return;
}

if ($requestPath === '/about.php' && $method === 'GET') {
    $controllers['about']->index($context);
    return;
}

if ($requestPath === '/login.php' && in_array($method, ['GET', 'POST'], true)) {
    $controllers['auth']->login($context);
    return;
}

if (in_array($requestPath, [ '/memberships.php'], true) && $method === 'GET') {
    $controllers['memberships']->index($context);
    return;
}

if ($requestPath === '/video-detail.php' && $method === 'GET') {
    $controllers['video']->detail($context);
    return;
}

// Ruta dinámica para videos: /video/{codigo} (solo códigos, no archivos con extensión)
if (preg_match('#^/video/([A-Za-z0-9_-]+)$#', $requestPath, $matches) && $method === 'GET') {
    $codigo = $matches[1];
    // Si tiene extensión de video, dejar que Apache/servidor web lo maneje
    if (!preg_match('/\.(mp4|avi|mov|mkv|webm|flv|m4v|wmv)$/i', $codigo)) {
        $_GET['id'] = $codigo;
        $controllers['video']->detail($context);
        return;
    }
    // Si es un archivo de video, no hacer nada y dejar que se sirva el archivo
}

if (($requestPath === '/api/canchas' || $requestPath === '/get_canchas_by_categoria.php') && $method === 'GET') {
    $controllers['apiCourt']->byCategory();
    return;
}

if ($requestPath === '/api/locales' && $method === 'GET') {
    $controllers['apiLocal']->all();
    return;
}

if ($requestPath === '/api/canchas-por-local' && $method === 'GET') {
    $controllers['apiCourt']->byLocal();
    return;
}

if ($requestPath === '/api/horas' && $method === 'GET') {
    $controllers['apiHour']->available();
    return;
}

// API de PINs de video
if (preg_match('#^/api/videos/([A-Za-z0-9_-]+)/generar-pin$#', $requestPath, $matches) && $method === 'POST') {
    $controllers['apiVideoPin']->generate($matches[1]);
    return;
}

if (preg_match('#^/api/videos/([A-Za-z0-9_-]+)/verificar-pin$#', $requestPath, $matches) && $method === 'POST') {
    $controllers['apiVideoPin']->verify($matches[1]);
    return;
}

// API de Clips (recorte profesional backend FFmpeg)
if ($requestPath === '/api/create-clip' && $method === 'POST') {
    $controllers['apiClip']->create();
    return;
}

// API de Reproducciones (tracking de plays para estadisticas)
if ($requestPath === '/api/log-play' && $method === 'POST') {
    $controllers['apiPlay']->log();
    return;
}

// Proxy de descarga de clips (fuerza descarga como archivo, resuelve cross-origin)
if ($requestPath === '/api/download-clip' && $method === 'GET') {
    $controllers['apiClip']->download();
    return;
}


// Rutas del admin
if (str_starts_with($requestPath, '/admin/')) {
    $adminFile = substr($requestPath, 7); 
    if (empty($adminFile) || $adminFile === '/') {
        $adminFile = 'dashboard.php';
    }
    
    $adminPath = __DIR__ . '/../admin/' . $adminFile;
    if (file_exists($adminPath) && is_file($adminPath)) {
        require $adminPath;
        return;
    }
}

// Rutas del panel de propietarios
if (str_starts_with($requestPath, '/owner/')) {
    $ownerFile = substr($requestPath, 7); 
    if (empty($ownerFile) || $ownerFile === '/') {
        $ownerFile = 'canchas.php';
    }

    $allowedOwnerFiles = [
        'canchas.php',
        'videos.php',
        'membership.php',
        'profile.php',
        'renew_membership.php',
        'logout.php',
    ];

    if (in_array($ownerFile, $allowedOwnerFiles, true)) {
        $ownerPath = __DIR__ . '/../owner/' . $ownerFile;
        if (file_exists($ownerPath) && is_file($ownerPath)) {
            require $ownerPath;
            return;
        }
    }
}

http_response_code(404);
echo 'Ruta no encontrada.';
