<?php
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

if (isset($_GET['codigo_video']) || isset($_GET['id'])) {
    $codigo_video = $_GET['codigo_video'] ?? $_GET['id'];


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
