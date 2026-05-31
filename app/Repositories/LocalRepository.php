<?php

namespace App\Repositories;

use PDO;

class LocalRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function getAllLocals(int $limit = 100, int $offset = 0): array
    {
        // Para el index público, usar una consulta simple sin JOINs complejos
        $stmt = $this->db->prepare('SELECT * FROM locales WHERE estado = 1 ORDER BY nombre_local LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    public function getLocalById(int $localId): ?array
    {
        $stmt = $this->db->prepare('CALL GetLocalById(:id)');
        $stmt->execute([':id' => $localId]);

        $local = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $local ?: null;
    }

    public function createLocal(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare('CALL CreateLocalSimple(:nombre, :estado)');
            $stmt->execute([
                ':nombre' => $data['nombre_local'],
                ':estado' => $data['estado'] ?? 1,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return (int) ($row['id_local'] ?? 0);
        } catch (\PDOException) {
            return null;
        }
    }

    public function updateLocal(int $localId, array $data): bool
    {
        try {
            $stmt = $this->db->prepare('CALL UpdateLocalSimple(:id, :nombre, :estado)');
            $result = $stmt->execute([
                ':id' => $localId,
                ':nombre' => $data['nombre_local'] ?? null,
                ':estado' => $data['estado'] ?? 1,
            ]);
            $stmt->closeCursor();
            return $result;
        } catch (\PDOException) {
            return false;
        }
    }

    public function deleteLocal(int $localId): bool
    {
        try {
            $stmt = $this->db->prepare('CALL DeleteLocal(:id)');
            $result = $stmt->execute([':id' => $localId]);
            $stmt->closeCursor();
            return $result;
        } catch (\PDOException) {
            return false;
        }
    }

    public function getLocalsWithCourts(): array
    {
        $stmt = $this->db->query('CALL GetLocalsWithCourts()');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    public function getLocalsWithOwners(): array
    {
        $stmt = $this->db->query('CALL GetLocalsWithOwners()');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }
}
