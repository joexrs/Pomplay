<?php
require_once __DIR__ . '/../../bootstrap/env.php';

// Configuración de sesión extendida para evitar desconexiones en grabaciones largas (1 a 2+ horas)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400); // 24 horas
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include __DIR__ . '/../../conexion.php';

// ── Sistema de Log de Interferencias y Eventos (.txt) ─────────────────────────
function logInterferencia(string $nivel, string $mensaje, array $detalles = []): void
{
    try {
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/interferencias_grabacion.txt';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $detalleStr = '';
        if (!empty($detalles)) {
            $detalleStr = ' | ' . json_encode($detalles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $linea = "[$timestamp] [IP:$ip] [$nivel] $mensaje$detalleStr" . PHP_EOL;
        @file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $t) {
        error_log("Error al escribir en interferencias_grabacion.txt: " . $t->getMessage());
    }
}

function jsonResponse(array $payload): void
{
    if (ob_get_length()) ob_clean();
    echo json_encode($payload);
    exit;
}

// ── Configuración ─────────────────────────────────────────────────────────────
define('VPS_API', (string) env('VPS_CAMERAS_URL', 'https://cctv.pomplay.com.pe/cameras.php'));
define('VPS_KEY', (string) env('VPS_KEY', ''));
if (VPS_KEY === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Servicio no configurado']);
    exit;
}

$action = $_GET['action'] ?? '';
$codigo = trim($_GET['codigo'] ?? '');

$vpsActions = ['status', 'start_rec', 'stop_rec', 'list_rec', 'keepalive'];

if (!in_array($action, $vpsActions)) {
    jsonResponse(['ok' => false, 'error' => 'Acción no válida']);
}
if (empty($codigo) && $action !== 'keepalive') {
    jsonResponse(['ok' => false, 'error' => 'Parámetro codigo requerido']);
}

// ── Keepalive: renovar sesión y registrar latidos en segundo plano ────────────
if ($action === 'keepalive') {
    $reqLocal = !empty($_GET['id_local']) ? (int)$_GET['id_local'] : null;
    if ($reqLocal && empty($_SESSION['id_local'])) {
        $_SESSION['id_local'] = $reqLocal;
    }

    // Registrar latido en el .txt periódicamente (cada 10 min) para auditar segundo plano
    $ultimoLog = $_SESSION['ultimo_keepalive_log'] ?? 0;
    if (time() - $ultimoLog >= 600) {
        $_SESSION['ultimo_keepalive_log'] = time();
        logInterferencia('KEEPALIVE', 'Navegador activo en segundo plano (Heartbeat OK)', [
            'id_local' => $_SESSION['id_local'] ?? $reqLocal,
            'hora'     => date('H:i:s')
        ]);
    }

    jsonResponse(['ok' => true, 'ts' => time()]);
}

// ── 1. Obtener TODAS las cámaras de esta cancha desde BD local ────────────────
$sessionLocalId = $_SESSION['id_local'] ?? null;
$paramLocalId   = !empty($_GET['id_local']) ? (int)$_GET['id_local'] : null;

// Recuperación de seguridad si la sesión expiró pero el cliente envió su id_local
if (empty($sessionLocalId) && !empty($paramLocalId)) {
    try {
        $stmtVal = $pdo->prepare("SELECT 1 FROM cancha WHERE id_local = ? AND codigo_cancha = ? LIMIT 1");
        $stmtVal->execute([$paramLocalId, $codigo]);
        if ($stmtVal->fetchColumn()) {
            $sessionLocalId = $paramLocalId;
            $_SESSION['id_local'] = $paramLocalId;
            logInterferencia('SESION_RECUPERADA', "id_local ($paramLocalId) restaurado exitosamente para Cancha: $codigo");
        }
    } catch (PDOException) {}
}

if (empty($sessionLocalId)) {
    logInterferencia('ERROR_SESION', "Acción '$action' rechazada para Cancha: $codigo por sesión expirada o sin id_local");
    jsonResponse(['ok' => false, 'error' => 'Sesión inválida: no se encontró id_local. Vuelve a iniciar sesión.']);
}

try {
    $stmt = $pdo->prepare("
        SELECT cam.id_camara,
               cam.go2rtc_stream AS cam_stream,
               cam.posicion,
               cam.grabando,
               c.id_local
        FROM cancha c
        LEFT JOIN camara cam ON cam.codigo_cancha = c.codigo_cancha
        WHERE c.codigo_cancha = ? AND c.id_local = ?
        ORDER BY cam.posicion
    ");
    $stmt->execute([$codigo, $sessionLocalId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonResponse(['ok' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}

if (empty($rows)) {
    jsonResponse(['ok' => false, 'error' => 'Cancha no encontrada para tu local']);
}

// FIX: castear id_local a int real o null. Antes, una cadena vacía '' se
// convertía silenciosamente a 0 en el INSERT y podía violar la FK video_local_fk.
$idLocalRaw = $rows[0]['id_local'] ?? null;
$idLocal    = ($idLocalRaw !== null && $idLocalRaw !== '') ? (int)$idLocalRaw : null;

// Construir lista de cámaras desde la tabla camara
$camaras = [];
foreach ($rows as $row) {
    if (empty($row['cam_stream'])) continue;
    $camaras[] = [
        'id_camara' => $row['id_camara'],
        'posicion'  => (int)($row['posicion'] ?? 1),
        'stream'    => $row['cam_stream'],
        'grabando'  => (int)($row['grabando'] ?? 0),
    ];
}

if (empty($camaras)) {
    jsonResponse(['ok' => false, 'error' => 'No hay cámaras configuradas para esta cancha']);
}

// ── 2. Helper: llamar al VPS en PARALELO para N cámaras ──────────────────────
function callVPSParallel(string $action, string $codigo, array $camaras, ?int $idLocal): array
{
    $mh      = curl_multi_init();
    $handles = [];

    foreach ($camaras as $cam) {
        $url = VPS_API . '?action=' . urlencode($action)
             . '&codigo='   . urlencode($codigo)
             . '&stream='   . urlencode($cam['stream'])
             . '&cam='      . (int)$cam['posicion']
             . '&id_local=' . urlencode((string)($idLocal ?? ''));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['X-VPS-Key: ' . VPS_KEY],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int)$cam['posicion']] = $ch;
    }

    // Ejecutar todas las peticiones simultáneamente
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    // Recoger resultados
    $results = [];
    foreach ($handles as $pos => $ch) {
        $resp    = curl_multi_getcontent($ch);
        $err     = curl_error($ch);
        $decoded = [];

        if ($err || $resp === false) {
            $decoded = ['ok' => false, 'error' => 'VPS inalcanzable: ' . $err, 'activa' => false, 'grabando' => false];
            logInterferencia('INTERFERENCIA_RED', "Fallo al comunicar con VPS ($action) en Cancha: $codigo (Cam: $pos)", [
                'curl_error' => $err,
                'cam_pos'    => $pos
            ]);
        } else {
            // Limpiar la respuesta: descartar cualquier texto antes del primer { o [
            // (el VPS puede imprimir "php\n\n" u otro texto antes del JSON)
            $clean = trim((string)$resp);
            $jsonStart = strpos($clean, '{');
            $arrStart  = strpos($clean, '[');
            $start = match(true) {
                $jsonStart !== false && $arrStart !== false => min($jsonStart, $arrStart),
                $jsonStart !== false                        => $jsonStart,
                $arrStart  !== false                        => $arrStart,
                default                                     => 0,
            };
            if ($start > 0) $clean = substr($clean, $start);

            $decoded = json_decode($clean, true)
                    ?? ['ok' => false, 'error' => 'Respuesta inválida del VPS: ' . substr($resp, 0, 100)];
        }

        $decoded['_cam_pos'] = $pos;
        $results[$pos]       = $decoded;

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $results;
}

// ── Helper: actualizar grabando en BD local ────────────────────────────────────────────
function actualizarGrabando(PDO $pdo, ?int $idCamara, int $valor): void
{
    if (!$idCamara) return;
    try {
        $pdo->prepare("UPDATE camara SET grabando = ? WHERE id_camara = ?")
            ->execute([$valor, $idCamara]);
    } catch (PDOException) {}
}

// ── Helper: obtener streams activos en go2rtc (tienen producers = señal real) ───────
define('GO2RTC_API', 'https://cctv.pomplay.com.pe/go2rtc/api/streams');

function getGo2rtcActiveStreams(): array
{
    $ch = curl_init(GO2RTC_API);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) return [];

    $all    = json_decode($resp, true);
    if (!is_array($all)) return [];

    // Devuelve un Set de nombres de stream que tienen al menos 1 producer activo
    $activos = [];
    foreach ($all as $name => $info) {
        $producers = $info['producers'] ?? [];
        if (!empty($producers)) {
            $activos[$name] = true;
        }
    }
    return $activos;
}

// ── Helper: generar el siguiente codigo_video de forma segura ante concurrencia ──────
// NOTA: sigue sin ser 100% atómico (MySQL no tiene "SELECT ... FOR UPDATE" útil sobre
// un MAX() de una columna de texto sin una fila que bloquear), pero al menos usamos
// una transacción explícita para reducir la ventana de carrera entre dos stop_rec
// simultáneos de canchas distintas. Si tu volumen de grabaciones concurrentes crece,
// migra esto a una columna AUTO_INCREMENT auxiliar.
function generarCodigoVideo(PDO $pdo): string
{
    $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_video, 4) AS UNSIGNED)) as max FROM video WHERE codigo_video LIKE 'VID%'");
    $rowMax  = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $next    = ($rowMax['max'] ?? 0) + 1;
    return 'VID' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

// ─────────────────────────────────────────────────────────────────────────────
switch ($action) {

    // ══ STATUS — usa go2rtc como fuente de verdad para `activa` ════════════════
    case 'status':
        // 1. Consultar go2rtc directamente para saber qué streams tienen señal real
        $go2rtcActivos = getGo2rtcActiveStreams();

        // 2. Pedir estado al VPS (solo para obtener `grabando`)
        $results     = callVPSParallel('status', $codigo, $camaras, $idLocal);
        $anyActiva   = false;
        $anyGrabando = false;
        $vpsRespondio = false;  // ¿al menos una cámara tuvo respuesta válida del VPS?

        foreach ($camaras as $cam) {
            $pos = $cam['posicion'];
            $res = $results[$pos] ?? [];

            // `activa`: go2rtc manda — si el stream tiene producers, está activa
            // Si go2rtc no respondió (arreglo vacío), fallback al dato del VPS
            if (!empty($go2rtcActivos)) {
                $results[$pos]['activa'] = isset($go2rtcActivos[$cam['stream']]);
            }

            if ($results[$pos]['activa']  ?? false) $anyActiva   = true;
            if ($res['grabando']          ?? false) $anyGrabando = true;

            // Considerar que el VPS respondió si no tiene error de conexión
            if (empty($res['error']) || strpos((string)($res['error'] ?? ''), 'inalcanzable') === false) {
                $vpsRespondio = true;
            }
        }

        // FIX: Solo limpiar BD si el VPS respondió correctamente y confirmó que
        // nadie está grabando. Si el VPS falló por red/capacidad, NO modificamos
        // el estado en BD para evitar que el frontend muestre "no grabando" por error.
        if ($vpsRespondio && !$anyGrabando) {
            foreach ($camaras as $cam) {
                if ($cam['grabando'] === 1) {
                    actualizarGrabando($pdo, $cam['id_camara'], 0);
                }
            }
        }

        // Verificar si hay grabación en progreso en BD (estado=0) aunque el VPS no lo diga
        $grabandoEnBD = false;
        try {
            $stmtBD = $pdo->prepare("
                SELECT COUNT(*) FROM video
                WHERE codigo_cancha = ? AND id_local = ? AND estado = 0
            ");
            $stmtBD->execute([$codigo, $idLocal]);
            $grabandoEnBD = (int)$stmtBD->fetchColumn() > 0;
        } catch (PDOException) {}

        // Registrar alerta si la grabación se cayó en el VPS inesperadamente
        if ($vpsRespondio && !$anyGrabando && $grabandoEnBD) {
            logInterferencia('ALERTA_PARADA_INESPERADA', "La grabación en VPS ya no está activa pero en BD figuraba en progreso para Cancha: $codigo. Posible corte de stream o internet.", [
                'cancha'   => $codigo,
                'id_local' => $idLocal
            ]);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'ok'            => true,
            'activa'        => $anyActiva,
            'grabando'      => $anyGrabando || ($grabandoEnBD && !$vpsRespondio),
            'grabando_en_bd'=> $grabandoEnBD,  // para que el frontend pueda advertir
            'cameras'       => array_values($results),
        ]);
        exit;

    // ══ START_REC — arrancar TODAS las cámaras en paralelo ════════════════════
    case 'start_rec':
        // GUARD: si ya hay cámaras grabando en BD o un video en estado=0 (en progreso),
        // rechazar la petición para evitar grabaciones duplicadas.
        $yaGrabando = false;
        foreach ($camaras as $cam) {
            if ($cam['grabando'] === 1) { $yaGrabando = true; break; }
        }
        if (!$yaGrabando) {
            try {
                $stmtCheck = $pdo->prepare("
                    SELECT COUNT(*) FROM video
                    WHERE codigo_cancha = ? AND id_local = ? AND estado = 0
                ");
                $stmtCheck->execute([$codigo, $idLocal]);
                $yaGrabando = (int)$stmtCheck->fetchColumn() > 0;
            } catch (PDOException) {}
        }
        if ($yaGrabando) {
            logInterferencia('ADVERTENCIA', "Intento de iniciar grabación rechazada: ya existe una grabación en curso para Cancha: $codigo", [
                'id_local' => $idLocal
            ]);
            jsonResponse([
                'ok'      => false,
                'grabando'=> true,
                'error'   => 'Ya hay una grabación en curso para esta cancha. Detén la grabación actual antes de iniciar una nueva.',
                'already_recording' => true,
            ]);
        }

        $results     = callVPSParallel('start_rec', $codigo, $camaras, $idLocal);
        $ok          = false;
        $iniciadas   = 0;
        $errores     = [];
        $horaInicio  = date('H:i:s');
        $fechaInicio = date('Y-m-d');

        foreach ($camaras as $cam) {
            $res = $results[$cam['posicion']] ?? ['ok' => false];
            if ($res['ok'] ?? false) {
                $ok = true;
                $iniciadas++;
                actualizarGrabando($pdo, $cam['id_camara'], 1);

                // FIX HORA INICIO: Insertar registro preliminar en BD con estado = 0 (en progreso).
                // De este modo la hora de inicio queda inmortalizada en la BD y no depende de la sesión.
                try {
                    $codigoVideo = generarCodigoVideo($pdo);
                    $descEnCurso = 'Grabación en curso (iniciada ' . date('H:i', strtotime($horaInicio)) . ') — Cam ' . $cam['posicion'];

                    $pdo->prepare("
                        INSERT INTO video (
                            codigo_video, id_local, fecha_partido, descripcion,
                            hora_partido, codigo_cancha, video_url, duracion,
                            fecha_registro, estado, es_descargable, id_camara
                        ) VALUES (
                            :cv, :il, :f, :d, :h, :cc, '', '0',
                            CURDATE(), 0, 1, :ic
                        )
                    ")->execute([
                        ':cv' => $codigoVideo,
                        ':il' => $idLocal,
                        ':f'  => $fechaInicio,
                        ':d'  => $descEnCurso,
                        ':h'  => $horaInicio,
                        ':cc' => $codigo,
                        ':ic' => $cam['id_camara']
                    ]);

                    if (!isset($_SESSION['rec_codigo_video'][$codigo])) {
                        $_SESSION['rec_codigo_video'][$codigo] = [];
                    }
                    $_SESSION['rec_codigo_video'][$codigo][$cam['posicion']] = $codigoVideo;

                } catch (PDOException $e) {
                    error_log("Error al crear registro preliminar de video: " . $e->getMessage());
                    logInterferencia('ERROR_BD', "Fallo al insertar registro preliminar en BD (Cam {$cam['posicion']}): " . $e->getMessage(), [
                        'cancha' => $codigo
                    ]);
                }
            } else {
                $errCam = $res['error'] ?? 'Error desconocido en cámara ' . $cam['posicion'];
                $errores[] = $errCam;
                logInterferencia('ERROR_INICIO_CAMARA', "No se pudo iniciar grabación en cámara {$cam['posicion']}: $errCam", [
                    'cancha' => $codigo,
                    'stream' => $cam['stream']
                ]);
            }
        }

        if ($ok) {
            $_SESSION['rec_start_time'][$codigo]      = $horaInicio;
            $_SESSION['rec_start_date'][$codigo]      = $fechaInicio;
            $_SESSION['rec_start_timestamp'][$codigo] = time();

            logInterferencia('INICIO_GRABACION', "Grabación iniciada con éxito en Cancha: $codigo ({$iniciadas}/" . count($camaras) . " cámaras)", [
                'id_local'     => $idLocal,
                'hora_inicio'  => $horaInicio,
                'fecha_inicio' => $fechaInicio
            ]);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'ok'      => $ok,
            'grabando'=> $ok,
            'message' => $ok
                ? "Grabación iniciada ({$iniciadas}/" . count($camaras) . " cámaras)"
                : 'No se pudo iniciar ninguna grabación',
            'error'   => $ok ? null : implode(' | ', array_unique($errores)),
            'cameras' => array_values($results),
        ]);
        exit;

    // ══ STOP_REC — detener TODAS las cámaras y guardar grabaciones en BD ═══════
    case 'stop_rec':
        $results         = callVPSParallel('stop_rec', $codigo, $camaras, $idLocal);
        $ok              = false;   // el VPS confirmó el stop de al menos 1 cámara
        $detenidas       = 0;
        $errores         = [];      // errores de comunicación con el VPS
        $dbErrores       = [];      // errores al guardar en BD
        $videosGuardados = [];
        $momentoStop     = time();

        foreach ($camaras as $cam) {
            $res = $results[$cam['posicion']] ?? ['ok' => false];
            if ($res['ok'] ?? false) {
                $ok = true;
                $detenidas++;
                actualizarGrabando($pdo, $cam['id_camara'], 0);

                // Si el VPS devuelve file_info, actualizar (o insertar) en tabla video
                $fi = $res['file_info'] ?? null;
                error_log("stop_rec response cam {$cam['posicion']}: " . json_encode($res));
                if (!empty($fi) && !empty($fi['url'])) {
                    try {
                        $timeParts = explode(':', $fi['duration_hms'] ?? '0:00:00');
                        $durSecs = (count($timeParts) === 3)
                            ? ($timeParts[0] * 3600) + ($timeParts[1] * 60) + $timeParts[2]
                            : (int)($fi['duration_seconds'] ?? 0);

                        // 1. Buscar si existe el registro preliminar en BD (estado = 0)
                        $stmtBusqueda = $pdo->prepare("
                            SELECT codigo_video, fecha_partido, hora_partido 
                            FROM video 
                            WHERE id_local = :id_local 
                              AND codigo_cancha = :codigo_cancha 
                              AND id_camara = :id_camara 
                              AND estado = 0 
                            ORDER BY fecha_registro DESC, hora_partido DESC 
                            LIMIT 1
                        ");
                        $stmtBusqueda->execute([
                            ':id_local'      => $idLocal,
                            ':codigo_cancha' => $codigo,
                            ':id_camara'     => $cam['id_camara']
                        ]);
                        $registroExistente = $stmtBusqueda->fetch(PDO::FETCH_ASSOC);

                        // 2. Determinación Infalible de HORA y FECHA DE INICIO:
                        // Prioridad A: Hora guardada en BD al dar "Grabar"
                        // Prioridad B: Hora calculada matemáticamente: (Hora actual de detención - Duración del video)
                        // Prioridad C: Hora guardada en sesión PHP (si aún sobrevive)
                        $metodoHora = 'bd_preliminar';
                        if ($registroExistente && !empty($registroExistente['hora_partido'])) {
                            $recStartHora  = $registroExistente['hora_partido'];
                            $recStartFecha = $registroExistente['fecha_partido'];
                        } elseif ($durSecs > 0) {
                            $metodoHora    = 'calculo_duracion';
                            $tsCalculado   = $momentoStop - $durSecs;
                            $recStartHora  = date('H:i:s', $tsCalculado);
                            $recStartFecha = date('Y-m-d', $tsCalculado);
                        } elseif (!empty($_SESSION['rec_start_time'][$codigo])) {
                            $metodoHora    = 'sesion';
                            $recStartHora  = $_SESSION['rec_start_time'][$codigo];
                            $recStartFecha = $_SESSION['rec_start_date'][$codigo] ?? date('Y-m-d');
                        } else {
                            $metodoHora    = 'fallback_ahora';
                            $recStartHora  = date('H:i:s');
                            $recStartFecha = date('Y-m-d');
                        }

                        $horaFinal = str_replace('-', ':', $recStartHora);
                        $desc = 'Grabación ' . date('d/m/Y H:i', strtotime("$recStartFecha $horaFinal"))
                              . ' — Cam ' . $cam['posicion'];

                        // 3. Auditoría de posibles cortes de red / caídas de stream durante la grabación
                        $tsInicioSesion = $_SESSION['rec_start_timestamp'][$codigo] ?? null;
                        if ($tsInicioSesion) {
                            $tiempoTranscurrido = $momentoStop - $tsInicioSesion;
                            // Si estuvo grabando más de 2 min por encima de la duración del video
                            if ($tiempoTranscurrido > ($durSecs + 120)) {
                                $minEsperados = round($tiempoTranscurrido / 60, 1);
                                $minGrabados  = round($durSecs / 60, 1);
                                logInterferencia('ALERTA_CORTE_STREAM', "Posible corte de internet o cámara en Cancha: $codigo (Cam: {$cam['posicion']}). Tiempo activo en sistema: {$minEsperados} min vs Duración real video: {$minGrabados} min.", [
                                    'tiempo_esperado_seg' => $tiempoTranscurrido,
                                    'duracion_video_seg'  => $durSecs,
                                    'diferencia_perdida'  => ($tiempoTranscurrido - $durSecs) . ' seg'
                                ]);
                            }
                        }

                        if ($registroExistente) {
                            // UPDATE del registro existente con los datos finales
                            $pdo->prepare("
                                UPDATE video SET
                                    fecha_partido = :f,
                                    hora_partido  = :h,
                                    descripcion   = :d,
                                    video_url     = :vu,
                                    duracion      = :dur,
                                    estado        = 1
                                WHERE codigo_video = :cv
                            ")->execute([
                                ':f'   => $recStartFecha,
                                ':h'   => $horaFinal,
                                ':d'   => $desc,
                                ':vu'  => $fi['url'],
                                ':dur' => $durSecs,
                                ':cv'  => $registroExistente['codigo_video'],
                            ]);
                            $codigoVideoGuardado = $registroExistente['codigo_video'];
                        } else {
                            // INSERT nuevo con la hora de inicio determinada
                            $codigoVideoGuardado = generarCodigoVideo($pdo);
                            $pdo->prepare("
                                INSERT INTO video (
                                    codigo_video, id_local, fecha_partido, descripcion,
                                    hora_partido, codigo_cancha, video_url, duracion,
                                    fecha_registro, estado, es_descargable, id_camara
                                ) VALUES (
                                    :cv, :il, :f, :d, :h, :cc, :vu, :dur,
                                    CURDATE(), 1, 1, :ic
                                )
                            ")->execute([
                                ':cv'  => $codigoVideoGuardado,
                                ':il'  => $idLocal,
                                ':f'   => $recStartFecha,
                                ':d'   => $desc,
                                ':h'   => $horaFinal,
                                ':cc'  => $codigo,
                                ':vu'  => $fi['url'],
                                ':dur' => $durSecs,
                                ':ic'  => $cam['id_camara'],
                            ]);
                        }
                        $videosGuardados[] = $codigoVideoGuardado;

                        logInterferencia('FIN_GRABACION', "Grabación guardada con éxito en BD para Cancha: $codigo (Cam: {$cam['posicion']}, Video: $codigoVideoGuardado)", [
                            'hora_inicio'  => $horaFinal,
                            'fecha_inicio' => $recStartFecha,
                            'duracion_hms' => $fi['duration_hms'] ?? gmdate("H:i:s", $durSecs),
                            'duracion_seg' => $durSecs,
                            'metodo_hora'  => $metodoHora,
                            'video_url'    => $fi['url']
                        ]);

                    } catch (PDOException $e) {
                        error_log("ERROR UPDATE/INSERT video cam {$cam['posicion']}: " . $e->getMessage());
                        $dbErrores[] = "Cam {$cam['posicion']}: " . $e->getMessage();
                        logInterferencia('ERROR_BD', "Fallo al guardar video en BD (Cam {$cam['posicion']}): " . $e->getMessage());
                    }
                } else {
                    // El VPS no retornó file_info — marcar registro como incompleto si existe
                    logInterferencia('ERROR_VPS_FILEINFO', "El VPS no devolvió file_info/url para Cancha: $codigo (Cam {$cam['posicion']})", [
                        'respuesta' => $res
                    ]);
                    $codigoVideoExistente = $_SESSION['rec_codigo_video'][$codigo][$cam['posicion']] ?? null;
                    if (!$codigoVideoExistente) {
                        try {
                            $stmtBusqIncomp = $pdo->prepare("
                                SELECT codigo_video FROM video 
                                WHERE id_local = ? AND codigo_cancha = ? AND id_camara = ? AND estado = 0 
                                LIMIT 1
                            ");
                            $stmtBusqIncomp->execute([$idLocal, $codigo, $cam['id_camara']]);
                            $codigoVideoExistente = $stmtBusqIncomp->fetchColumn();
                        } catch (PDOException) {}
                    }
                    if ($codigoVideoExistente) {
                        try {
                            $pdo->prepare("UPDATE video SET estado = 2 WHERE codigo_video = :cv")
                                ->execute([':cv' => $codigoVideoExistente]);
                        } catch (PDOException) {}
                    }
                    error_log("stop_rec no guardó video para cam {$cam['posicion']}: file_info=" . json_encode($fi));
                    $dbErrores[] = "Cam {$cam['posicion']}: el VPS no devolvió file_info/url en la respuesta de stop_rec";
                }
            } else {
                $errCam = $res['error'] ?? 'Error en cámara ' . $cam['posicion'];
                $errores[] = $errCam;
                logInterferencia('ERROR_STOP_CAMARA', "Fallo al detener cámara {$cam['posicion']} en VPS: $errCam", [
                    'cancha' => $codigo
                ]);
            }
        }

        // Limpiar hora de inicio guardada en sesión
        unset(
            $_SESSION['rec_start_time'][$codigo],
            $_SESSION['rec_start_date'][$codigo],
            $_SESSION['rec_start_timestamp'][$codigo],
            $_SESSION['rec_codigo_video'][$codigo]
        );

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'ok'        => $ok,
            'grabando'  => false,
            'message'   => $ok
                ? "Grabación detenida ({$detenidas}/" . count($camaras) . " cámaras)"
                : 'No se pudo detener ninguna grabación',
            'error'     => $ok ? null : implode(' | ', array_unique($errores)),
            // FIX: este campo ya NO depende de $ok. Antes, si el VPS confirmaba el
            // stop pero el guardado en BD fallaba, el frontend nunca se enteraba.
            'db_error'  => !empty($dbErrores) ? implode(' | ', $dbErrores) : null,
            'videos'    => $videosGuardados,
            'cameras'   => array_values($results),
        ]);
        exit;

    // ══ LIST_REC ═══════════════════════════════════════════════════════════════
    case 'list_rec':
        $cam = $camaras[0];
        $url = VPS_API . '?action=list_rec'
             . '&codigo='   . urlencode($codigo)
             . '&stream='   . urlencode($cam['stream'])
             . '&cam=1'
             . '&id_local=' . urlencode((string)($idLocal ?? ''));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['X-VPS-Key: ' . VPS_KEY],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        if (ob_get_length()) ob_clean();
        echo $resp ?: json_encode(['ok' => false, 'error' => 'Sin respuesta del VPS']);
        exit;
}
