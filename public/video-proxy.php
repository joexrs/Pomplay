<?php
/**
 * Video Proxy - Sirve videos con headers CORS para permitir captureStream()
 * Uso: /public/video-proxy.php?url=<encoded_video_url>
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo 'Missing URL parameter';
    exit;
}

$videoUrl = urldecode($_GET['url']);

// Validar que sea una URL de video permitida
$allowedDomains = [
    'cctv.pomplay.com.pe',
    'pomplay.com.pe',
    $_SERVER['HTTP_HOST'] ?? 'localhost'
];

$parsedUrl = parse_url($videoUrl);
if (!$parsedUrl || !isset($parsedUrl['host'])) {
    http_response_code(400);
    echo 'Invalid URL';
    exit;
}

$isAllowed = false;
foreach ($allowedDomains as $domain) {
    if (str_ends_with($parsedUrl['host'], $domain)) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo 'Domain not allowed';
    exit;
}

// Obtener el video
$ch = curl_init($videoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

// Headers para streaming
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$videoData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$contentLength = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo 'Error fetching video: ' . $error;
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo 'Video not found (HTTP ' . $httpCode . ')';
    exit;
}

// Enviar el video con headers apropiados
header('Content-Type: ' . ($contentType ?: 'video/mp4'));
header('Content-Length: ' . $contentLength);
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');

// Habilitar CORS para captureStream
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Cross-Origin-Resource-Policy: cross-origin');
header('Cross-Origin-Embedder-Policy: require-corp');

echo $videoData;
