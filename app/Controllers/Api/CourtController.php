<?php

namespace App\Controllers\Api;

use App\Repositories\CourtRepository;

final class CourtController
{
    public function __construct(private CourtRepository $courts)
    {
    }

    public function byCategory(): void
    {
        header('Content-Type: application/json');
        $categoria = isset($_GET['categoria']) ? (int) $_GET['categoria'] : 0;
        if ($categoria <= 0) {
            echo json_encode([]);
            return;
        }

        echo json_encode($this->courts->codes($categoria));
    }

    public function byLocal(): void
    {
        header('Content-Type: application/json');
        $idLocal = isset($_GET['local']) && $_GET['local'] !== '' ? (int) $_GET['local'] : 0;
        if ($idLocal <= 0) {
            echo json_encode([]);
            return;
        }

        echo json_encode($this->courts->getByLocal($idLocal));
    }
}
