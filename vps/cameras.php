<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  cameras.php — VPS API para go2rtc + grabacion con ffmpeg
 *  Ruta en VPS: /opt/cctv/api/cameras.php
 *  URL publica:  https://cctv.pomplay.com.pe/cameras.php
 * ═══════════════════════════════════════════════════════════════
 */
date_default_timezone_set('America/Lima'); // Hora de Perú (UTC-5, sin horario de verano)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// ── Configuracion ────────────────────────────────────────────────
$vpsConfig = is_readable('/opt/cctv/.env')
    ? @parse_ini_file('/opt/cctv/.env', false, INI_SCANNER_RAW)
    : [];
$vpsConfig = is_array($vpsConfig) ? $vpsConfig : [];
define('API_KEY',    (string) ($vpsConfig['VPS_KEY'] ?? ''));
define('GO2RTC_API', 'http://localhost:1984/api');
define('RTSP_BASE',  'rtsp://localhost:8554');
define('REC_DIR',    '/opt/cctv/recordings');
define('PID_DIR',    '/opt/cctv/pids');
define('FFMPEG',     '/usr/bin/ffmpeg');
define('FFPROBE',    '/usr/bin/ffprobe');
define('BASE_URL',   rtrim((string) ($vpsConfig['CCTV_BASE_URL'] ?? 'https://cctv.pomplay.com.pe/videos'), '/'));
// ── Helpers ──────────────────────────────────────────────────────
function jsonResp(array $data): void {
    echo json_encode($data);
    exit;
}
/**
 * Ruta del archivo PID.
 * Formato del contenido: "PID\n/ruta/completa/al/archivo.mp4"
 */
function pidFilePath(string $codigo, int $cam): string {
    return PID_DIR . '/rec_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $codigo) . '_cam' . $cam . '.pid';
}
function readPidFile(string $codigo, int $cam): array {
    $pf = pidFilePath($codigo, $cam);
    if (!file_exists($pf)) return ['pid' => 0, 'file' => ''];
    $parts = explode("\n", trim(file_get_contents($pf)), 2);
    return [
        'pid'  => (int)trim($parts[0] ?? '0'),
        'file' => trim($parts[1] ?? ''),
    ];
}
function isRecording(string $codigo, int $cam): bool {
    $data = readPidFile($codigo, $cam);
    if ($data['pid'] <= 0) return false;
    return file_exists('/proc/' . $data['pid']);
}
function getDurationSecs(string $filepath): int {
    if (!file_exists($filepath)) return 0;
    $cmd = FFPROBE
         . ' -v quiet'
         . ' -show_entries format=duration'
         . ' -of csv=p=0'
         . ' ' . escapeshellarg($filepath)
         . ' 2>/dev/null';
    $out = trim(shell_exec($cmd) ?? '0');
    return (int)floatval($out);
}
function secsToHms(int $secs): string {
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    return sprintf('%d:%02d:%02d', $h, $m, $s);
}
function go2rtcStreamStatus(string $streamName): array {
    $url = GO2RTC_API . '/streams?src=' . urlencode($streamName);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return ['activa' => false, 'producers' => 0];
    $data = json_decode($resp, true);
    if (!is_array($data)) return ['activa' => false, 'producers' => 0];
    // go2rtc puede devolver { streamName: {...} } o directamente { producers: [...] }
    $streamData = isset($data['producers']) ? $data : ($data[$streamName] ?? []);
    $prod = count($streamData['producers'] ?? []);
    return ['activa' => $prod > 0, 'producers' => $prod];
}
// ── Validaciones ─────────────────────────────────────────────────
$key = $_SERVER['HTTP_X_VPS_KEY'] ?? '';
if (API_KEY === '' || !hash_equals(API_KEY, (string) $key)) {
    http_response_code(API_KEY === '' ? 503 : 401);
    jsonResp(['ok' => false, 'error' => API_KEY === '' ? 'Servicio no configurado' : 'No autorizado']);
}
$action   = trim($_GET['action']   ?? '');
$codigo   = trim($_GET['codigo']   ?? '');
$stream   = trim($_GET['stream']   ?? '');
$cam      = max(1, (int)($_GET['cam']      ?? 1));
$id_local = trim($_GET['id_local'] ?? 'local');
if (!in_array($action, ['status', 'start_rec', 'stop_rec', 'list_rec', 'check_processing', 'delete_rec'], true))
    jsonResp(['ok' => false, 'error' => 'Accion no valida: ' . $action]);
if (empty($codigo))
    jsonResp(['ok' => false, 'error' => 'Parametro codigo requerido']);
// ── Acciones ─────────────────────────────────────────────────────
switch ($action) {
    // ══ STATUS ═══════════════════════════════════════════════════
    case 'status':
        if (empty($stream)) jsonResp(['ok' => false, 'error' => 'Parametro stream requerido']);
        $st = go2rtcStreamStatus($stream);
        jsonResp([
            'ok'        => true,
            'activa'    => $st['activa'],
            'grabando'  => isRecording($codigo, $cam),
            'producers' => $st['producers'],
            'stream'    => $stream,
        ]);
    // ══ START_REC ════════════════════════════════════════════════
    case 'start_rec':
        if (empty($stream)) jsonResp(['ok' => false, 'error' => 'Parametro stream requerido']);
        // Si ya hay un proceso activo, no duplicar
        if (isRecording($codigo, $cam)) {
            $pd = readPidFile($codigo, $cam);
            jsonResp(['ok' => true, 'grabando' => true, 'message' => 'Ya estaba grabando', 'pid' => $pd['pid']]);
        }
        // Verificar señal en go2rtc
        $st = go2rtcStreamStatus($stream);
        if (!$st['activa'])
            jsonResp(['ok' => false, 'error' => 'Sin senal activa en go2rtc para stream: ' . $stream]);
        // Crear directorio de grabaciones
        $recPath = REC_DIR . '/' . $id_local . '/' . $codigo;
        if (!is_dir($recPath) && !mkdir($recPath, 0755, true))
            jsonResp(['ok' => false, 'error' => 'No se pudo crear directorio: ' . $recPath]);
        // FIX: timestamp con milisegundos para evitar colisión de nombre de
        // archivo si start_rec se dispara dos veces muy rápido (doble click,
        // reintento del frontend, etc.)
        $timestamp = date('Y-m-d_H-i-s') . '_' . substr(sprintf('%03d', (int)(microtime(true) * 1000) % 1000), 0, 3);
        $filename  = $recPath . '/' . $timestamp . '_cam' . $cam . '.mp4';
        $rtspUrl   = RTSP_BASE . '/' . $stream;
        $logFile   = '/opt/cctv/logs/ffmpeg_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $codigo) . '_cam' . $cam . '.log';
        // Lanzar ffmpeg en background y obtener PID
        //
        // FIX: se agregan varias correcciones respecto a la versión anterior:
        //  - analyzeduration/probesize altos: el stream de origen es HEVC y a
        //    veces ffmpeg no lograba leer los parámetros del códec a tiempo,
        //    lo que producía archivos de 0 segundos ("Could not find codec
        //    parameters" / "dimensions not set").
        //  - err_detect ignore_err + discardcorrupt: tolera paquetes RTP
        //    corruptos (bad cseq / Missed some packets) sin abortar.
        //  - Se re-encodea a H.264 1080p en vez de copiar el HEVC original:
        //    reduce el peso del archivo, evita los errores "Multi-layer HEVC
        //    coding is not implemented", y ya no requiere que recompress.sh
        //    haga un re-encode completo después (solo un remux liviano).
        //  - preset ultrafast: minimiza el uso de CPU durante la grabación
        //    en vivo, ya que el servidor tiene recursos limitados.
        //  - Sin +faststart aquí: ese remux se aplica en recompress.sh sobre
        //    el archivo ya cerrado, para no arriesgar corrupción si stop_rec
        //    tiene que forzar el cierre del proceso.
        $cmd = FFMPEG
             . ' -nostdin -rtsp_transport tcp'
             . ' -analyzeduration 10000000 -probesize 10000000'
             . ' -err_detect ignore_err -fflags +discardcorrupt'
             . ' -i '    . escapeshellarg($rtspUrl)
             . ' -vf scale=-2:1080'
             // FIX: se cambia preset de "ultrafast" a "veryfast" (mejor
             // compresión, sigue siendo liviano de CPU) y se agrega un techo
             // de bitrate explícito (-maxrate/-bufsize). Sin esto, con -crf
             // 23 solo, videos de 1h estaban saliendo en ~10 Mbps / 5.6 GB
             // -- muy pesados para cargar y hacer seek con fluidez. Con el
             // techo de 2.5 Mbps, un video de 1h queda en ~1.1-1.3 GB,
             // manteniendo buena calidad visual para este tipo de contenido
             // (cámara fija, sin cambios de escena).
             . ' -c:v libx264 -preset veryfast -crf 23'
             . ' -maxrate 2500k -bufsize 5000k'
             // FIX: GOP corto para que el seek/adelantar en el reproductor
             // sea casi instantáneo. Sin esto, libx264 usa un intervalo de
             // keyframes por defecto de hasta 250 frames (~16s a 15fps), y
             // el navegador tiene que descargar/decodificar desde el
             // keyframe anterior cada vez que el usuario adelanta el video.
             // -g 30: un keyframe cada 30 frames (~2s a 15fps).
             // -keyint_min 30: no permite keyframes más seguido de lo fijado.
             // -sc_threshold 0: desactiva keyframes automáticos por cambio
             //   de escena, que si no, desalinean el GOP fijo.
             . ' -g 30 -keyint_min 30 -sc_threshold 0'
             . ' -c:a aac -b:a 128k'
             . ' '       . escapeshellarg($filename)
             . ' >> '    . escapeshellarg($logFile) . ' 2>&1'
             . ' & echo $!';
        $pid = (int)trim(shell_exec($cmd));
        if ($pid <= 0)
            jsonResp(['ok' => false, 'error' => 'No se pudo iniciar ffmpeg. Ruta: ' . FFMPEG]);
        // Guardar PID + ruta del archivo en pid file
        if (file_put_contents(pidFilePath($codigo, $cam), $pid . "\n" . $filename) === false)
            jsonResp(['ok' => false, 'error' => 'No se pudo escribir pid file en: ' . PID_DIR]);
        jsonResp([
            'ok'       => true,
            'grabando' => true,
            'pid'      => $pid,
            'file'     => basename($filename),
            'message'  => 'Grabacion iniciada',
        ]);
    // ══ STOP_REC ═════════════════════════════════════════════════
    case 'stop_rec':
        $pidData = readPidFile($codigo, $cam);
        $pid      = $pidData['pid'];
        $filepath = $pidData['file'];
        if ($pid <= 0 || !file_exists('/proc/' . $pid)) {
            // Limpiar pid file huerfano si existe
            if (file_exists(pidFilePath($codigo, $cam))) unlink(pidFilePath($codigo, $cam));
            jsonResp(['ok' => true, 'grabando' => false, 'message' => 'No habia grabacion activa']);
        }
        // Eliminar pid file antes de matar el proceso
        unlink(pidFilePath($codigo, $cam));
        // SIGTERM → ffmpeg finaliza el MP4 correctamente
        posix_kill($pid, 15);
        // FIX: se amplía el margen de espera de 4s a 10s antes de forzar
        // SIGKILL. Sin +faststart el cierre debería ser rápido, pero con
        // libx264 en preset ultrafast aún necesita terminar de escribir el
        // buffer de encoding pendiente.
        $waited = 0;
        while (file_exists('/proc/' . $pid) && $waited < 100) {
            usleep(100000); // 100ms
            $waited++;
        }
        // Forzar si no terminó en 10s
        if (file_exists('/proc/' . $pid)) {
            error_log("stop_rec: forzando SIGKILL PID {$pid} codigo={$codigo} cam={$cam} tras 10s sin cerrar");
            posix_kill($pid, 9);
            usleep(500000);
        }
        // Esperar que el archivo quede listo
        sleep(1);

        // ── Disparar remux con faststart en background (fix compatibilidad iOS) ──
        // NOTA: ya no es un re-encode completo (el archivo ya sale en H.264
        // 1080p desde la grabación en vivo), recompress.sh ahora solo hace
        // un remux liviano para mover el moov atom al inicio del archivo.
        $recompressLog = '/opt/cctv/logs/recompress_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $codigo) . '_cam' . $cam . '.log';
        $recompressCmd = 'nohup /opt/cctv/scripts/recompress.sh '
                       . escapeshellarg($filepath)
                       . ' >> ' . escapeshellarg($recompressLog) . ' 2>&1 &';
        shell_exec($recompressCmd);
        // ─────────────────────────────────────────────────────────────────────────

        // Obtener duración con ffprobe
        $durSecs = getDurationSecs($filepath);
        $durHms  = secsToHms($durSecs);
        $fileSize = file_exists($filepath) ? filesize($filepath) : 0;

        // FIX: dejar rastro explícito en el log si la duración salió en 0 o
        // inválida, para diagnosticar sin tener que adivinar la próxima vez.
        if ($durSecs <= 0) {
            error_log("stop_rec: ADVERTENCIA duración 0 o inválida para {$filepath} (codigo={$codigo} cam={$cam})");
        }

        // Construir URL pública del video
        $videoUrl = BASE_URL
                  . '/' . rawurlencode($id_local)
                  . '/' . rawurlencode($codigo)
                  . '/' . rawurlencode(basename($filepath));
        jsonResp([
            'ok'       => true,
            'grabando' => false,
            'message'  => 'Grabacion detenida',
            'file_info' => [
                'filename'         => basename($filepath),
                'url'              => $videoUrl,
                'duration_seconds' => $durSecs,
                'duration_hms'     => $durHms,
                'size'             => $fileSize,
                'fecha'            => date('Y-m-d'),
                'hora'             => date('H:i:s'),
            ],
        ]);
    // ══ LIST_REC ═════════════════════════════════════════════════
    case 'list_rec':
        $recPath = REC_DIR . '/' . $id_local . '/' . $codigo;
        if (!is_dir($recPath)) jsonResp(['ok' => true, 'recordings' => []]);
        $files = glob($recPath . '/*.mp4') ?: [];
        rsort($files);
        $list = array_map(fn($f) => [
            'filename' => basename($f),
            'size'     => filesize($f),
            'date'     => date('Y-m-d H:i:s', filemtime($f)),
            'url'      => BASE_URL
                        . '/' . rawurlencode($id_local)
                        . '/' . rawurlencode($codigo)
                        . '/' . rawurlencode(basename($f)),
        ], $files);
        jsonResp(['ok' => true, 'recordings' => $list]);
    // ══ CHECK_PROCESSING ═════════════════════════════════════════
    // Verifica si un archivo de video aún se está recomprimiendo
    case 'check_processing':
        $filename = trim($_GET['filename'] ?? '');
        if (empty($filename)) jsonResp(['ok' => false, 'error' => 'Parametro filename requerido']);

        $recPath  = REC_DIR . '/' . $id_local . '/' . $codigo;
        $filepath = $recPath . '/' . basename($filename); // basename evita path traversal
        $marker   = $filepath . '.processing';

        jsonResp([
            'ok'          => true,
            'processing'  => file_exists($marker),
            'file_exists' => file_exists($filepath),
        ]);
    // ══ DELETE_REC ═══════════════════════════════════════════════
    // Borra un archivo de grabación específico. Usado por el script de
    // limpieza automática (cron en Hostinger) que aplica la política de
    // retención: 1 día para videos antiguos (prefijo "V"), 3 días para
    // videos nuevos (prefijo "VID").
    case 'delete_rec':
        $filename = trim($_GET['filename'] ?? '');
        if (empty($filename)) jsonResp(['ok' => false, 'error' => 'Parametro filename requerido']);

        // basename() evita path traversal (../../etc/passwd, etc.)
        $recPath  = REC_DIR . '/' . $id_local . '/' . $codigo;
        $filepath = $recPath . '/' . basename($filename);

        if (!file_exists($filepath)) {
            jsonResp(['ok' => true, 'deleted' => false, 'message' => 'El archivo ya no existía']);
        }

        // No borrar si hay una grabación activa apuntando a este mismo
        // archivo (protección extra, no debería pasar con la política de
        // retención de días, pero evita un borrado accidental de algo en uso)
        for ($c = 1; $c <= 4; $c++) {
            $pd = readPidFile($codigo, $c);
            if ($pd['file'] === $filepath && file_exists('/proc/' . $pd['pid'])) {
                jsonResp(['ok' => false, 'error' => 'El archivo está siendo grabado actualmente, no se borra']);
            }
        }

        if (unlink($filepath)) {
            jsonResp(['ok' => true, 'deleted' => true, 'message' => 'Archivo borrado']);
        } else {
            jsonResp(['ok' => false, 'error' => 'No se pudo borrar el archivo: ' . $filepath]);
        }
}
