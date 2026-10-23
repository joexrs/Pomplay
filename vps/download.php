<?php
/**
 * Descarga autorizada de videos del VPS con soporte para reanudación HTTP.
 * Subir junto a convert.php, cameras.php y storage.php en la raíz web de CCTV.
 */

declare(strict_types=1);

$config = is_readable('/opt/cctv/.env')
    ? @parse_ini_file('/opt/cctv/.env', false, INI_SCANNER_RAW)
    : [];
$config = is_array($config) ? $config : [];
$secret = (string) ($config['VPS_KEY'] ?? getenv('VPS_KEY') ?: '');
$recordingsRoot = realpath('/opt/cctv/recordings');

function downloadError(int $status, string $message, ?int $fileSize = null): void
{
    if ($fileSize !== null) header('Content-Range: bytes */' . $fileSize);
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if ($secret === '' || $recordingsRoot === false) {
    downloadError(503, 'Servicio de descarga no configurado');
}

$relativePath = rawurldecode(trim($_GET['path'] ?? ''));
$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_GET['filename'] ?? 'video.mp4'));
$expires = filter_var($_GET['expires'] ?? null, FILTER_VALIDATE_INT);
$signature = strtolower(trim($_GET['signature'] ?? ''));

if (!preg_match('#^\d+/[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+\.mp4$#i', $relativePath)
    || str_contains($relativePath, '..')
    || str_contains($relativePath, "\0")) {
    downloadError(400, 'Ruta de video no válida');
}
if ($filename === '' || !str_ends_with(strtolower($filename), '.mp4')) $filename = 'video.mp4';
if (!$expires || $expires < time() || $expires > time() + 21660) {
    downloadError(403, 'El enlace de descarga expiró');
}

$expectedSignature = hash_hmac('sha256', $relativePath . "\n" . $filename . "\n" . $expires, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    downloadError(403, 'Firma de descarga no válida');
}

$filePath = realpath($recordingsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
$rootPrefix = rtrim($recordingsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($filePath === false || !str_starts_with($filePath, $rootPrefix) || !is_file($filePath) || !is_readable($filePath)) {
    downloadError(404, 'Video no encontrado');
}

$fileSize = filesize($filePath);
if ($fileSize === false || $fileSize < 1) downloadError(404, 'Video vacío o no disponible');

$start = 0;
$end = $fileSize - 1;
$rangeHeader = trim($_SERVER['HTTP_RANGE'] ?? '');
if ($rangeHeader !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/i', $rangeHeader, $rangeMatch)
        || ($rangeMatch[1] === '' && $rangeMatch[2] === '')) {
        downloadError(416, 'Rango de bytes no válido', $fileSize);
    }

    if ($rangeMatch[1] === '') {
        $suffixLength = (int) $rangeMatch[2];
        if ($suffixLength < 1) downloadError(416, 'Rango de bytes no válido', $fileSize);
        $start = max(0, $fileSize - $suffixLength);
    } else {
        $start = (int) $rangeMatch[1];
        if ($rangeMatch[2] !== '') $end = min((int) $rangeMatch[2], $fileSize - 1);
    }

    if ($start >= $fileSize || $end < $start) downloadError(416, 'Rango fuera del video', $fileSize);
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
} else {
    http_response_code(200);
}

$contentLength = $end - $start + 1;
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . $contentLength);
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Accel-Buffering: no');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') downloadError(405, 'Método no permitido');

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) @ob_end_clean();

$stream = fopen($filePath, 'rb');
if ($stream === false || fseek($stream, $start) !== 0) downloadError(500, 'No se pudo leer el video');

$remaining = $contentLength;
while ($remaining > 0 && !connection_aborted()) {
    $chunk = fread($stream, min(1024 * 1024, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($stream);

if ($remaining > 0) {
    error_log(sprintf('[download.php] Stream incompleto para %s: faltaron %d bytes', basename($filePath), $remaining));
}
