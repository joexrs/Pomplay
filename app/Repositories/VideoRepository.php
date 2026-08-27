<?php

namespace App\Repositories;

use App\Core\Cache;
use PDO;

final class VideoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Buscar videos paginados con filtros avanzados
     *
     * @param int|null $idLocal - ID del local (sucursal)
     * @param string|null $codigoCancha - Código de la cancha
     * @param string|null $fecha - Fecha del partido (Y-m-d)
     * @param int|string|null $hora - Hora de inicio del partido (ej. 15 o '15:30')
     * @param int $limit - Registros por página
     * @param int $offset - Offset para paginación
     */
    public function findAdvanced(?int $idLocal, ?string $codigoCancha, ?string $fecha, int|string|null $hora, int $limit, int $offset): array
    {
        // Generar clave de caché
        $cacheKey = Cache::generateKey('findAdvanced', [
            $idLocal, $codigoCancha, $fecha, $hora, $limit, $offset
        ]);

        return Cache::remember($cacheKey, function() use ($idLocal, $codigoCancha, $fecha, $hora, $limit, $offset) {
            // Usar consulta SQL directa en lugar de stored procedure para mejor rendimiento
            $sql = "SELECT
                        v.*,
                        c.descripcion AS cancha_nombre,
                        l.nombre_local,
                        cat.nombre_categoria
                    FROM video v
                    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
                    LEFT JOIN locales l ON v.id_local = l.id_local
                    LEFT JOIN categoria cat ON c.id_categoria = cat.id_categoria
                    WHERE v.estado = 1";

            $params = [];

            if ($idLocal !== null) {
                $sql .= " AND v.id_local = :id_local";
                $params[':id_local'] = $idLocal;
            }

            if ($codigoCancha !== null) {
                $sql .= " AND v.codigo_cancha = :codigo_cancha";
                $params[':codigo_cancha'] = $codigoCancha;
            }

            if ($fecha !== null) {
                $sql .= " AND DATE(v.fecha_partido) = :fecha";
                $params[':fecha'] = $fecha;
            }

            if ($hora !== null && $hora !== '') {
                if (is_string($hora) && strpos($hora, ':') !== false) {
                    $horaInicio = strlen($hora) === 5 ? $hora . ':00' : $hora;
                    $sql .= " AND v.hora_partido >= :hora_inicio AND v.hora_partido <= ADDTIME(:hora_inicio_add, '01:00:00')";
                    $params[':hora_inicio'] = $horaInicio;
                    $params[':hora_inicio_add'] = $horaInicio;
                } else {
                    $sql .= " AND HOUR(v.hora_partido) = :hora";
                    $params[':hora'] = (int) $hora;
                }
            }

            $sql .= " ORDER BY v.fecha_partido DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return $rows;
        }, 180); // Caché por 3 minutos
    }

    /**
     * Contar videos con filtros avanzados
     */
    public function countAdvanced(?int $idLocal, ?string $codigoCancha, ?string $fecha, int|string|null $hora): int
    {
        // Usar consulta SQL directa en lugar de stored procedure
        $sql = "SELECT COUNT(*) AS total
                FROM video v
                WHERE v.estado = 1";

        $params = [];

        if ($idLocal !== null) {
            $sql .= " AND v.id_local = :id_local";
            $params[':id_local'] = $idLocal;
        }

        if ($codigoCancha !== null) {
            $sql .= " AND v.codigo_cancha = :codigo_cancha";
            $params[':codigo_cancha'] = $codigoCancha;
        }

        if ($fecha !== null) {
            $sql .= " AND DATE(v.fecha_partido) = :fecha";
            $params[':fecha'] = $fecha;
        }

        if ($hora !== null && $hora !== '') {
            if (is_string($hora) && strpos($hora, ':') !== false) {
                $horaInicio = strlen($hora) === 5 ? $hora . ':00' : $hora;
                $sql .= " AND v.hora_partido >= :hora_inicio AND v.hora_partido <= ADDTIME(:hora_inicio_add, '01:00:00')";
                $params[':hora_inicio'] = $horaInicio;
                $params[':hora_inicio_add'] = $horaInicio;
            } else {
                $sql .= " AND HOUR(v.hora_partido) = :hora";
                $params[':hora'] = (int) $hora;
            }
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Obtener candhas disponibles en un local
     */
    public function getCourtsInLocal(int $idLocal): array
    {
        $stmt = $this->pdo->prepare('CALL GetCourtsInLocal(:id_local)');
        $stmt->execute([':id_local' => $idLocal]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * Obtener horas disponibles en un día para una cancha
     */
    public function getAvailableHours(string $fecha, string $codigoCancha): array
    {
        $stmt = $this->pdo->prepare('CALL GetAvailableHours(:fecha, :codigo_cancha)');
        $stmt->execute([
            ':fecha' => $fecha,
            ':codigo_cancha' => $codigoCancha,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * Obtener horas disponibles para el selector de franja (puede filtrar por local y cancha)
     */
    public function getAvailableHoursForFilter(?int $idLocal, ?string $codigoCancha, ?string $fecha): array
    {
        // Usar consulta SQL directa en lugar de stored procedure
        $sql = "SELECT DISTINCT TIME_FORMAT(v.hora_partido, '%H:%i') AS hora
                FROM video v
                WHERE v.estado = 1
                AND v.fecha_partido != '0000-00-00'
                AND v.hora_partido IS NOT NULL";

        $params = [];

        if ($fecha !== null) {
            $sql .= " AND DATE(v.fecha_partido) = :fecha";
            $params[':fecha'] = $fecha;
        }

        if ($codigoCancha !== null) {
            $sql .= " AND v.codigo_cancha = :codigo_cancha";
            $params[':codigo_cancha'] = $codigoCancha;
        }

        if ($idLocal !== null) {
            $sql .= " AND v.id_local = :id_local";
            $params[':id_local'] = $idLocal;
        }

        $sql .= " ORDER BY v.hora_partido ASC";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * Obtener locales disponibles
     */
    public function getAvailableLocals(): array
    {
        $stmt = $this->pdo->query('CALL GetAvailableLocalsWithVideos()');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * Obtener videos del usuario (dueño)
     */
    public function getOwnerVideos(int $idLocal, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('CALL GetOwnerVideos(:id_local, :limit, :offset)');
        $stmt->execute([
            ':id_local' => $idLocal,
            ':limit' => $limit,
            ':offset' => $offset,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * Contar videos del usuario (dueño)
     */
    public function countOwnerVideos(int $idLocal): int
    {
        $stmt = $this->pdo->prepare('CALL CountOwnerVideos(:id_local)');
        $stmt->execute([':id_local' => $idLocal]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Obtener todas las cámaras de un video (para multi-cámara del mismo partido)
     */
    public function getCamerasForVideoSession(
        string $codigoCancha,
        string $fechaPartido,
        string $horaPartido
    ): array {
        try {
            $stmt = $this->pdo->prepare('CALL GetCamerasForVideoSession(:codigo_cancha, :fecha_partido, :hora_partido)');
            $stmt->execute([
                ':codigo_cancha' => $codigoCancha,
                ':fecha_partido' => $fechaPartido,
                ':hora_partido' => $horaPartido,
            ]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return $rows;
        } catch (\PDOException $e) {
            error_log("getCamerasForVideoSession Error: " . $e->getMessage());
            return [];
        }
    }

    // ===== MÉTODOS ORIGINALES POR COMPATIBILIDAD =====

    public function findPaginated(?string $fecha, ?string $codigo, ?int $categoria, int $limit, int $offset): array
    {
        if ($categoria !== null && $fecha !== null && $codigo !== null) {
            $sql = 'CALL GetByCategoriaCodigoYFechaYPaginado(:categoria, :codigo, :fecha, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_INT);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        } elseif ($categoria !== null && $codigo !== null) {
            $sql = 'CALL GetByCategoriaCodigoYPaginado(:categoria, :codigo, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_INT);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
        } elseif ($categoria !== null && $fecha !== null) {
            $sql = 'CALL GetByCategoriaYFechaYPaginado(:categoria, :fecha, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        } elseif ($categoria !== null) {
            $sql = 'CALL GetByCategoriaYPaginado(:categoria, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_INT);
        } elseif ($fecha !== null && $codigo !== null) {
            $sql = 'CALL GetByCodigoYFechaYPaginado(:fecha, :codigo, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
        } elseif ($codigo !== null) {
            $sql = 'CALL GetByCodigoYPaginado(:codigo, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
        } elseif ($fecha !== null) {
            $sql = 'CALL GetAllFechaYPaginado(:fecha, :limit, :offset)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        } else {
            $stmt = $this->pdo->prepare('CALL GetAllPaginado(:limit, :offset)');
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $rows;
    }

    public function countForFilters(?string $fecha, ?string $codigo, ?int $categoria): int
    {
        $stmt = $this->pdo->prepare('CALL CountVideosFiltros(:fecha, :codigo, :categoria)');
        $stmt->bindValue(':fecha', $fecha, $fecha === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':codigo', $codigo, $codigo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':categoria', $categoria, $categoria === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        $stmt->execute();
        $total = (int) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $total;
    }

    public function findByCode(string $codigoVideo): ?array
    {
        // Intentar con el SP que incluye es_privado
        try {
            $stmt = $this->pdo->prepare('CALL GetVideoWithLocalPrivacy(:codigo_video)');
            $stmt->bindParam(':codigo_video', $codigoVideo, PDO::PARAM_STR);
            $stmt->execute();
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($video) {
                return $video;
            }
        } catch (\PDOException $e) {
            // Si el SP no existe, usar el original como fallback
        }

        // Fallback: usar SP original + consulta separada para es_privado
        try {
            $stmt = $this->pdo->prepare('CALL GetVideoPorCodigo(:codigo_video)');
            $stmt->bindParam(':codigo_video', $codigoVideo, PDO::PARAM_STR);
            $stmt->execute();
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($video && !empty($video['id_local'])) {
                // Consultar es_privado del local
                $stmtPriv = $this->pdo->prepare(
                    'SELECT COALESCE(es_privado, 0) AS es_privado FROM locales WHERE id_local = :id_local LIMIT 1'
                );
                $stmtPriv->execute([':id_local' => $video['id_local']]);
                $localData = $stmtPriv->fetch(PDO::FETCH_ASSOC);
                $stmtPriv->closeCursor();
                $video['es_privado'] = (int) ($localData['es_privado'] ?? 0);
            }

            return $video ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }
}
