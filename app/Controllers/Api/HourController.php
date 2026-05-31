<?php

namespace App\Controllers\Api;

use App\Repositories\VideoRepository;

final class HourController
{
    public function __construct(private VideoRepository $videos)
    {
    }

    public function available(): void
    {
        header('Content-Type: application/json');

        $idLocal      = isset($_GET['local'])   && $_GET['local']   !== '' ? (int) $_GET['local']   : null;
        $codigoCancha = isset($_GET['cancha'])  && $_GET['cancha']  !== '' ? $_GET['cancha']         : null;
        $fecha        = isset($_GET['fecha'])   && $_GET['fecha']   !== '' ? $_GET['fecha']          : null;

        $rows = $this->videos->getAvailableHoursForFilter($idLocal, $codigoCancha, $fecha);
        
        // Agregar formato de rango de hora (ej: "15:00 - 16:00")
        $formatted = array_map(function($row) {
            $hora = (int) $row['hora'];
            $horaInicio = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
            $horaFin = str_pad($hora + 1, 2, '0', STR_PAD_LEFT) . ':00';
            return [
                'hora' => $hora,
                'hora_rango' => $horaInicio . ' - ' . $horaFin
            ];
        }, $rows);
        
        echo json_encode($formatted);
    }
}
