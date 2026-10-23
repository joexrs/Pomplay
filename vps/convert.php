<?php
/**
 * convert.php — VPS cctv.pomplay.com.pe
 */

// ── Configuración ──────────────────────────────────────────────────────────
$vpsConfig = is_readable('/opt/cctv/.env')
    ? @parse_ini_file('/opt/cctv/.env', false, INI_SCANNER_RAW)
    : [];
$vpsConfig = is_array($vpsConfig) ? $vpsConfig : [];
$API_KEY    = (string) ($vpsConfig['VPS_KEY'] ?? '');
$FFMPEG     = '/usr/bin/ffmpeg';
$CLIPS_DIR  = __DIR__ . '/clips';
$CLIPS_URL  = rtrim((string) ($vpsConfig['CCTV_CLIPS_BASE_URL'] ?? 'https://cctv.pomplay.com.pe/clips'), '/');
$MAX_DUR    = 7200;
$KEEP_HOURS = 1;

// ── Assets ─────────────────────────────────────────────────────────────────
$WATERMARK        = '/opt/cctv/assets/watermark.png'; // logo letras blancas
$OUTRO_LOGO       = '/opt/cctv/assets/outro.png';     // logo letras negras para outro
$OUTRO_DUR        = 5;    // Segundos del outro
$TRANS_DUR        = 0.6;  // Duración del fade (clip → outro)
$WM_SCALE         = 0.2;  // Tamaño del logo sobre el clip (fracción del ancho)
$WM_OPACITY       = 0.45; // Opacidad del logo sobre el clip (0.0–1.0)
$OUTRO_LOGO_SCALE = 0.4;  // Tamaño del logo en el outro (fracción del ancho)
$OUTRO_LOGO_OPACITY = 1.0; // Opacidad del logo en el outro (0.0–1.0)

// ── Resolución de salida fija 16:9 ─────────────────────────────────────────
$OUT_W = 1280;
$OUT_H = 720;

if (!is_dir($CLIPS_DIR)) mkdir($CLIPS_DIR, 0755, true);

// ── Autenticación ──────────────────────────────────────────────────────────
$key = $_POST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
$jsonInput = [];
$rawBody = file_get_contents('php://input');
if (!empty($rawBody) && str_starts_with(trim($rawBody), '{')) {
    $jsonInput = json_decode($rawBody, true) ?? [];
    if (empty($key)) $key = $jsonInput['key'] ?? '';
}
if ($API_KEY === '' || !hash_equals($API_KEY, (string) $key)) {
    http_response_code($API_KEY === '' ? 503 : 401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $API_KEY === '' ? 'Servicio no configurado' : 'No autorizado']);
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
    $hasOutroLogo = file_exists($OUTRO_LOGO);

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
    // [2] = outro.png (si existe)
    $inputPart = ' -ss ' . escapeshellarg((string)$start)
               . ' -to ' . escapeshellarg((string)$end)
               . ' -i '  . escapeshellarg($videoUrl);

    $wmIdx = null;
    if ($hasWatermark) {
        $inputPart .= ' -i ' . escapeshellarg($WATERMARK);
        $wmIdx = 1;
    }

    $outroIdx = null;
    if ($hasOutroLogo) {
        $inputPart .= ' -i ' . escapeshellarg($OUTRO_LOGO);
        $outroIdx = $hasWatermark ? 2 : 1;
    }

    // ── Variables para el filter_complex ──────────────────────────────
    $ow  = $OUT_W;
    $oh  = $OUT_H;
    $dur = $OUTRO_DUR;
    $trans     = $TRANS_DUR;
    $wms       = $WM_SCALE;
    $wmo       = $WM_OPACITY;
    $ols       = $OUTRO_LOGO_SCALE;
    $olo       = $OUTRO_LOGO_OPACITY;
    $outroLogoW = (int)round($OUT_W * $ols);

    $videoFilter = "scale=iw*sar:ih";
    if ($zoom > 1.05) {
        $videoFilter .= ",crop=iw/{$zoom}:ih/{$zoom}"
                      . ":(iw-iw/{$zoom})/2+({$panX}):(ih-ih/{$zoom})/2+({$panY})";
    }
    $videoFilter .= ",scale={$ow}:{$oh}:force_original_aspect_ratio=decrease"
                  . ",pad={$ow}:{$oh}:(ow-iw)/2:(oh-ih)/2:color=black"
                  . ",setsar=1";

    $fadeStart = number_format(max(0.0, $duration - $TRANS_DUR), 4, '.', '');

    // ── filter_complex ────────────────────────────────────────────────
    $fc_parts = [];

    // 1. Clip: SAR + zoom + 1280×720
    $fc_parts[] = "[0:v]{$videoFilter}[clip]";

    if ($hasWatermark) {
        // 2. Watermark del clip: escala + opacidad (letras blancas)
        $fc_parts[] = "[{$wmIdx}:v]scale=iw*{$wms}:-1,format=rgba,colorchannelmixer=aa={$wmo}[wm]";

        // 3. Superponer watermark sobre el clip — esquina inferior izquierda,
        //    con un margen de 20px respecto al borde izquierdo e inferior.
        $fc_parts[] = "[clip][wm]overlay=x=20:y=H-h-20,format=yuv420p[clipwm]";
    } else {
        $fc_parts[] = "[clip]format=yuv420p[clipwm]";
    }

    // 4. Fade-out del clip a blanco
    $fc_parts[] = "[clipwm]fade=t=out:st={$fadeStart}:d={$trans}:color=white[clipfaded]";

    // 5. Fondo blanco del outro
    $fc_parts[] = "color=c=white:r=25:d={$dur}:size={$ow}x{$oh},setsar=1,format=yuv420p[outrobg]";

    if ($hasOutroLogo) {
        // 6. outro.png directo (ya tiene letras negras), escala + opacidad
        $fc_parts[] = "[{$outroIdx}:v]scale={$outroLogoW}:-1,format=rgba"
                    . ",colorchannelmixer=aa={$olo}[wmoutro]";

        // 7. Superponer logo centrado sobre fondo blanco
        $fc_parts[] = "[outrobg][wmoutro]overlay=x=(W-w)/2:y=(H-h)/2,format=yuv420p[outroraw]";
    } else {
        // Sin outro.png: outro blanco liso
        $fc_parts[] = "[outrobg]copy[outroraw]";
    }

    // 8. Fade-in del outro desde blanco
    $fc_parts[] = "[outroraw]fade=t=in:st=0:d={$trans}:color=white[outroin]";

    // 9. Concatenar clip + outro
    $fc_parts[] = "[clipfaded][outroin]concat=n=2:v=1:a=0[vout]";

    $fc = implode(';', $fc_parts);

    // ── Comando FFmpeg ─────────────────────────────────────────────────
    $cmd = escapeshellcmd($FFMPEG)
         . $inputPart
         . ' -filter_complex ' . escapeshellarg($fc)
         . ' -map "[vout]"'
         . ' -map 0:a?'
         . ' -c:v libx264 -preset fast -crf 23'
         . ' -maxrate 3000k -bufsize 6000k'
         . ' -profile:v baseline -level 3.1'
         . ' -pix_fmt yuv420p'
         . ' -aspect 16:9'
         . ' -c:a aac -b:a 128k -ar 44100 -ac 2'
         . ' -movflags +faststart'
         . ' -avoid_negative_ts make_zero'
         . ' -y ' . escapeshellarg($outFile)
         . ' 2>&1';

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outFile) || filesize($outFile) < 1000) {
        $errDetail = implode("\n", array_slice($output, -15));
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
        'width'    => $OUT_W,
        'height'   => $OUT_H,
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
     . ' -vf "scale=iw*sar:ih,scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1"'
     . ' -aspect 16:9'
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
