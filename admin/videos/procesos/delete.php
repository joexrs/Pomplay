<?php
session_start();
require_once __DIR__ . '/../../../bootstrap/autoload.php';

if (!\App\Core\Auth::isAuthenticated() || !\App\Core\Auth::hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

if (!\App\Core\Csrf::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida. Recarga la página e inténtalo nuevamente.');
}

include '../../../conexion.php';

// Calcular baseUrl dinámicamente
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(dirname(dirname(dirname(__FILE__))))), '/');
$baseUrl = '';
if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}
$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');
if ($baseUrl === '/') {
    $baseUrl = '';
}

if (isset($_POST['codigo_video']) || isset($_POST['id'])) {
    $codigo_video = $_POST['codigo_video'] ?? $_POST['id'];


    $stmt = $pdo->prepare("CALL DeleteVideo(:codigo_video)");
    $stmt->bindParam(':codigo_video', $codigo_video);

    if ($stmt->execute()) {

        header("Location: $baseUrl/admin/videos/index.php?mensaje=Video eliminado con éxito");
        exit();
    } else {
        echo "Error al eliminar el video.";
    }
} else {
    echo "No se ha proporcionado un código de video válido.";
}
