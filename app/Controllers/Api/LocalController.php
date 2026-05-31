<?php

namespace App\Controllers\Api;

use App\Repositories\LocalRepository;

final class LocalController
{
    public function __construct(private LocalRepository $locals)
    {
    }

    public function all(): void
    {
        header('Content-Type: application/json');
        $rows = $this->locals->getAllLocals(200, 0);
        echo json_encode($rows);
    }
}
