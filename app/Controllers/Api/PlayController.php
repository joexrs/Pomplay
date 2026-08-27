<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Repositories\EstadisticasRepository;

/**
 * PlayController — registra una reproduccion de video para estadisticas.
 *
 * POST /api/log-play
 * Body JSON: { codigoVideo, idLocal, codigoCancha }
 */
final class PlayController
{
    public function __construct(
        private EstadisticasRepository $estadisticas
    ) {
    }

    public function log(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
            return;
        }

        $body  = file_get_contents('php://input');
        $input = json_decode($body ?: '{}', true) ?? [];

        $codigoVideo  = trim((string) ($input['codigoVideo']  ?? ''));
        $idLocal      = isset($input['idLocal'])  ? (int) $input['idLocal']  : null;
        $codigoCancha = trim((string) ($input['codigoCancha'] ?? ''));

        // Registrar reproduccion (fallo silencioso para no interrumpir al usuario)
        $this->estadisticas->logReproduccion(
            $codigoVideo  !== '' ? $codigoVideo  : null,
            $idLocal ?: null,
            $codigoCancha !== '' ? $codigoCancha : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        echo json_encode(['ok' => true]);
    }
}
