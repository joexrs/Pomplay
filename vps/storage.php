<?php
/**
 * storage.php — API de almacenamiento para el panel Admin de PomPlay
 * Subir este archivo al VPS en la raíz web (junto a cameras.php)
 * URL resultante: https://cctv.pomplay.com.pe/storage.php
 *
 * Acciones disponibles:
 *   - create_dir      : Crea directorio /opt/cctv/recordings/{id_local}/{codigo_cancha}/
 *   - upload_chunk    : Recibe un fragmento (chunk) de 1MB del archivo de video
 *   - finalize_upload : Ensambla todos los chunks y devuelve la video_url final
 *   - check_dir       : Verifica si existe un directorio (debug)
 *
 * Flujo de subida para archivos grandes (4GB+):
 *   1. El navegador divide el archivo en chunks de 10MB
 *   2. Envía cada chunk a upload_chunk con un upload_id único
 *   3. Al terminar, llama a finalize_upload para ensamblar el archivo final
 *   4. Recibe la video_url definitiva para guardar en BD
 */

ob_start();

// ── CORS — debe ir antes de cualquier otra salida ──────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: X-VPS-Key, Content-Type, Accept, Origin, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // cache preflight 24h
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Configuración ──────────────────────────────────────────────────────────────
define('VPS_KEY',         '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6');
define('RECORDINGS_BASE', '/opt/cctv/recordings');
define('CCTV_PUBLIC_URL', 'https://cctv.pomplay.com.pe/videos');
define('CHUNKS_TMP_DIR',  '/tmp/pomplay_chunks'); // directorio temporal para chunks

// ── Helper: respuesta JSON ─────────────────────────────────────────────────────
function jsonResponse(array $payload, int $code = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// ── Autenticación ──────────────────────────────────────────────────────────────
$receivedKey = $_SERVER['HTTP_X_VPS_KEY']
    ?? $_POST['key']
    ?? $_GET['key']
    ?? '';

if (!hash_equals(VPS_KEY, $receivedKey)) {
    jsonResponse(['ok' => false, 'error' => 'No autorizado'], 401);
}

// ── Acción ─────────────────────────────────────────────────────────────────────
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {

    // ══ CREATE_DIR ═════════════════════════════════════════════════════════════
    // Crea /opt/cctv/recordings/{id_local}/ y opcionalmente /{codigo_cancha}/
    case 'create_dir':
        $id_local      = trim($_POST['id_local'] ?? '');
        $codigo_cancha = trim($_POST['codigo_cancha'] ?? '');

        if (empty($id_local) || !is_numeric($id_local)) {
            jsonResponse(['ok' => false, 'error' => 'id_local inválido']);
        }

        $id_local      = (int) $id_local;
        $codigo_cancha = preg_replace('/[^A-Za-z0-9]/', '', $codigo_cancha);

        $localPath = RECORDINGS_BASE . '/' . $id_local;
        if (!is_dir($localPath)) {
            if (!mkdir($localPath, 0755, true)) {
                jsonResponse(['ok' => false, 'error' => 'No se pudo crear: ' . $localPath]);
            }
        }

        $createdPaths = [$localPath];

        if (!empty($codigo_cancha)) {
            $canchaPath = $localPath . '/' . $codigo_cancha;
            if (!is_dir($canchaPath)) {
                if (!mkdir($canchaPath, 0755, true)) {
                    jsonResponse(['ok' => false, 'error' => 'No se pudo crear: ' . $canchaPath]);
                }
            }
            $createdPaths[] = $canchaPath;
        }

        jsonResponse([
            'ok'      => true,
            'message' => 'Directorios verificados/creados correctamente',
            'paths'   => $createdPaths,
        ]);
        break;

    // ══ UPLOAD_CHUNK ═══════════════════════════════════════════════════════════
    // Recibe un fragmento del archivo. El navegador envía los chunks en orden.
    //
    // Parámetros POST requeridos:
    //   upload_id     : identificador único de esta subida (UUID generado en el cliente)
    //   chunk_index   : índice del chunk actual (0-based)
    //   total_chunks  : total de chunks del archivo
    //   orig_ext      : extensión original del archivo (mp4, avi, etc.)
    //   chunk         : el fragmento del archivo (file input)
    case 'upload_chunk':
        $upload_id    = preg_replace('/[^A-Za-z0-9_-]/', '', trim($_POST['upload_id']    ?? ''));
        $chunk_index  = (int) ($_POST['chunk_index']  ?? -1);
        $total_chunks = (int) ($_POST['total_chunks'] ?? 0);
        $orig_ext     = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($_POST['orig_ext'] ?? '')));

        if (empty($upload_id)) {
            jsonResponse(['ok' => false, 'error' => 'upload_id requerido']);
        }
        if ($chunk_index < 0 || $total_chunks <= 0) {
            jsonResponse(['ok' => false, 'error' => 'chunk_index / total_chunks inválidos']);
        }
        if (!in_array($orig_ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'ts'], true)) {
            jsonResponse(['ok' => false, 'error' => 'Extensión no permitida: ' . $orig_ext]);
        }
        if (empty($_FILES['chunk']['tmp_name']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['chunk']['error'] ?? -1;
            jsonResponse(['ok' => false, 'error' => 'Error recibiendo chunk (código: ' . $errCode . ')']);
        }

        // Crear directorio temporal para los chunks de esta subida
        $chunkDir = CHUNKS_TMP_DIR . '/' . $upload_id;
        if (!is_dir($chunkDir)) {
            if (!mkdir($chunkDir, 0755, true)) {
                jsonResponse(['ok' => false, 'error' => 'No se pudo crear directorio temporal']);
            }
        }

        // Guardar el chunk con nombre ordenable: chunk_0000, chunk_0001, ...
        $chunkFile = $chunkDir . '/chunk_' . str_pad($chunk_index, 6, '0', STR_PAD_LEFT);
        if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
            jsonResponse(['ok' => false, 'error' => 'No se pudo guardar el chunk ' . $chunk_index]);
        }

        // Contar cuántos chunks se han recibido hasta ahora
        $receivedChunks = count(glob($chunkDir . '/chunk_*'));

        jsonResponse([
            'ok'             => true,
            'chunk_index'    => $chunk_index,
            'total_chunks'   => $total_chunks,
            'received_chunks'=> $receivedChunks,
            'complete'       => ($receivedChunks >= $total_chunks),
        ]);
        break;

    // ══ FINALIZE_UPLOAD ════════════════════════════════════════════════════════
    // Ensambla todos los chunks en el archivo final y lo mueve al destino.
    //
    // Parámetros POST requeridos:
    //   upload_id     : el mismo upload_id usado en upload_chunk
    //   id_local      : ID del local
    //   codigo_cancha : código de la cancha
    //   fecha         : dd-MM-yyyy
    //   hora          : HH-mm-ss
    //   orig_ext      : extensión original (mp4, etc.)
    //   total_chunks  : total de chunks esperados
    case 'finalize_upload':
        $upload_id     = preg_replace('/[^A-Za-z0-9_-]/', '', trim($_POST['upload_id']    ?? ''));
        $id_local      = (int) ($_POST['id_local']      ?? 0);
        $codigo_cancha = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['codigo_cancha']  ?? ''));
        $fecha         = trim($_POST['fecha']            ?? ''); // dd-MM-yyyy
        $hora          = trim($_POST['hora']             ?? ''); // HH-mm-ss
        $orig_ext      = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($_POST['orig_ext'] ?? '')));
        $total_chunks  = (int) ($_POST['total_chunks']  ?? 0);

        // Validaciones
        if (empty($upload_id)) {
            jsonResponse(['ok' => false, 'error' => 'upload_id requerido']);
        }
        if ($id_local <= 0) {
            jsonResponse(['ok' => false, 'error' => 'id_local inválido']);
        }
        if (empty($codigo_cancha)) {
            jsonResponse(['ok' => false, 'error' => 'codigo_cancha requerido']);
        }
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha)) {
            jsonResponse(['ok' => false, 'error' => 'fecha inválida (esperado: dd-MM-yyyy)']);
        }
        if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $hora)) {
            jsonResponse(['ok' => false, 'error' => 'hora inválida (esperado: HH-mm-ss)']);
        }
        if (!in_array($orig_ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'ts'], true)) {
            jsonResponse(['ok' => false, 'error' => 'Extensión no permitida']);
        }

        // Verificar que existan todos los chunks
        $chunkDir = CHUNKS_TMP_DIR . '/' . $upload_id;
        if (!is_dir($chunkDir)) {
            jsonResponse(['ok' => false, 'error' => 'No se encontraron chunks para este upload_id']);
        }

        $chunkFiles = glob($chunkDir . '/chunk_*');
        sort($chunkFiles); // orden ascendente

        if (count($chunkFiles) < $total_chunks) {
            jsonResponse([
                'ok'    => false,
                'error' => 'Chunks incompletos: se recibieron ' . count($chunkFiles) . ' de ' . $total_chunks,
            ]);
        }

        // Verificar/crear directorio de destino
        $destDir = RECORDINGS_BASE . '/' . $id_local . '/' . $codigo_cancha;
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                jsonResponse(['ok' => false, 'error' => 'No se pudo crear el directorio destino: ' . $destDir]);
            }
        }

        // Construir nombre del archivo: dd-MM-yyyy_HH-mm-ss.ext
        $fileName = $fecha . '_' . $hora . '.' . $orig_ext;
        $destPath = $destDir . '/' . $fileName;

        // Si ya existe un archivo con ese nombre, agregar sufijo único
        if (file_exists($destPath)) {
            $micro    = substr(str_replace('.', '', (string)microtime(true)), -4);
            $fileName = $fecha . '_' . $hora . '_' . $micro . '.' . $orig_ext;
            $destPath = $destDir . '/' . $fileName;
        }

        // Ensamblar chunks en el archivo final
        $finalHandle = fopen($destPath, 'wb');
        if (!$finalHandle) {
            jsonResponse(['ok' => false, 'error' => 'No se pudo crear el archivo final en: ' . $destPath]);
        }

        foreach ($chunkFiles as $chunkFile) {
            $chunkHandle = fopen($chunkFile, 'rb');
            if (!$chunkHandle) {
                fclose($finalHandle);
                jsonResponse(['ok' => false, 'error' => 'No se pudo leer el chunk: ' . basename($chunkFile)]);
            }
            while (!feof($chunkHandle)) {
                fwrite($finalHandle, fread($chunkHandle, 8192)); // leer de 8KB en 8KB
            }
            fclose($chunkHandle);
        }
        fclose($finalHandle);

        // Limpiar chunks temporales
        foreach ($chunkFiles as $chunkFile) {
            @unlink($chunkFile);
        }
        @rmdir($chunkDir);

        // Verificar que el archivo se creó correctamente
        if (!file_exists($destPath)) {
            jsonResponse(['ok' => false, 'error' => 'El archivo final no se creó correctamente']);
        }

        $fileSize = filesize($destPath);
        $videoUrl = CCTV_PUBLIC_URL . '/' . $id_local . '/' . $codigo_cancha . '/' . $fileName;

        jsonResponse([
            'ok'        => true,
            'message'   => 'Archivo ensamblado y guardado correctamente',
            'filename'  => $fileName,
            'path'      => $destPath,
            'video_url' => $videoUrl,
            'size_bytes'=> $fileSize,
            'size_mb'   => round($fileSize / (1024 * 1024), 2),
        ]);
        break;

    // ══ CHECK_DIR ══════════════════════════════════════════════════════════════
    case 'check_dir':
        $id_local      = (int) ($_POST['id_local']      ?? $_GET['id_local']      ?? 0);
        $codigo_cancha = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['codigo_cancha'] ?? $_GET['codigo_cancha'] ?? ''));

        $localPath  = RECORDINGS_BASE . '/' . $id_local;
        $canchaPath = !empty($codigo_cancha) ? $localPath . '/' . $codigo_cancha : null;

        jsonResponse([
            'ok'           => true,
            'local_exists' => is_dir($localPath),
            'local_path'   => $localPath,
            'cancha_exists'=> $canchaPath ? is_dir($canchaPath) : null,
            'cancha_path'  => $canchaPath,
        ]);
        break;

    default:
        jsonResponse(['ok' => false, 'error' => 'Acción no válida: ' . $action], 400);
}
