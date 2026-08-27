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
        
        // Agregar formato de rango de hora de 1 hora (ej: "15:30 - 16:30")
        $formatted = array_map(function($row) {
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
        }, $rows);
        
        echo json_encode($formatted);
    }
}
