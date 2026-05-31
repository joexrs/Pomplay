<?php
/**
 * Versión alternativa usando UPDATE directo (sin stored procedure)
 * Si el stored procedure no funciona, renombra este archivo a procesar_edit_video.php
 */

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo_video = $_POST['codigo_video'];
    $fecha_partido = $_POST['fecha_partido'];
    $hora_partido = $_POST['hora_partido'];
    $descripcion = $_POST['descripcion'];
    $codigo_cancha = $_POST['codigo_cancha'];
    $video_url = $_POST['video_url'];
    $duracion = $_POST['duracion'];
    $observacion = $_POST['observacion'];

    // DEBUG: Ver qué valores llegan
    error_log("=== DEBUG UPDATE VIDEO V2 (UPDATE DIRECTO) ===");
    error_log("Fecha recibida: " . $fecha_partido);
    error_log("Hora recibida: " . $hora_partido);
    
    // Asegurar que la hora tenga segundos
    if (strlen($hora_partido) == 5) { // Si viene como "15:30"
        $hora_partido .= ':00'; // Agregar segundos
    }

    error_log("Fecha a guardar: " . $fecha_partido);
    error_log("Hora a guardar: " . $hora_partido);

    try {
        // Usar UPDATE directo en lugar de stored procedure
        $stmt = $pdo->prepare("
            UPDATE video 
            SET fecha_partido = :fecha_partido,
                hora_partido = :hora_partido,
                descripcion = :descripcion,
                codigo_cancha = :codigo_cancha,
                video_url = :video_url,
                duracion = :duracion,
                observacion = :observacion
            WHERE codigo_video = :codigo_video
        ");
        
        $result = $stmt->execute([
            ':codigo_video' => $codigo_video,
            ':fecha_partido' => $fecha_partido,
            ':hora_partido' => $hora_partido,
            ':descripcion' => $descripcion,
            ':codigo_cancha' => $codigo_cancha,
            ':video_url' => $video_url,
            ':duracion' => $duracion,
            ':observacion' => $observacion,
        ]);
        
        $rowsAffected = $stmt->rowCount();
        
        error_log("Resultado de la actualización: " . ($result ? "SUCCESS" : "FAILED"));
        error_log("Filas afectadas: " . $rowsAffected);
        
        if (!$result) {
            error_log("Error PDO: " . print_r($stmt->errorInfo(), true));
        }
        
        if ($rowsAffected === 0) {
            error_log("ADVERTENCIA: No se actualizó ninguna fila. ¿El código existe?");
        }
        
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        die("Error al actualizar: " . $e->getMessage());
    }

    header('Location: ' . $baseUrl . '/admin/videos/index.php');
    exit();
}
