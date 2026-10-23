<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap/autoload.php';

use App\Core\Auth;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function storageProxyResponse(array $payload, int $status): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    storageProxyResponse(['ok' => false, 'error' => 'Método no permitido'], 405);
}

if (!Auth::isAuthenticated() || !Auth::hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
    storageProxyResponse(['ok' => false, 'error' => 'No autorizado'], 401);
}

$sessionCsrf = (string) ($_SESSION['storage_proxy_csrf'] ?? '');
$requestCsrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($sessionCsrf === '' || $requestCsrf === '' || !hash_equals($sessionCsrf, $requestCsrf)) {
    storageProxyResponse(['ok' => false, 'error' => 'Solicitud no válida; recarga la página e inténtalo de nuevo'], 403);
}

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, ['upload_chunk', 'finalize_upload'], true)) {
    storageProxyResponse(['ok' => false, 'error' => 'Acción no permitida'], 400);
}

$apiUrl = rtrim((string) env('VPS_STORAGE_URL', ''), '/');
$apiKey = (string) env('VPS_KEY', '');
if ($apiUrl === '' || $apiKey === '') {
    storageProxyResponse(['ok' => false, 'error' => 'Almacenamiento no configurado'], 503);
}

$fields = ['action' => $action];
$allowedFields = [
    'upload_id', 'chunk_index', 'total_chunks', 'orig_ext',
    'id_local', 'codigo_cancha', 'fecha', 'hora',
];
foreach ($allowedFields as $field) {
    if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
        $fields[$field] = (string) $_POST[$field];
    }
}

if ($action === 'upload_chunk') {
    $file = $_FILES['chunk'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        storageProxyResponse(['ok' => false, 'error' => 'No se recibió un fragmento válido'], 400);
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        storageProxyResponse(['ok' => false, 'error' => 'El fragmento excede el tamaño permitido'], 413);
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: $mime;
    }
    $fields['chunk'] = new CURLFile(
        $file['tmp_name'],
        $mime,
        basename((string) ($file['name'] ?? 'chunk.bin'))
    );
}

$curl = curl_init($apiUrl);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $fields,
    CURLOPT_HTTPHEADER => ['X-VPS-Key: ' . $apiKey],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if (!is_string($responseBody)) {
    error_log('VPS storage proxy transport error: ' . $curlError);
    storageProxyResponse(['ok' => false, 'error' => 'No se pudo conectar con el almacenamiento'], 502);
}

$response = json_decode($responseBody, true);
if (!is_array($response)) {
    error_log('VPS storage proxy received a non-JSON response (HTTP ' . $httpStatus . ')');
    storageProxyResponse(['ok' => false, 'error' => 'Respuesta inválida del almacenamiento'], 502);
}

storageProxyResponse($response, $httpStatus >= 400 ? $httpStatus : 200);
