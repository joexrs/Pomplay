<?php
/**
 * convert.php — VPS cctv.pomplay.com.pe
 */

// ── Configuración ──────────────────────────────────────────────────────────
$API_KEY    = '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6';
$FFMPEG     = '/usr/bin/ffmpeg';
$CLIPS_DIR  = __DIR__ . '/clips';
$CLIPS_URL  = 'https://cctv.pomplay.com.pe/clips';
$MAX_DUR    = 7200;
$KEEP_HOURS = 1;

// ── Assets ─────────────────────────────────────────────────────────────────
$WATERMARK  = '/opt/cctv/assets/watermark.png';
$OUTRO_DUR  = 5;     // Segundos del outro (pantalla negra + logo)
$WM_SCALE   = 0.4;   // Tamaño del logo: 40% del ancho del clip
$WM_OPACITY = 0.45;  // Opacidad del logo en el clip (0.0–1.0)
$WM_OUTRO_OPACITY = 0.9; // Opacidad del logo en el outro (más visible)

if (!is_dir($CLIPS_DIR)) mkdir($CLIPS_DIR, 0755, true);

// ── Autenticación ──────────────────────────────────────────────────────────
$key = $_POST['key'] ?? $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
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

$mode = $_POST['mode'] ?? $jsonInput['mode'] ?? (isset($_FILES['clip']) ? 'convert' : 'clip');

// ══════════════════════════════════════════════════════════════════════════
// MODO 2: Recorte profesional
// ══════════════════════════════════════════════════════════════════════════
if ($mode === 'clip') {
    header('Content-Type: application/json; charset=utf-8');

    $videoUrl = $_POST['video_url'] ?? $jsonInput['video_url'] ?? '';
    $start    = (float)($_POST['start'] ?? $jsonInput['start'] ?? -1);
    $end      = (float)($_POST['end']   ?? $jsonInput['end']   ?? -1);
    $zoom     = (float)($_POST['zoom']  ?? $jsonInput['zoom']  ?? 1);
    $panX     = (float)($_POST['pan_x'] ?? $jsonInput['pan_x'] ?? 0);
    $panY     = (float)($_POST['pan_y'] ?? $jsonInput['pan_y'] ?? 0);

    if (empty($videoUrl)) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'video_url es requerido']); exit;
    }
    if ($start < 0 || $end <= 0 || $end <= $start) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'start/end inválidos']); exit;
    }
    $duration = $end - $start;
    if ($duration < 1 || $duration > $MAX_DUR) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>"Duración fuera de rango (1s–{$MAX_DUR}s)"]); exit;
    }

    $hasWatermark = file_exists($WATERMARK);

    // Limpiar clips viejos
    $threshold = time() - ($KEEP_HOURS * 3600);
    foreach (glob($CLIPS_DIR . '/clip_*.mp4') ?: [] as $f) {
        if (filemtime($f) < $threshold) @unlink($f);
    }

    $clipId  = 'clip_' . bin2hex(random_bytes(8));
    $outFile = $CLIPS_DIR . '/' . $clipId . '.mp4';

    // ── Inputs ────────────────────────────────────────────────────────
    // [0] = video principal
    // [1] = watermark.png (si existe)
    $inputPart = ' -ss ' . escapeshellarg((string)$start)
               . ' -to ' . escapeshellarg((string)$end)
               . ' -i '  . escapeshellarg($videoUrl);

    $wmIdx = null;
    if ($hasWatermark) {
        $inputPart .= ' -i ' . escapeshellarg($WATERMARK);
        $wmIdx = 1;
    }

    // ── Zoom/pan ──────────────────────────────────────────────────────
    $zf = '';
    if ($zoom > 1.05) {
        $zf = "crop=iw/{$zoom}:ih/{$zoom}:(iw-iw/{$zoom})/2-({$panX}/{$zoom}):(ih-ih/{$zoom})/2-({$panY}/{$zoom}),scale=iw*{$zoom}:ih*{$zoom},scale=in_w:in_h,";
    }

    $wms  = $WM_SCALE;
    $wmo  = $WM_OPACITY;
    $wmoo = $WM_OUTRO_OPACITY;
    $dur  = $OUTRO_DUR;

   

    if ($hasWatermark) {
        $fc = implode(';', [
            // 1. Escalar clip
            "[0:v]{$zf}scale=trunc(iw/2)*2:trunc(ih/2)*2[clip]",

            // 2. Generar pantalla negra duración=OUTRO_DUR
            //    color filter: c=black, r=fps del clip (25 por defecto), d=duración
            "color=c=black:r=25:d={$dur}[blackraw]",

            // 3. Escalar pantalla negra al tamaño exacto del clip con scale2ref
            //    Primer output=[blackscaled], segundo output=[clipref] (clip sin cambio)
            "[blackraw][clip]scale2ref[blackscaled][clipref]",

            // 4. Watermark sobre el CLIP: escalar logo + opacidad normal
            "[{$wmIdx}:v]scale=iw*{$wms}:-1,format=rgba,colorchannelmixer=aa={$wmo}[wmclip]",
            "[clipref][wmclip]overlay=x=(W-w)/2:y=(H-h)/2+H*0.08[clipwm]",

            // 5. Watermark sobre el OUTRO (pantalla negra): más opaco y centrado
            "[{$wmIdx}:v]scale=iw*{$wms}:-1,format=rgba,colorchannelmixer=aa={$wmoo}[wmoutro]",
            "[blackscaled][wmoutro]overlay=x=(W-w)/2:y=(H-h)/2[outro]",

            // 6. Concatenar clip (con logo) + outro (negro + logo)
            "[clipwm][outro]concat=n=2:v=1:a=0[vout]",
        ]);
    } else {
        // Sin watermark: clip + pantalla negra simple
        $fc = implode(';', [
            "[0:v]{$zf}scale=trunc(iw/2)*2:trunc(ih/2)*2[clip]",
            "color=c=black:r=25:d={$dur}[blackraw]",
            "[blackraw][clip]scale2ref[blackscaled][clipref]",
            "[clipref][blackscaled]concat=n=2:v=1:a=0[vout]",
        ]);
    }

    // ── Comando FFmpeg ────────────────────────────────────────────────
    $cmd = escapeshellcmd($FFMPEG)
         . $inputPart
         . ' -filter_complex ' . escapeshellarg($fc)
         . ' -map "[vout]"'
         . ' -map 0:a?'
         . ' -c:v libx264 -preset fast -crf 23'
         . ' -maxrate 3000k -bufsize 6000k'
         . ' -profile:v baseline -level 3.1'
         . ' -pix_fmt yuv420p'
         . ' -c:a aac -b:a 128k -ar 44100 -ac 2'
         . ' -movflags +faststart'
         . ' -avoid_negative_ts make_zero'
         . ' -y ' . escapeshellarg($outFile)
         . ' 2>&1';

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outFile) || filesize($outFile) < 1000) {
        $errDetail = implode("\n", array_slice($output, -10));
        error_log('[PomPlay clip] FFmpeg error: ' . $errDetail);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al recortar el video', 'detail' => $errDetail]);
        exit;
    }

    echo json_encode([
        'ok'       => true,
        'clip_url' => $CLIPS_URL . '/' . $clipId . '.mp4',
        'filename' => $clipId . '.mp4',
        'size'     => filesize($outFile),
        'duration' => $duration + $OUTRO_DUR,
        'start'    => $start,
        'end'      => $end,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// MODO 1 legacy: WebM → MP4
// ══════════════════════════════════════════════════════════════════════════
header('Content-Type: application/json');
if (!isset($_FILES['clip']) || $_FILES['clip']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No se recibió el archivo']); exit;
}
$tmpIn = $_FILES['clip']['tmp_name'];
$size  = $_FILES['clip']['size'];
if ($size < 500) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Archivo demasiado pequeño']); exit;
}
$tmpOut = tempnam(sys_get_temp_dir(), 'pomplay_') . '.mp4';
$cmd = escapeshellcmd($FFMPEG)
     . ' -i ' . escapeshellarg($tmpIn)
     . ' -c:v libx264 -preset fast -crf 23 -maxrate 2500k -bufsize 5000k'
     . ' -profile:v baseline -level 3.1 -pix_fmt yuv420p'
     . ' -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2"'
     . ' -c:a aac -b:a 128k -ar 44100 -ac 2 -movflags +faststart'
     . ' -y ' . escapeshellarg($tmpOut) . ' 2>/dev/null';
exec($cmd, $output, $returnCode);
if ($returnCode !== 0 || !file_exists($tmpOut) || filesize($tmpOut) < 1000) {
    header('Content-Type: video/webm');
    header('Content-Disposition: attachment; filename="clip_pomplay.webm"');
    header('Content-Length: ' . $size);
    readfile($tmpIn); exit;
}
$mp4Size = filesize($tmpOut);
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="clip_pomplay.mp4"');
header('Content-Length: ' . $mp4Size);
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
readfile($tmpOut);
@unlink($tmpOut);