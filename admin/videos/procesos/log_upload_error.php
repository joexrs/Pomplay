<?php
/**
 * log_upload_error.php
 * Registra en logs/subidas_videos_errores.log los fallos técnicos reales
 * ocurridos durante la subida de videos (para soporte y diagnóstico posterior).
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

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

$usuario = $_SESSION['admin_nombre'] ?? $_SESSION['user_nombre'] ?? $_SESSION['usuario'] ?? 'Admin ID: ' . ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 'Invitado');
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$fechaHora = date('Y-m-d H:i:s');

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
         "FALLO TÉCNICO: {$errorMsg}\n" .
         "================================================================================\n\n";

@file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true]);
