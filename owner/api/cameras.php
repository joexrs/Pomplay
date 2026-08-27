<?php

// FIX: se necesita la sesión para obtener id_local y así distinguir entre
// locales distintos que reusan el mismo codigo_cancha (ver WHERE más abajo).
session_start();

ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include __DIR__ . '/../../conexion.php';

function jsonResponse(array $payload): void
{
    if (ob_get_length()) ob_clean();
    echo json_encode($payload);
    exit;
}

// ── Configuración ─────────────────────────────────────────────────────────────
define('VPS_API', 'https://cctv.pomplay.com.pe/cameras.php');
define('VPS_KEY', '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6');

$action = $_GET['action'] ?? '';
$codigo = trim($_GET['codigo'] ?? '');

$vpsActions = ['status', 'start_rec', 'stop_rec', 'list_rec', 'keepalive'];

if (!in_array($action, $vpsActions)) {
    jsonResponse(['ok' => false, 'error' => 'Acción no válida']);
}
if (empty($codigo) && $action !== 'keepalive') {
    jsonResponse(['ok' => false, 'error' => 'Parámetro codigo requerido']);
}

// ── Keepalive: renovar sesión sin llegar a validar id_local ni consultar BD ───
if ($action === 'keepalive') {
    // Solo necesitamos que la sesión siga activa; al llamar session_start()
    // ya se renueva el tiempo de vida del cookie de sesión.
    jsonResponse(['ok' => true, 'ts' => time()]);
}

// ── 1. Obtener TODAS las cámaras de esta cancha desde BD local ────────────────
// FIX: codigo_cancha NO es único globalmente (solo es único junto con id_local,
// ver UNIQUE KEY uq_local_codigo(id_local, codigo_cancha) en la tabla `cancha`).
// Si dos locales distintos reusan el mismo código (ej. 'C01'), la consulta
// anterior (solo WHERE c.codigo_cancha = ?) devolvía canchas de AMBOS locales,
// multiplicando el JOIN con camara y duplicando cámaras (ej. "4/4 cámaras"
// cuando en realidad eran 2). Se filtra ahora también por id_local de sesión.
$sessionLocalId = $_SESSION['id_local'] ?? null;

if (empty($sessionLocalId)) {
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
             . '&id_local=' . urlencode((string)($idLocal ?? ''))
             . '&key='      . VPS_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
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
        // rechazar la petición para evitar grabaciones duplicadas / de microsegundos.
        // Esto ocurre cuando el usuario presiona Grabar creyendo que no graba, pero
        // el VPS sí sigue grabando (fallo de red temporal en el status check).
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
            jsonResponse([
                'ok'      => false,
                'grabando'=> true,
                'error'   => 'Ya hay una grabación en curso para esta cancha. Deten la grabación actual antes de iniciar una nueva.',
                'already_recording' => true,
            ]);
        }

        $results   = callVPSParallel('start_rec', $codigo, $camaras, $idLocal);
        $ok        = false;
        $iniciadas = 0;
        $errores   = [];

        foreach ($camaras as $cam) {
            $res = $results[$cam['posicion']] ?? ['ok' => false];
            if ($res['ok'] ?? false) {
                $ok = true;
                $iniciadas++;
                actualizarGrabando($pdo, $cam['id_camara'], 1);

                // Guardar hora de inicio en sesión (para stop_rec)
                if (!isset($_SESSION['rec_codigo_video'][$codigo])) {
                    $_SESSION['rec_codigo_video'][$codigo] = [];
                }
                $_SESSION['rec_codigo_video'][$codigo][$cam['posicion']] = true;
            } else {
                $errores[] = $res['error'] ?? 'Error desconocido en cámara ' . $cam['posicion'];
            }
        }

        if ($ok) {
            // Guardar la hora exacta en que se presionó el botón "Grabar" (fallback para stop_rec)
            $_SESSION['rec_start_time'][$codigo] = date('H:i:s');
            $_SESSION['rec_start_date'][$codigo] = date('Y-m-d');
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
        $results      = callVPSParallel('stop_rec', $codigo, $camaras, $idLocal);
        $ok           = false;   // el VPS confirmó el stop de al menos 1 cámara
        $detenidas    = 0;
        $errores      = [];      // errores de comunicación con el VPS
        $dbErrores    = [];      // FIX: errores al guardar en BD, separados de $errores/$ok
        $videosGuardados = [];

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

                        // Obtener hora de inicio guardada en sesión
                        $recStartHora  = $_SESSION['rec_start_time'][$codigo] ?? date('H:i:s');
                        $recStartFecha = $_SESSION['rec_start_date'][$codigo] ?? date('Y-m-d');

                        // Verificar si ya existe un registro incompleto en BD
                        $stmtBusqueda = $pdo->prepare("
                            SELECT codigo_video FROM video 
                            WHERE id_local = :id_local 
                              AND codigo_cancha = :codigo_cancha 
                              AND id_camara = :id_camara 
                              AND estado = 0 
                            ORDER BY fecha_registro DESC, hora_partido DESC 
                            LIMIT 1
                        ");
                        $stmtBusqueda->execute([
                            ':id_local' => $idLocal,
                            ':codigo_cancha' => $codigo,
                            ':id_camara' => $cam['id_camara']
                        ]);
                        $registroExistente = $stmtBusqueda->fetch(PDO::FETCH_ASSOC);

                        if ($registroExistente) {
                            // UPDATE del registro existente
                            $pdo->prepare("
                                UPDATE video SET
                                    video_url = :vu,
                                    duracion  = :dur,
                                    estado    = 1
                                WHERE codigo_video = :cv
                            ")->execute([
                                ':vu'  => $fi['url'],
                                ':dur' => $durSecs,
                                ':cv'  => $registroExistente['codigo_video'],
                            ]);
                            $videosGuardados[] = $registroExistente['codigo_video'];
                        } else {
                            // INSERT nuevo con la hora de inicio
                            $codigoVideo = generarCodigoVideo($pdo);
                            $hora  = str_replace('-', ':', $recStartHora);
                            $desc  = 'Grabación ' . date('d/m/Y H:i', strtotime("$recStartFecha $hora"))
                                   . ' — Cam ' . $cam['posicion'];

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
                                ':cv'  => $codigoVideo,
                                ':il'  => $idLocal,
                                ':f'   => $recStartFecha,
                                ':d'   => $desc,
                                ':h'   => $hora,
                                ':cc'  => $codigo,
                                ':vu'  => $fi['url'],
                                ':dur' => $durSecs,
                                ':ic'  => $cam['id_camara'],
                            ]);
                            $videosGuardados[] = $codigoVideo;
                        }
                    } catch (PDOException $e) {
                        error_log("ERROR UPDATE/INSERT video cam {$cam['posicion']}: " . $e->getMessage());
                        $dbErrores[] = "Cam {$cam['posicion']}: " . $e->getMessage();
                    }
                } else {
                    // El VPS no retornó file_info — marcar registro como incompleto si existe
                    $codigoVideoExistente = $_SESSION['rec_codigo_video'][$codigo][$cam['posicion']] ?? null;
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
                $errores[] = $res['error'] ?? 'Error en cámara ' . $cam['posicion'];
            }
        }

        // Limpiar hora de inicio guardada en sesión
        unset(
            $_SESSION['rec_start_time'][$codigo],
            $_SESSION['rec_start_date'][$codigo],
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
             . '&id_local=' . urlencode((string)($idLocal ?? ''))
             . '&key='      . VPS_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $resp = curl_exec($ch);
        curl_close($ch);

        if (ob_get_length()) ob_clean();
        echo $resp ?: json_encode(['ok' => false, 'error' => 'Sin respuesta del VPS']);
        exit;
}