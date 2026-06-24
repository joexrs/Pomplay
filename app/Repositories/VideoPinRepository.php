<?php

namespace App\Repositories;

use PDO;

/**
 * Repositorio para gestionar PINs de acceso a videos privados.
 * Los PINs se almacenan hasheados con bcrypt, nunca en texto plano.
 */
final class VideoPinRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Generar un nuevo PIN aleatorio para un video.
     * Invalida PINs anteriores, genera uno nuevo, lo hashea y lo guarda.
     *
     * @param string $codigoVideo Código del video
     * @param int $longitud Longitud del PIN (4 o 6 dígitos)
     * @param int $minutosExpiracion Minutos hasta que expire el PIN
     * @return array ['pin' => string (texto plano, solo esta vez), 'expira_en' => string]
     */
    public function generatePin(string $codigoVideo, int $longitud = 6, int $minutosExpiracion = 30): array
    {
        // Validar longitud
        if (!in_array($longitud, [4, 6], true)) {
            $longitud = 6;
        }

        // Validar minutos
        if ($minutosExpiracion < 5 || $minutosExpiracion > 120) {
            $minutosExpiracion = 30;
        }

        // 1. Invalidar PINs existentes para este video
        $this->invalidateExistingPins($codigoVideo);

        // 2. Generar PIN aleatorio
        $pin = $this->generateRandomPin($longitud);

        // 3. Hashear el PIN con bcrypt
        $pinHash = password_hash($pin, PASSWORD_BCRYPT);

        // 4. Calcular fecha de expiración
        $expiraEn = date('Y-m-d H:i:s', time() + ($minutosExpiracion * 60));

        // 5. Guardar en BD
        $stmt = $this->pdo->prepare('CALL InsertVideoPin(:codigo_video, :pin_hash, :pin_longitud, :expira_en)');
        $stmt->execute([
            ':codigo_video' => $codigoVideo,
            ':pin_hash' => $pinHash,
            ':pin_longitud' => $longitud,
            ':expira_en' => $expiraEn,
        ]);
        $stmt->closeCursor();

        // 6. Limpiar PINs expirados de otros videos (mantenimiento oportunista)
        $this->cleanExpiredPins();

        return [
            'pin' => $pin,
            'expira_en' => $expiraEn,
            'longitud' => $longitud,
        ];
    }

    /**
     * Verificar un PIN ingresado contra el hash almacenado.
     *
     * @param string $codigoVideo Código del video
     * @param string $pinIngresado PIN en texto plano ingresado por el usuario
     * @return array ['valid' => bool, 'expired' => bool, 'not_found' => bool]
     */
    public function verifyPin(string $codigoVideo, string $pinIngresado): array
    {
        // Obtener PIN activo
        $pin = $this->getActivePin($codigoVideo);

        if ($pin === null) {
            // Verificar si existe algún PIN (puede estar expirado)
            $anyPin = $this->getAnyPin($codigoVideo);

            if ($anyPin !== null) {
                return ['valid' => false, 'expired' => true, 'not_found' => false];
            }

            return ['valid' => false, 'expired' => false, 'not_found' => true];
        }

        // Verificar si expiró (doble validación)
        if (strtotime($pin['expira_en']) <= time()) {
            return ['valid' => false, 'expired' => true, 'not_found' => false];
        }

        // Comparar con password_verify (bcrypt)
        $isValid = password_verify($pinIngresado, $pin['pin_hash']);

        return ['valid' => $isValid, 'expired' => false, 'not_found' => false];
    }

    /**
     * Obtener PIN activo (no expirado) de un video.
     */
    public function getActivePin(string $codigoVideo): ?array
    {
        $stmt = $this->pdo->prepare('CALL GetActivePinByVideo(:codigo_video)');
        $stmt->execute([':codigo_video' => $codigoVideo]);

        $pin = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $pin ?: null;
    }

    /**
     * Obtener cualquier PIN (incluso expirado) de un video.
     */
    private function getAnyPin(string $codigoVideo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, codigo_video, expira_en FROM video_pins WHERE codigo_video = :codigo_video ORDER BY creado_en DESC LIMIT 1'
        );
        $stmt->execute([':codigo_video' => $codigoVideo]);

        $pin = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $pin ?: null;
    }

    /**
     * Invalidar (eliminar) PINs existentes de un video.
     */
    public function invalidateExistingPins(string $codigoVideo): void
    {
        $stmt = $this->pdo->prepare('CALL InvalidatePinsByVideo(:codigo_video)');
        $stmt->execute([':codigo_video' => $codigoVideo]);
        $stmt->closeCursor();
    }

    /**
     * Limpiar PINs expirados de la BD (mantenimiento).
     */
    public function cleanExpiredPins(): void
    {
        try {
            $stmt = $this->pdo->query('CALL CleanExpiredPins()');
            $stmt->closeCursor();
        } catch (\PDOException) {
            // Silenciar errores de limpieza
        }
    }

    /**
     * Verificar si un video pertenece a un local privado.
     */
    public function isVideoPrivate(string $codigoVideo): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(l.es_privado, 0) AS es_privado
             FROM video v
             LEFT JOIN locales l ON v.id_local = l.id_local
             WHERE v.codigo_video = :codigo_video
             LIMIT 1'
        );
        $stmt->execute([':codigo_video' => $codigoVideo]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return (bool) ($result['es_privado'] ?? false);
    }

    /**
     * Verificar si un local es privado por su ID.
     */
    public function isLocalPrivate(int $idLocal): bool
    {
        $stmt = $this->pdo->prepare('SELECT es_privado FROM locales WHERE id_local = :id_local LIMIT 1');
        $stmt->execute([':id_local' => $idLocal]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return (bool) ($result['es_privado'] ?? false);
    }

    /**
     * Generar un PIN numérico aleatorio seguro.
     */
    private function generateRandomPin(int $length): string
    {
        $pin = '';
        for ($i = 0; $i < $length; $i++) {
            $pin .= random_int(0, 9);
        }
        return $pin;
    }
}
