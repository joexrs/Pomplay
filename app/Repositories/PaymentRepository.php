<?php

namespace App\Repositories;

use PDO;

class PaymentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crear registro de pago
     */
    public function createPayment(array $data): ?int
    {
        $query = <<<SQL
            INSERT INTO pagos (id_usuario, id_membresia, id_local, monto, moneda, metodo_pago, referencia_pago, estado_pago, codigo_qr)
            VALUES (:user_id, :membresia_id, :local_id, :monto, :moneda, :metodo, :referencia, :estado, :qr)
        SQL;

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $data['id_usuario'],
                ':membresia_id' => $data['id_membresia'] ?? null,
                ':local_id' => $data['id_local'],
                ':monto' => $data['monto'],
                ':moneda' => $data['moneda'] ?? 'USD',
                ':metodo' => $data['metodo_pago'] ?? 'QR',
                ':referencia' => $data['referencia_pago'] ?? null,
                ':estado' => $data['estado_pago'] ?? 'PENDIENTE',
                ':qr' => $data['codigo_qr'] ?? null,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Actualizar estado del pago
     */
    public function updatePaymentStatus(int $paymentId, string $status, ?string $reference = null): bool
    {
        $query = <<<SQL
            UPDATE pagos 
            SET estado_pago = :status, 
                referencia_pago = COALESCE(:reference, referencia_pago),
                fecha_pago = CASE WHEN :status = 'COMPLETADO' THEN NOW() ELSE fecha_pago END
            WHERE id_pago = :id
        SQL;

        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':id' => $paymentId,
                ':status' => $status,
                ':reference' => $reference,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Obtener pago por ID
     */
    public function getPaymentById(int $paymentId): ?array
    {
        $query = <<<SQL
            SELECT 
                p.*,
                u.usuario,
                u.email,
                l.nombre_local
            FROM pagos p
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN locales l ON p.id_local = l.id_local
            WHERE p.id_pago = :id
            LIMIT 1
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $paymentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtener pagos del usuario
     */
    public function getUserPayments(int $userId, int $limit = 50): array
    {
        $query = <<<SQL
            SELECT 
                p.*,
                l.nombre_local,
                m.id_membresia
            FROM pagos p
            LEFT JOIN locales l ON p.id_local = l.id_local
            LEFT JOIN membresias m ON p.id_membresia = m.id_membresia
            WHERE p.id_usuario = :user_id
            ORDER BY p.fecha_creacion DESC
            LIMIT :limit
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pagos pendientes
     */
    public function getPendingPayments(int $limit = 50): array
    {
        $query = <<<SQL
            SELECT 
                p.*,
                u.usuario,
                u.email,
                l.nombre_local
            FROM pagos p
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN locales l ON p.id_local = l.id_local
            WHERE p.estado_pago = 'PENDIENTE'
            ORDER BY p.fecha_creacion DESC
            LIMIT :limit
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pagos completados en período
     */
    public function getCompletedPayments(string $dateFrom, string $dateTo, ?int $localId = null): array
    {
        $baseQuery = <<<SQL
            SELECT 
                p.*,
                u.usuario,
                l.nombre_local
            FROM pagos p
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN locales l ON p.id_local = l.id_local
            WHERE p.estado_pago = 'COMPLETADO'
            AND DATE(p.fecha_pago) BETWEEN :from AND :to
        SQL;

        $params = [':from' => $dateFrom, ':to' => $dateTo];

        if ($localId) {
            $baseQuery .= ' AND p.id_local = :local_id';
            $params[':local_id'] = $localId;
        }

        $baseQuery .= ' ORDER BY p.fecha_pago DESC';

        $stmt = $this->db->prepare($baseQuery);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar pagos completados
     */
    public function countCompletedPayments(): int
    {
        $query = 'SELECT COUNT(*) as total FROM pagos WHERE estado_pago = "COMPLETADO"';

        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] ?? 0;
    }

    /**
     * Obtener monto total pagado
     */
    public function getTotalPaymentsAmount(?int $localId = null): float
    {
        $query = 'SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE estado_pago = "COMPLETADO"';
        $params = [];

        if ($localId) {
            $query .= ' AND id_local = :local_id';
            $params[':local_id'] = $localId;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($result['total'] ?? 0);
    }
}
