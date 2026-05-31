<?php
header('Content-Type: application/json');
include __DIR__ . '/../../conexion.php';

$body = json_decode(file_get_contents('php://input'), true);

// Verificar key
$API_KEY = '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6';
if (($body['key'] ?? '') !== $API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Generar codigo_video
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_video, 4) AS UNSIGNED)) as max FROM video");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $next = ($row['max'] ?? 0) + 1;
    $codigoVideo = 'VID' . str_pad($next, 3, '0', STR_PAD_LEFT);

    // Descripcion automática con fecha y hora de Lima
    $fecha       = $body['fecha'] ?? date('Y-m-d');
    $hora        = $body['hora']  ?? date('H:i:s');
    $descripcion = date('d/m/Y H:i', strtotime("$fecha $hora"));

    $pdo->prepare("
        INSERT INTO video (
            codigo_video, id_local, fecha_partido, descripcion,
            hora_partido, codigo_cancha, video_url, duracion,
            foto_referencia, fecha_registro, estado, es_descargable
        ) VALUES (
            :codigo_video, :id_local, :fecha, :descripcion,
            :hora, :codigo_cancha, :video_url, :duracion,
            '', CURDATE(), 1, 1
        )
    ")->execute([
        ':codigo_video'  => $codigoVideo,
        ':id_local'      => $body['id_local'],
        ':fecha'         => $fecha,
        ':hora'          => $hora,
        ':descripcion'   => $descripcion,
        ':codigo_cancha' => $body['codigo_cancha'],
        ':video_url'     => $body['video_url'],
        ':duracion'      => $body['duracion']
    ]);

    echo json_encode(['ok' => true, 'codigo_video' => $codigoVideo]);

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}