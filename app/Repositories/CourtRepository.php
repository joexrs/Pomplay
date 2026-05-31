<?php

namespace App\Repositories;

use PDO;

final class CourtRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function codes(?int $categoria = null): array
    {
        if ($categoria !== null) {
            $stmt = $this->pdo->prepare('SELECT * FROM cancha WHERE id_categoria = :categoria AND estado = 1 ORDER BY descripcion');
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return $rows;
        }

        $stmt = $this->pdo->query('SELECT * FROM cancha WHERE estado = 1 ORDER BY descripcion');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    public function getByLocal(int $idLocal): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cancha WHERE id_local = :id_local AND estado = 1 ORDER BY descripcion');
        $stmt->execute([':id_local' => $idLocal]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }
}
