<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CourtRepository;
use App\Repositories\EstadisticasRepository;
use App\Repositories\LocalRepository;
use App\Repositories\VideoRepository;

final class HomeController
{
    public function __construct(
        private VideoRepository $videos,
        private LocalRepository $locals,
        private CourtRepository $courts,
        private EstadisticasRepository $estadisticas
    ) {
    }

    public function index(array $context): void
    {
        $startTime = microtime(true);

        // Filtros: local, cancha, fecha y hora afectan los resultados
        $searchLocal  = isset($_GET['search_local']) && $_GET['search_local'] !== '' ? (int) $_GET['search_local'] : null;
        $searchCancha = isset($_GET['search_cancha']) && $_GET['search_cancha'] !== '' ? $_GET['search_cancha'] : null;
        $searchDate   = isset($_GET['search_date'])   && $_GET['search_date']   !== '' ? date('Y-m-d', strtotime($_GET['search_date'])) : null;
        $searchHora   = isset($_GET['search_hora'])   && $_GET['search_hora']   !== '' ? trim($_GET['search_hora']) : null;

        // Si se aplicaron filtros, buscar el video y redirigir
        if ($searchLocal !== null || $searchCancha || $searchDate || $searchHora !== null) {
            try {
                $total = $this->videos->countAdvanced($searchLocal, $searchCancha, $searchDate, $searchHora);
                $codigoVideoResultado = null;

                if ($total === 1) {
                    // Si hay exactamente 1 resultado, redirigir al detalle
                    $canchas = $this->videos->findAdvanced($searchLocal, $searchCancha, $searchDate, $searchHora, 1, 0);
                    if (!empty($canchas)) {
                        $video = $canchas[0];
                        $codigoVideoResultado = $video['codigo_video'] ?? null;

                        $this->estadisticas->logBusqueda(
                            $searchLocal,
                            $searchCancha,
                            $searchDate,
                            $searchHora !== null ? (string) $searchHora : null,
                            $total,
                            $codigoVideoResultado,
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null
                        );

                        header('Location: ' . $context['baseUrl'] . '/video/' . $video['codigo_video']);
                        exit;
                    }
                } elseif ($total === 0) {
                    $errorMessage = 'No se encontró ningún video con los filtros seleccionados.';
                } else {
                    // Múltiples resultados: verificar si son solo ángulos distintos del MISMO partido
                    $resultados = $this->videos->findAdvanced($searchLocal, $searchCancha, $searchDate, $searchHora, $total, 0);

                    $mismoPartido = true;
                    $primero = $resultados[0];
                    $timePrimero = strtotime($primero['hora_partido']);
                    foreach ($resultados as $r) {
                        $timeR = strtotime($r['hora_partido']);
                        if (
                            $r['codigo_cancha']  !== $primero['codigo_cancha'] ||
                            $r['fecha_partido']  !== $primero['fecha_partido'] ||
                            ($timePrimero !== false && $timeR !== false && abs($timeR - $timePrimero) > 3600)
                        ) {
                            $mismoPartido = false;
                            break;
                        }
                    }

                    if ($mismoPartido) {
                        $codigoVideoResultado = $primero['codigo_video'] ?? null;

                        $this->estadisticas->logBusqueda(
                            $searchLocal,
                            $searchCancha,
                            $searchDate,
                            $searchHora !== null ? (string) $searchHora : null,
                            $total,
                            $codigoVideoResultado,
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null
                        );

                        // Son distintos ángulos del mismo partido → ir directo al detalle
                        header('Location: ' . $context['baseUrl'] . '/video/' . $primero['codigo_video']);
                        exit;
                    }

                    $errorMessage = "Se encontraron {$total} videos. Por favor, refina tu búsqueda para obtener un resultado específico.";
                }

                $this->estadisticas->logBusqueda(
                    $searchLocal,
                    $searchCancha,
                    $searchDate,
                    $searchHora !== null ? (string) $searchHora : null,
                    $total,
                    $codigoVideoResultado,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            } catch (\Exception $e) {
                $errorMessage = 'Error al buscar el video. Por favor, intenta nuevamente.';
            }
        }

        // Obtener locales para el selector
        try {
            $locales = $this->locals->getAllLocals(20, 0);
        } catch (\Exception $e) {
            $locales = [];
        }

        // Obtener todas las canchas disponibles
        try {
            $codigos = $this->courts->codes();
        } catch (\Exception $e) {
            $codigos = [];
        }

        // Solo obtener horas si hay fecha seleccionada
        $horas = [];
        if ($searchDate !== null) {
            try {
                $horasRaw = $this->videos->getAvailableHoursForFilter($searchLocal, $searchCancha, $searchDate);
                // Formatear horas con rango (ej: "15:30 - 16:30")
                $horas = array_map(function($row) {
                    $horaVal = (string) ($row['hora'] ?? '');
                    if (strpos($horaVal, ':') !== false) {
                        $horaInicio = $horaVal;
                        $horaFin = date('H:i', strtotime($horaVal . ' +1 hour'));
                    } else {
                        $h = (int) $horaVal;
                        $horaInicio = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                        $horaFin = str_pad(($h + 1) % 24, 2, '0', STR_PAD_LEFT) . ':00';
                    }
                    return [
                        'hora' => $horaVal,
                        'hora_rango' => $horaInicio . ' - ' . $horaFin
                    ];
                }, $horasRaw);
            } catch (\Exception $e) {
                $horas = [];
            }
        }

        $loadTime = round(microtime(true) - $startTime, 3);

        View::render('home/index', [
            'baseUrl'      => $context['baseUrl'],
            'locales'      => $locales,
            'codigos'      => $codigos,
            'horas'        => $horas,
            'searchLocal'  => $searchLocal,
            'searchCancha' => $searchCancha,
            'searchDate'   => $searchDate,
            'searchHora'   => $searchHora,
            'errorMessage' => $errorMessage ?? null,
            'loadTime'     => $loadTime,
        ]);
    }
}
