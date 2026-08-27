<?php


// ── Configuración ──────────────────────────────────────────────────────────
$API_KEY    = '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6';
$FFMPEG     = '/usr/bin/ffmpeg'; // Ajustar si es necesario
$CLIPS_DIR  = __DIR__ . '/clips';        // Carpeta pública donde se guardan los MP4
$CLIPS_URL  = 'https://cctv.pomplay.com.pe/clips'; // URL pública de la carpeta
$MAX_DUR    = 7200;  // Duración máxima del clip: 2 horas (segundos)
$KEEP_HOURS = 24;    // Horas que se conservan los clips antes de borrar

// Crear carpeta de clips si no existe
if (!is_dir($CLIPS_DIR)) {
    mkdir($CLIPS_DIR, 0755, true);
}

// ── Autenticación ──────────────────────────────────────────────────────────
$key = $_POST['key'] ?? $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

// Soporte JSON body
$jsonInput = [];
$rawBody = file_get_contents('php://input');
if (!empty($rawBody) && str_starts_with(trim($rawBody), '{')) {
    $jsonInput = json_decode($rawBody, true) ?? [];
    if (empty($key)) $key = $jsonInput['key'] ?? '';
}

if ($key !== $API_KEY) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

// ── Determinar modo ────────────────────────────────────────────────────────
$mode = $_POST['mode'] ?? $jsonInput['mode'] ?? (isset($_FILES['clip']) ? 'convert' : 'clip');

// ══════════════════════════════════════════════════════════════════════════
// MODO 2: Recorte profesional por URL + tiempos (NUEVO)
// ══════════════════════════════════════════════════════════════════════════
if ($mode === 'clip') {
    header('Content-Type: application/json; charset=utf-8');

    // Parámetros
    $videoUrl  = $_POST['video_url'] ?? $jsonInput['video_url'] ?? '';
    $start     = (float)($_POST['start'] ?? $jsonInput['start'] ?? -1);
    $end       = (float)($_POST['end']   ?? $jsonInput['end']   ?? -1);
    $zoom      = (float)($_POST['zoom']  ?? $jsonInput['zoom']  ?? 1);
    $panX      = (float)($_POST['pan_x'] ?? $jsonInput['pan_x'] ?? 0);
    $panY      = (float)($_POST['pan_y'] ?? $jsonInput['pan_y'] ?? 0);

    // Validación
    if (empty($videoUrl)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'video_url es requerido']);
        exit;
    }
    if ($start < 0 || $end <= 0 || $end <= $start) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'start/end inválidos']);
        exit;
    }
    $duration = $end - $start;
    if ($duration < 1 || $duration > $MAX_DUR) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Duración fuera de rango (1s–{$MAX_DUR}s)"]);
        exit;
    }

    // Limpiar clips viejos (mantenimiento automático)
    cleanOldClips($CLIPS_DIR, $KEEP_HOURS);

    // Nombre único para el output
    $clipId   = 'clip_' . bin2hex(random_bytes(8));
    $outFile  = $CLIPS_DIR . '/' . $clipId . '.mp4';

    // ── Construir filtro de video ──────────────────────────────────────
    // Si hay zoom + pan, aplicar crop + scale para simular el zoom del reproductor
    $vfFilters = [];

    if ($zoom > 1.05) {
        // El reproductor aplica CSS transform:scale(zoom) translate(panX,panY)
        // FFmpeg equivalente: crop centrado en la región de zoom, luego scale al tamaño original
        // panX/panY están en píxeles del video escalado; convertir a fracción
        // Usamos crop con iw/zoom y anclaje ajustado por pan
        $cropW   = 'iw/' . $zoom;
        $cropH   = 'ih/' . $zoom;
        // Posición del crop: centro + pan normalizado
        $cropX   = "(iw-iw/{$zoom})/2 - ({$panX}/{$zoom})";
        $cropY   = "(ih-ih/{$zoom})/2 - ({$panY}/{$zoom})";
        $vfFilters[] = "crop={$cropW}:{$cropH}:{$cropX}:{$cropY}";
        $vfFilters[] = 'scale=iw*' . $zoom . ':ih*' . $zoom;
        // Reescalar al tamaño original
        $vfFilters[] = 'scale=in_w:in_h';
    }

    // Asegurar dimensiones pares (requerido por libx264)
    $vfFilters[] = 'scale=trunc(iw/2)*2:trunc(ih/2)*2';

    $vfArg = implode(',', $vfFilters);

    // ── Comando FFmpeg ────────────────────────────────────────────────
    // -ss antes de -i para seek rápido (seek de entrada = sin decodificar todo)
    // Para videos muy largos de CCTV esto es CRÍTICO para el rendimiento
    $cmd = escapeshellcmd($FFMPEG)
         . ' -ss ' . escapeshellarg((string)$start)
         . ' -to ' . escapeshellarg((string)$end)
         . ' -i '  . escapeshellarg($videoUrl)
         . ' -c:v libx264'
         . ' -preset fast'
         . ' -crf 23'
         . ' -maxrate 3000k'
         . ' -bufsize 6000k'
         . ' -profile:v baseline'  // máxima compatibilidad móvil/iPhone
         . ' -level 3.1'
         . ' -pix_fmt yuv420p'     // requerido para WhatsApp/iOS
         . ' -vf ' . escapeshellarg($vfArg)
         . ' -c:a aac'
         . ' -b:a 128k'
         . ' -ar 44100'
         . ' -ac 2'
         . ' -movflags +faststart' // streaming inmediato en móviles
         . ' -avoid_negative_ts make_zero'
         . ' -y ' . escapeshellarg($outFile)
         . ' 2>&1';

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outFile) || filesize($outFile) < 1000) {
        $errDetail = implode("\n", array_slice($output, -5)); // últimas 5 líneas de FFmpeg
        error_log('[PomPlay clip] FFmpeg error: ' . $errDetail);
        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => 'Error al recortar el video',
            'detail' => (ini_get('display_errors') ? $errDetail : null),
        ]);
        exit;
    }

    $clipUrl  = $CLIPS_URL . '/' . $clipId . '.mp4';
    $filename = $clipId . '.mp4';
    $size     = filesize($outFile);

    echo json_encode([
        'ok'       => true,
        'clip_url' => $clipUrl,
        'filename' => $filename,
        'size'     => $size,
        'duration' => $duration,
        'start'    => $start,
        'end'      => $end,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// MODO 1 (legacy): Subida de archivo WebM → conversión a MP4
// ══════════════════════════════════════════════════════════════════════════
header('Content-Type: application/json');

if (!isset($_FILES['clip']) || $_FILES['clip']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo']);
    exit;
}

$tmpIn  = $_FILES['clip']['tmp_name'];
$size   = $_FILES['clip']['size'];

if ($size < 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Archivo demasiado pequeño']);
    exit;
}

$tmpOut = tempnam(sys_get_temp_dir(), 'pomplay_') . '.mp4';

$cmd = escapeshellcmd($FFMPEG)
     . ' -i '    . escapeshellarg($tmpIn)
     . ' -c:v libx264'
     . ' -preset fast'
     . ' -crf 23'
     . ' -maxrate 2500k -bufsize 5000k'
     . ' -profile:v baseline -level 3.1'
     . ' -pix_fmt yuv420p'
     . ' -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2"'
     . ' -c:a aac'
     . ' -b:a 128k'
     . ' -ar 44100 -ac 2'
     . ' -movflags +faststart'
     . ' -y ' . escapeshellarg($tmpOut)
     . ' 2>/dev/null';

exec($cmd, $output, $returnCode);

if ($returnCode !== 0 || !file_exists($tmpOut) || filesize($tmpOut) < 1000) {
    // Fallback: devolver el WebM original si FFmpeg falla
    header('Content-Type: video/webm');
    header('Content-Disposition: attachment; filename="clip_pomplay.webm"');
    header('Content-Length: ' . $size);
    readfile($tmpIn);
    exit;
}

// Devolver MP4
$mp4Size = filesize($tmpOut);
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="clip_pomplay.mp4"');
header('Content-Length: ' . $mp4Size);
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

readfile($tmpOut);
@unlink($tmpOut);
exit;

// ── Función: limpiar clips viejos ─────────────────────────────────────────
function cleanOldClips(string $dir, int $keepHours): void {
    $threshold = time() - ($keepHours * 3600);
    foreach (glob($dir . '/clip_*.mp4') ?: [] as $file) {
        if (filemtime($file) < $threshold) {
            @unlink($file);
        }
    }
}
