<?php
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include __DIR__ . '/../../conexion.php';

function jsonResponse(array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($payload);
    exit;
}
// Configuración del VPS
define('VPS_API',    'https://cctv.pomplay.com.pe/cameras.php');
define('VPS_KEY',    '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6');
define('VPS_VIDEOS', 'https://cctv.pomplay.com.pe/videos');
define('GO2RTC_URL', 'https://cctv.pomplay.com.pe/go2rtc');

$action = $_GET['action'] ?? '';
$codigo = $_GET['codigo'] ?? '';

// Acciones que van directo al VPS
$vpsActions = ['status', 'start_rec', 'stop_rec', 'list_rec'];

if (in_array($action, $vpsActions)) {
    // Obtener el stream de go2rtc desde la BD
    $stmt = $pdo->prepare("SELECT go2rtc_stream, id_local FROM cancha WHERE codigo_cancha = ?");
    $stmt->execute([$codigo]);
    $cancha = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cancha || empty($cancha['go2rtc_stream'])) {
        jsonResponse(['ok' => false, 'error' => 'Cámara no configurada']);
    }

    $stream  = $cancha['go2rtc_stream'];
    $idLocal = $cancha['id_local'];

    $url = VPS_API . '?action=' . urlencode($action)
         . '&codigo='      . urlencode($codigo)
         . '&stream='      . urlencode($stream)
         . '&id_local='    . urlencode($idLocal)
         . '&key='         . VPS_KEY;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo conectar al VPS: ' . $err]);
    }

    json_decode((string) $resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse([
            'ok' => false,
            'error' => 'El VPS devolvió una respuesta inválida',
        ]);
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo $resp;
    exit;
}

?>
