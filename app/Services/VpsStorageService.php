<?php

namespace App\Services;

/**
 * VpsStorageService
 *
 * Cliente HTTP centralizado para comunicarse con storage.php en el VPS.
 * Usa cURL con el mismo patrón que cameras.php (VPS_KEY para autenticación).
 *
 * URL del VPS: https://cctv.pomplay.com.pe/storage.php
 */
class VpsStorageService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(env('VPS_STORAGE_URL', 'https://cctv.pomplay.com.pe/storage.php'), '/');
        $this->apiKey = env('VPS_KEY', '5a51e68bb363b9f212f631eccca1ac1b7a15c6f3c200991f6bcebfcc15524fc6');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Crear directorio del Local: /opt/cctv/recordings/{id_local}/
    // ─────────────────────────────────────────────────────────────────────────
    public function createLocalDirectory(int $id_local): bool
    {
        $response = $this->post('create_dir', [
            'id_local' => $id_local,
        ]);

        return $response['ok'] ?? false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Crear directorio de Cancha: /opt/cctv/recordings/{id_local}/{codigo_cancha}/
    // ─────────────────────────────────────────────────────────────────────────
    public function createCanchaDirectory(int $id_local, string $codigo_cancha): bool
    {
        $response = $this->post('create_dir', [
            'id_local'      => $id_local,
            'codigo_cancha' => $codigo_cancha,
        ]);

        return $response['ok'] ?? false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Subir video al VPS.
    // Retorna ['ok' => true, 'video_url' => '...'] o ['ok' => false, 'error' => '...']
    //
    // $fecha  : dd-MM-yyyy  (fecha del partido)
    // $hora   : HH-mm-ss    (hora del partido)
    // $tmpPath: ruta temporal del archivo ($_FILES['video_file']['tmp_name'])
    // $origName: nombre original del archivo ($_FILES['video_file']['name'])
    // ─────────────────────────────────────────────────────────────────────────
    public function uploadVideo(
        int    $id_local,
        string $codigo_cancha,
        string $fecha,
        string $hora,
        string $tmpPath,
        string $origName
    ): array {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            return ['ok' => false, 'error' => 'Archivo temporal no encontrado o no legible'];
        }

        $url = $this->apiUrl . '?key=' . urlencode($this->apiKey);

        $postFields = [
            'action'        => 'upload_video',
            'key'           => $this->apiKey,
            'id_local'      => $id_local,
            'codigo_cancha' => $codigo_cancha,
            'fecha'         => $fecha,
            'hora'          => $hora,
            'video_file'    => new \CURLFile($tmpPath, $this->getMimeType($origName), $origName),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_TIMEOUT        => 300,   // 5 minutos para archivos grandes
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'X-VPS-Key: ' . $this->apiKey,
            ],
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $resp === false) {
            return ['ok' => false, 'error' => 'Error de conexión con el VPS: ' . $err];
        }

        $decoded = $this->decodeResponse($resp);

        if (!($decoded['ok'] ?? false)) {
            return [
                'ok'    => false,
                'error' => $decoded['error'] ?? 'Error desconocido del VPS (HTTP ' . $httpCode . ')',
            ];
        }

        return $decoded;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Verificar si existen los directorios (útil para diagnóstico)
    // ─────────────────────────────────────────────────────────────────────────
    public function checkDirectory(int $id_local, string $codigo_cancha = ''): array
    {
        $response = $this->post('check_dir', [
            'id_local'      => $id_local,
            'codigo_cancha' => $codigo_cancha,
        ]);

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Construir la video_url pública a partir de los parámetros
    // (para casos donde ya se conoce el nombre del archivo)
    // ─────────────────────────────────────────────────────────────────────────
    public static function buildVideoUrl(int $id_local, string $codigo_cancha, string $filename): string
    {
        $base = rtrim(env('CCTV_BASE_URL', 'https://cctv.pomplay.com.pe/videos'), '/');
        return $base . '/' . $id_local . '/' . $codigo_cancha . '/' . $filename;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: POST al VPS con cURL (para acciones sin archivo)
    // ─────────────────────────────────────────────────────────────────────────
    private function post(string $action, array $params): array
    {
        $params['action'] = $action;
        $params['key']    = $this->apiKey;

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'X-VPS-Key: ' . $this->apiKey,
            ],
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $resp === false) {
            return ['ok' => false, 'error' => 'Error de conexión con el VPS: ' . $err];
        }

        return $this->decodeResponse($resp);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: limpiar y decodificar respuesta JSON (igual que cameras.php)
    // ─────────────────────────────────────────────────────────────────────────
    private function decodeResponse(string $resp): array
    {
        $clean     = trim($resp);
        $jsonStart = strpos($clean, '{');
        $arrStart  = strpos($clean, '[');

        $start = match (true) {
            $jsonStart !== false && $arrStart !== false => min($jsonStart, $arrStart),
            $jsonStart !== false                        => $jsonStart,
            $arrStart  !== false                        => $arrStart,
            default                                     => 0,
        };

        if ($start > 0) {
            $clean = substr($clean, $start);
        }

        return json_decode($clean, true)
            ?? ['ok' => false, 'error' => 'Respuesta inválida del VPS: ' . substr($resp, 0, 200)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: detectar MIME type por extensión
    // ─────────────────────────────────────────────────────────────────────────
    private function getMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp4'  => 'video/mp4',
            'avi'  => 'video/x-msvideo',
            'mkv'  => 'video/x-matroska',
            'mov'  => 'video/quicktime',
            'wmv'  => 'video/x-ms-wmv',
            'ts'   => 'video/mp2t',
            default => 'application/octet-stream',
        };
    }
}
