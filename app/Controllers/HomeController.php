<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CourtRepository;
use App\Repositories\LocalRepository;
use App\Repositories\VideoRepository;

final class HomeController
{
    public function __construct(
        private VideoRepository $videos,
        private LocalRepository $locals,
        private CourtRepository $courts
    ) {
    }

    public function index(array $context): void
    {
        $startTime = microtime(true);

        // Filtros: cancha, fecha y hora afectan los resultados (local NO)
        $searchCancha = isset($_GET['search_cancha']) && $_GET['search_cancha'] !== '' ? $_GET['search_cancha'] : null;
        $searchDate   = isset($_GET['search_date'])   && $_GET['search_date']   !== '' ? date('Y-m-d', strtotime($_GET['search_date'])) : null;
        $searchHora   = isset($_GET['search_hora'])   && $_GET['search_hora']   !== '' ? (int) $_GET['search_hora'] : null;

        // Si se aplicaron filtros, buscar el video y redirigir
        if ($searchCancha || $searchDate || $searchHora !== null) {
            try {
                $total = $this->videos->countAdvanced(null, $searchCancha, $searchDate, $searchHora);
                
                if ($total === 1) {
                    // Si hay exactamente 1 resultado, redirigir al detalle
                    $canchas = $this->videos->findAdvanced(null, $searchCancha, $searchDate, $searchHora, 1, 0);
                    if (!empty($canchas)) {
                        $video = $canchas[0];
                        header('Location: ' . $context['baseUrl'] . '/video/' . $video['codigo_video']);
                        exit;
                    }
                } elseif ($total === 0) {
                    // Si no hay resultados, mostrar mensaje
                    $errorMessage = 'No se encontró ningún video con los filtros seleccionados.';
                } else {
                    // Si hay múltiples resultados, mostrar mensaje
                    $errorMessage = "Se encontraron {$total} videos. Por favor, refina tu búsqueda para obtener un resultado específico.";
                }
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
                $horasRaw = $this->videos->getAvailableHoursForFilter(null, $searchCancha, $searchDate);
                // Formatear horas con rango (ej: "15:00 - 16:00")
                $horas = array_map(function($row) {
                    $hora = (int) $row['hora'];
                    $horaInicio = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
                    $horaFin = str_pad($hora + 1, 2, '0', STR_PAD_LEFT) . ':00';
                    return [
                        'hora' => $hora,
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
            'searchCancha' => $searchCancha,
            'searchDate'   => $searchDate,
            'searchHora'   => $searchHora,
            'errorMessage' => $errorMessage ?? null,
            'loadTime'     => $loadTime,
        ]);
    }
}
