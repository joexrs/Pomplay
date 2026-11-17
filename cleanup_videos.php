<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  cleanup_videos.php — Limpieza automática de grabaciones antiguas
 *  Se ejecuta vía Cron Job de Hostinger (hPanel → Avanzado → Cron Jobs)
 *
 *  Política de retención:
 *   - codigo_video que empieza con "V"   (pero NO con "VID") → 3 días 
 *   - codigo_video que empieza con "VID"                     → 3 días
 *
 *  Por cada registro vencido:
 *   1. Le pide al VPS que borre el archivo físico (endpoint delete_rec)
 *   2. Borra la fila de la tabla `video`
 *  Si el VPS no responde o el archivo ya no existe, IGUAL se borra el
 *  registro de BD (no queremos filas huérfanas acumulándose porque el
 *  VPS estuvo caído un día).
 * ═══════════════════════════════════════════════════════════════
 */

// The configured Hostinger cron invokes this script with PHP CLI.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('No autorizado');
}

require __DIR__ . '/conexion.php'; // ajusta la ruta si este script no queda al mismo nivel

define('VPS_API', 'https://cctv.pomplay.com.pe/cameras.php');
define('VPS_KEY', (string) env('VPS_KEY', ''));

$logFile = __DIR__ . '/cleanup_videos.log';

function logLine(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

/**
 * Extrae id_local, codigo_cancha y filename desde la video_url guardada.
 * Formato esperado: https://cctv.pomplay.com.pe/videos/{id_local}/{codigo_cancha}/{filename}
 */
function parseVideoUrl(string $url): ?array {
    $path = parse_url($url, PHP_URL_PATH); // /videos/{id_local}/{codigo_cancha}/{filename}
    if (!$path) return null;
    $parts = array_values(array_filter(explode('/', $path)));
    // $parts = ['videos', '{id_local}', '{codigo_cancha}', '{filename}']
    if (count($parts) < 4 || $parts[0] !== 'videos') return null;
    return [
        'id_local' => rawurldecode($parts[1]),
        'codigo'   => rawurldecode($parts[2]),
        'filename' => rawurldecode($parts[3]),
    ];
}

/**
 * Pide al VPS que borre el archivo físico. Devuelve true si se puede
 * continuar con el borrado en BD (éxito, o archivo ya no existía, o VPS
 * no accesible -- en este último caso igual se borra el registro para no
 * acumular filas huérfanas, pero queda logueado para revisión manual).
 */
function deleteOnVps(string $idLocal, string $codigo, string $filename): bool {
    $url = VPS_API . '?action=delete_rec'
         . '&codigo='   . urlencode($codigo)
         . '&id_local=' . urlencode($idLocal)
         . '&filename=' . urlencode($filename);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'X-VPS-Key: ' . VPS_KEY,
        ],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) {
        logLine("  ⚠ VPS inalcanzable para {$filename}: {$err} — se borrará igual el registro de BD");
        return true; // no bloquear la limpieza de BD por un VPS caído
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        logLine("  ⚠ Respuesta VPS inválida para {$filename}: " . substr($resp, 0, 200));
        return true;
    }

    if ($data['ok'] ?? false) {
        if ($data['deleted'] ?? false) {
            logLine("  ✓ Archivo borrado en VPS: {$filename}");
        } else {
            logLine("  · Archivo ya no existía en VPS: {$filename}");
        }
        return true;
    }

    // ok=false: por ejemplo, "está siendo grabado actualmente" — no borrar
    // el registro de BD en ese caso, se reintentará al día siguiente.
    logLine("  ✗ VPS rechazó el borrado de {$filename}: " . ($data['error'] ?? 'error desconocido'));
    return false;
}

/**
 * Procesa un grupo de videos vencidos (una condición de antigüedad) y
 * borra archivo + registro por cada uno.
 */
function limpiarGrupo(PDO $pdo, string $whereSql, string $etiqueta): void {
    logLine("── Procesando grupo: {$etiqueta} ──");

    $stmt = $pdo->query("
        SELECT codigo_video, video_url
        FROM video
        WHERE {$whereSql}
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        logLine("  Sin registros vencidos en este grupo.");
        return;
    }

    logLine("  " . count($rows) . " registro(s) vencido(s) encontrados.");

    foreach ($rows as $row) {
        $codigoVideo = $row['codigo_video'];
        $info = parseVideoUrl($row['video_url'] ?? '');

        if (!$info) {
            logLine("  ⚠ {$codigoVideo}: no se pudo interpretar video_url ('{$row['video_url']}'), se omite (no se borra nada)");
            continue;
        }

        $puedeBorrarBd = deleteOnVps($info['id_local'], $info['codigo'], $info['filename']);

        if (!$puedeBorrarBd) {
            logLine("  ⏭ {$codigoVideo}: se pospone el borrado de BD (reintenta mañana)");
            continue;
        }

        try {
            $del = $pdo->prepare("DELETE FROM video WHERE codigo_video = ?");
            $del->execute([$codigoVideo]);
            logLine("  ✓ Registro de BD borrado: {$codigoVideo}");
        } catch (PDOException $e) {
            logLine("  ✗ ERROR al borrar registro de BD {$codigoVideo}: " . $e->getMessage());
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────
logLine("════════ Iniciando limpieza automática de videos ════════");

// Grupo 1: prefijo "V" pero NO "VID" → 3 días de antigüedad
// (formato legado, ej. V010, V011...)
limpiarGrupo(
    $pdo,
    "codigo_video LIKE 'V%' 
     AND codigo_video NOT LIKE 'VID%' 
     AND fecha_registro <= (CURDATE() - INTERVAL 3 DAY)",
    'Prefijo "V" (legado) — retención 3 día'
);

// Grupo 2: prefijo "VID" → 3 días de antigüedad
limpiarGrupo(
    $pdo,
    "codigo_video LIKE 'VID%' 
     AND fecha_registro <= (CURDATE() - INTERVAL 3 DAY)",
    'Prefijo "VID" — retención 3 días'
);

logLine("════════ Limpieza finalizada ════════\n");
