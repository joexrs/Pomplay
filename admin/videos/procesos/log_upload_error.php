<?php
/**
 * log_upload_error.php
 * Registra en logs/subidas_videos_errores.log los fallos técnicos reales
 * ocurridos durante la subida de videos (para soporte y diagnóstico posterior).
 */
session_start();
require_once __DIR__ . '/../../../bootstrap/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if (!\App\Core\Auth::isAuthenticated() || !\App\Core\Auth::hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!\App\Core\Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solicitud no válida']);
    exit;
}

session_write_close();

// Leer cuerpo (FormData o JSON)
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$errorMsg     = trim($input['error_message'] ?? 'Error desconocido');
$fileName     = trim($input['file_name'] ?? 'Desconocido');
$fileSize     = trim($input['file_size'] ?? 'Desconocido');
$codigoCancha = trim($input['codigo_cancha'] ?? 'N/A');
$idLocal      = trim($input['id_local'] ?? 'N/A');
$uploadId     = trim($input['upload_id'] ?? 'N/A');
$contexto     = trim($input['context'] ?? 'admin_videos');
$uploadMetrics = $input['upload_metrics'] ?? null;

$usuario = $_SESSION['admin_nombre'] ?? $_SESSION['user_nombre'] ?? $_SESSION['usuario'] ?? 'Admin ID: ' . ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 'Invitado');
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$fechaHora = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');

// Directorio logs
$logsDir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}

$logFile = $logsDir . DIRECTORY_SEPARATOR . 'subidas_videos_errores.log';

$linea = "================================================================================\n" .
         "FECHA Y HORA : [{$fechaHora}] | IP: {$ip} | USUARIO: {$usuario}\n" .
         "MÓDULO       : {$contexto}\n" .
         "ARCHIVO      : {$fileName} ({$fileSize})\n" .
         "DESTINO      : Local ID: {$idLocal} | Cancha: {$codigoCancha} | UploadID: {$uploadId}\n" .
         "EVENTO       : {$errorMsg}\n" .
         "================================================================================\n\n";

if (is_array($uploadMetrics)) {
    $allowedMetrics = ['phase', 'completed_chunks', 'total_chunks', 'bytes_sent', 'elapsed_seconds', 'average_speed_kbps', 'request_count', 'average_request_ms', 'max_request_ms', 'average_proxy_to_vps_ms', 'max_proxy_to_vps_ms', 'retries'];
    $safeMetrics = array_intersect_key($uploadMetrics, array_flip($allowedMetrics));
    $linea .= 'METRICAS     : ' . json_encode($safeMetrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
}

@file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true]);
