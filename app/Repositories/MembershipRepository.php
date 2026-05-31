<?php

namespace App\Repositories;

use PDO;

class MembershipRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Obtener membresía activa del usuario en un local
     */
    public function getActiveMembership(int $userId, int $localId): ?array
    {
        $query = <<<SQL
            SELECT * FROM membresias 
            WHERE id_usuario = :user_id 
            AND id_local = :local_id 
            AND estado = 1 
            AND fecha_vencimiento >= CURDATE()
            LIMIT 1
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':local_id' => $localId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtener todas las membresías del usuario
     */
    public function getUserMemberships(int $userId): array
    {
        $query = <<<SQL
            SELECT 
                m.*,
                l.nombre_local,
                CASE 
                    WHEN m.fecha_vencimiento >= CURDATE() THEN 'ACTIVA'
                    ELSE 'EXPIRADA'
                END as estado_actual
            FROM membresias m
            LEFT JOIN locales l ON m.id_local = l.id_local
            WHERE m.id_usuario = :user_id
            ORDER BY m.fecha_vencimiento DESC
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear membresía
     */
    public function createMembership(array $data): ?int
    {
        $query = <<<SQL
            INSERT INTO membresias (id_usuario, id_local, fecha_inicio, fecha_vencimiento, estado, tipo_membresia)
            VALUES (:user_id, :local_id, :fecha_inicio, :fecha_vencimiento, :estado, :tipo)
        SQL;

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $data['id_usuario'],
                ':local_id' => $data['id_local'],
                ':fecha_inicio' => $data['fecha_inicio'] ?? date('Y-m-d'),
                ':fecha_vencimiento' => $data['fecha_vencimiento'],
                ':estado' => $data['estado'] ?? 1,
                ':tipo' => $data['tipo_membresia'] ?? 'BASICA',
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Renovar membresía (extender fecha de vencimiento)
     */
    public function renewMembership(int $membershipId, int $months = 1): bool
    {
        $query = <<<SQL
            UPDATE membresias 
            SET fecha_vencimiento = DATE_ADD(fecha_vencimiento, INTERVAL :months MONTH),
                estado = 1
            WHERE id_membresia = :id
        SQL;

        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':id' => $membershipId,
                ':months' => $months,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Obtener membresía por ID
     */
    public function getMembershipById(int $membershipId): ?array
    {
        $query = <<<SQL
            SELECT 
                m.*,
                l.nombre_local,
                u.usuario,
                CASE 
                    WHEN m.fecha_vencimiento >= CURDATE() THEN 'ACTIVA'
                    ELSE 'EXPIRADA'
                END as estado_actual,
                DATEDIFF(m.fecha_vencimiento, CURDATE()) as dias_restantes
            FROM membresias m
            LEFT JOIN locales l ON m.id_local = l.id_local
            LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
            WHERE m.id_membresia = :id
            LIMIT 1
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $membershipId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtener membresías próximas a vencer (30 días)
     */
    public function getMembershipsExpiringSoon(int $days = 30): array
    {
        $query = <<<SQL
            SELECT 
                m.*,
                l.nombre_local,
                u.usuario,
                u.email,
                DATEDIFF(m.fecha_vencimiento, CURDATE()) as dias_restantes
            FROM membresias m
            LEFT JOIN locales l ON m.id_local = l.id_local
            LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
            WHERE m.estado = 1 
            AND DATEDIFF(m.fecha_vencimiento, CURDATE()) > 0 
            AND DATEDIFF(m.fecha_vencimiento, CURDATE()) <= :days
            ORDER BY m.fecha_vencimiento ASC
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':days' => $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener membresías vencidas
     */
    public function getExpiredMemberships(): array
    {
        $query = <<<SQL
            SELECT 
                m.*,
                l.nombre_local,
                u.usuario,
                u.email,
                DATEDIFF(CURDATE(), m.fecha_vencimiento) as dias_vencidos
            FROM membresias m
            LEFT JOIN locales l ON m.id_local = l.id_local
            LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
            WHERE m.estado = 1 
            AND m.fecha_vencimiento < CURDATE()
            ORDER BY m.fecha_vencimiento DESC
        SQL;

        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar membresías activas
     */
    public function countActiveMemberships(): int
    {
        $query = <<<SQL
            SELECT COUNT(*) as total FROM membresias 
            WHERE estado = 1 AND fecha_vencimiento >= CURDATE()
        SQL;

        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] ?? 0;
    }
}
