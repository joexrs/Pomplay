<?php

namespace App\Controllers;

use App\Controllers\Api\VideoPinController;
use App\Core\View;
use App\Repositories\VideoRepository;

final class VideoController
{
    public function __construct(private VideoRepository $videos)
    {
    }

    public function detail(array $context): void
    {
        $codigo = isset($_GET['id']) ? (string) $_GET['id'] : '';
        $video = null;
        $videoUrl = '';
        $error = null;
        $requiresPin = false;
        $hasAccess = false;
        $cameras = [];

        if ($codigo === '') {
            $error = 'No se ha proporcionado un codigo de video.';
        } else {
            $video = $this->videos->findByCode($codigo);
            if ($video === null) {
                $error = 'No se encontraron resultados para el codigo: ' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
            } else {
                $originalVideoUrl = (string) ($video['video_url'] ?? '');
                $videoUrl = $this->resolvePlayableVideoUrl($originalVideoUrl, $context['baseUrl']);
                $video['video_url_original'] = $originalVideoUrl;
                $video['video_url'] = $videoUrl;

                // Verificar si el video es privado
                $esPrivado = (bool) ($video['es_privado'] ?? false);
                if ($esPrivado) {
                    $requiresPin = true;
                    $hasAccess = VideoPinController::hasTemporaryAccess($codigo);
                }

                // Obtener todas las cámaras disponibles para esta sesión de video (misma cancha, fecha, hora)
                $codigoCancha = $video['codigo_cancha'] ?? '';
                $fechaPartido = $video['fecha_partido'] ?? '';
                $horaPartido = $video['hora_partido'] ?? '';

                if (!empty($codigoCancha) && !empty($fechaPartido) && !empty($horaPartido)) {
                    $cameras = $this->videos->getCamerasForVideoSession(
                        $codigoCancha,
                        $fechaPartido,
                        $horaPartido
                    );

                    // Resolver URLs de cada cámara
                    foreach ($cameras as &$camera) {
                        $camera['video_url'] = $this->resolvePlayableVideoUrl(
                            $camera['video_url'] ?? '',
                            $context['baseUrl']
                        );
                    }
                    unset($camera);
                }
            }
        }

        View::render('video/detail', [
            'baseUrl' => $context['baseUrl'],
            'video' => $video,
            'videoUrl' => $videoUrl,
            'error' => $error,
            'codigoVideo' => $codigo,
            'requiresPin' => $requiresPin,
            'hasAccess' => $hasAccess,
            'cameras' => $cameras,
        ]);
    }

    private function resolvePlayableVideoUrl(string $rawUrl, string $baseUrl): string
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $rawUrl)) {
            return $rawUrl;
        }

        if (str_starts_with($rawUrl, '//')) {
            return 'https:' . $rawUrl;
        }

        $base = rtrim((string) env('CCTV_VIDEOS_BASE_URL', 'https://cctv.pomplay.com.pe/videos'), '/');
        $path = str_replace('\\', '/', $rawUrl);
        $path = preg_replace('#^file:/+#i', '', $path) ?? $path;

        if (preg_match('#cctv\.pomplay\.com\.pe/(?:videos/)?(.+)$#i', $path, $matches)) {
            $path = $matches[1];
        } elseif (preg_match('#(?:^|/)videos/(.+)$#i', $path, $matches)) {
            $path = $matches[1];
        } elseif (preg_match('#(?:^|/)cctv/(.+)$#i', $path, $matches)) {
            $path = $matches[1];
        } else {
            $path = ltrim($path, '/');
        }

        $parts = array_filter(explode('/', $path), static fn ($part) => $part !== '');
        $encodedPath = implode('/', array_map('rawurlencode', $parts));

        $directUrl = $base . '/' . $encodedPath;

        // Usar proxy local para habilitar CORS y permitir captureStream()
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl . '/public/video-proxy.php?url=' . urlencode($directUrl);
    }
}

