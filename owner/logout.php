<?php
require __DIR__ . '/../bootstrap/autoload.php';
use App\Core\Auth;

// Prevenir caché del navegador
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

Auth::logout();

// Calcular la base URL correctamente (debe apuntar a la raíz del proyecto, no a /owner)
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/'); // Subir 1 nivel desde owner

$baseUrl = '';
if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}
$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando sesión</title>
</head>
<body>
    <script>
        // Notificar a otras pestañas que se está haciendo logout
        try {
            localStorage.setItem('logout_event', Date.now().toString());
        } catch(e) {
            console.error('Error al notificar logout:', e);
        }
        
        // Limpiar todo el localStorage y sessionStorage
        localStorage.clear();
        sessionStorage.clear();
        
        // Limpiar todas las cookies (incluyendo JWT)
        const cookies = document.cookie.split(";");
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i];
            const eqPos = cookie.indexOf("=");
            const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
            document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
        }
        
        // Redirigir inmediatamente a la raíz del proyecto
        window.location.replace('<?= $baseUrl ?>/login.php?logout=1');
    </script>
</body>
</html>
