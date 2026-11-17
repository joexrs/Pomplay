<?php

namespace App\Controllers;

use App\Controllers\Api\VideoPasswordController;
use App\Core\View;
use App\Repositories\VideoRepository;

final class VideoController
{
    public function __construct(private VideoRepository $videos)
    {
    }

    public function detail(array $context): void
    {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');

        $codigo = isset($_GET['id']) ? (string) $_GET['id'] : '';
        $video = null;
        $videoUrl = '';
        $error = null;
        $requiresPassword = false;
        $hasAccess = false;
        $cameras = [];

        

        if ($codigo === '') {
            $error = 'No se ha proporcionado un codigo de video.';
        } else {
            $video = $this->videos->findByCode($codigo);
            if ($video === null) {
                $error = 'No se encontraron resultados para el codigo: ' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
            } elseif (!array_key_exists('password_hash', $video)) {
                $error = 'No se puede comprobar la protección de este video. Actualiza la base de datos para habilitar las contraseñas.';
                $video = null;
            } else {
                $originalVideoUrl = (string) ($video['video_url'] ?? '');
                $passwordHash     = (string) ($video['password_hash'] ?? '');

                // ── Verificar acceso ──────────────────────────────────────────
                // $hasAccess = true si:
                //   • el video no tiene contraseña, O
                //   • el usuario ya validó la clave en esta sesión
                $hasAccess = $passwordHash === ''
                    || VideoPasswordController::hasTemporaryAccess($codigo, $passwordHash);

                $requiresPassword = $passwordHash !== '';

                // Solo se genera/expone la URL firmada si hay acceso real.
                // De lo contrario el enlace directo al VPS NO aparece en el HTML.
                if ($hasAccess) {
                    $videoUrl = $this->resolvePlayableVideoUrl($codigo, $originalVideoUrl, $context['baseUrl']);
                    $video['video_url'] = $videoUrl;

                    // Obtener cámaras adicionales del mismo partido
                    $idLocal      = (int) ($video['id_local'] ?? 0);
                    $codigoCancha = trim((string) ($video['codigo_cancha'] ?? ''));
                    $fechaPartido = date('Y-m-d', strtotime((string) ($video['fecha_partido'] ?? '')));
                    $horaPartido  = date('H:i:s',  strtotime((string) ($video['hora_partido']  ?? '')));

                    if ($idLocal > 0 && $codigoCancha !== '' && !empty($fechaPartido) && !empty($horaPartido)) {
                        $cameras = $this->videos->getCamerasForVideoSession(
                            $idLocal,
                            $codigoCancha,
                            $fechaPartido,
                            $horaPartido
                        );

                        foreach ($cameras as &$camera) {
                            $camera['video_url'] = $this->resolvePlayableVideoUrl(
                                (string) ($camera['codigo_video'] ?? ''),
                                $camera['video_url'] ?? '',
                                $context['baseUrl']
                            );
                        }
                        unset($camera);
                    }
                } else {
                    // Contraseña requerida y aún no validada:
                    // no exponer URL ni cámaras en el HTML.
                    $videoUrl           = '';
                    $video['video_url'] = '';
                    $cameras            = [];
                }
            }
        }

        View::render('video/detail', [
            'baseUrl' => $context['baseUrl'],
            'video' => $video,
            'videoUrl' => $videoUrl,
            'error' => $error,
            'codigoVideo' => $codigo,
            'requiresPassword' => $requiresPassword,
            'hasAccess' => $hasAccess,
            'cameras' => $cameras,
        ]);
    }

    private function resolvePlayableVideoUrl(string $codigoVideo, string $rawUrl, string $baseUrl): string
    {
        if (trim($rawUrl) === '' || $codigoVideo === '') {
            return '';
        }

        // Intentar generar URL firmada directa al VPS (sin pasar por video-proxy.php)
        $signed = $this->buildSignedVpsUrl($rawUrl);
        if ($signed !== null) {
            return $signed;
        }

        // Fallback: proxy de Hostinger (ruta anterior)
        $baseUrl = rtrim($baseUrl, '/');
        return $baseUrl . '/public/video-proxy.php?codigo_video=' . rawurlencode($codigoVideo);
    }

    /**
     * Genera una URL firmada directa a cctv.pomplay.com.pe/download.php con inline=1
     * para que el navegador haga streaming sin pasar por el proxy de Hostinger.
     *
     * Formato esperado de $rawUrl:
     *   https://cctv.pomplay.com.pe/videos/{id_local}/{codigo_cancha}/{filename}
     *   o rutas relativas tipo  {id_local}/{codigo_cancha}/{filename}
     *
     * Devuelve null si la URL no es del VPS o no tiene el formato esperado.
     */
    private function buildSignedVpsUrl(string $rawUrl): ?string
    {
        $secret = (string) env('VPS_KEY', '');
        if ($secret === '') {
            return null;
        }

        // Normalizar a ruta relativa: extraer la parte después de /videos/
        $path = $rawUrl;
        if (preg_match('#cctv\.pomplay\.com\.pe/(?:videos/)?(.+)$#i', $path, $m)) {
            $path = $m[1];
        } elseif (!preg_match('#^https?://#i', $path)) {
            // ya es relativa; limpiar barras iniciales
            $path = ltrim(str_replace('\\', '/', $path), '/');
        } else {
            // URL de otro dominio: no aplica
            return null;
        }

        // Debe tener formato  {id_local}/{codigo_cancha}/{filename.mp4}
        if (!preg_match('#^(\d+/[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+\.mp4)$#i', $path)) {
            return null;
        }

        // El nombre de archivo es la última parte de la ruta
        $parts    = explode('/', $path);
        $filename = end($parts);

        $expires   = time() + 6 * 3600;   // máximo que acepta download.php
        $signature = hash_hmac('sha256', $path . "\n" . $filename . "\n" . $expires, $secret);

        return 'https://cctv.pomplay.com.pe/download.php?' . http_build_query([
            'path'      => $path,
            'filename'  => $filename,
            'expires'   => $expires,
            'signature' => $signature,
            'inline'    => 1,
        ]);
    }
}
