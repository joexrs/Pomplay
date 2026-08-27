<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

final class EstadisticasRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function logBusqueda(
        ?int $idLocal,
        ?string $codigoCancha,
        ?string $fechaBuscada,
        ?string $horaBuscada,
        int $resultados,
        ?string $codigoVideoResultado = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO busqueda_video
                    (id_local, codigo_cancha, fecha_buscada, hora_buscada,
                     resultados_encontrados, codigo_video_resultado, ip_address, user_agent)
                 VALUES
                    (:id_local, :codigo_cancha, :fecha_buscada, :hora_buscada,
                     :resultados, :codigo_video, :ip, :ua)'
            );
            $stmt->execute([
                ':id_local'      => $idLocal,
                ':codigo_cancha' => $codigoCancha,
                ':fecha_buscada' => $fechaBuscada,
                ':hora_buscada'  => $horaBuscada,
                ':resultados'    => $resultados,
                ':codigo_video'  => $codigoVideoResultado,
                ':ip'            => $ipAddress,
                ':ua'            => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            ]);
        } catch (PDOException $e) {
            error_log('EstadisticasRepository::logBusqueda error: ' . $e->getMessage());
            // No interrumpir la búsqueda pública si la tabla aún no existe o la inserción falla
        }
    }

    public function logClip(array $data): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO clip
                    (codigo_video, id_local, codigo_cancha, video_url, clip_url, filename,
                     start_time, end_time, duracion, id_camara, ip_address)
                 VALUES
                    (:codigo_video, :id_local, :codigo_cancha, :video_url, :clip_url, :filename,
                     :start_time, :end_time, :duracion, :id_camara, :ip)'
            );
            $stmt->execute([
                ':codigo_video'  => $data['codigo_video'] ?? null,
                ':id_local'      => $data['id_local'] ?? null,
                ':codigo_cancha' => $data['codigo_cancha'] ?? null,
                ':video_url'     => $data['video_url'],
                ':clip_url'      => $data['clip_url'],
                ':filename'      => $data['filename'],
                ':start_time'    => $data['start_time'],
                ':end_time'      => $data['end_time'],
                ':duracion'      => $data['duracion'],
                ':id_camara'     => $data['id_camara'] ?? null,
                ':ip'            => $data['ip_address'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('EstadisticasRepository::logClip error: ' . $e->getMessage());
            // No interrumpir la generación del clip
        }
    }

    /** @return list<array<string, mixed>> */
    public function busquedasPorCancha(string $mes, ?int $idLocal = null): array
    {
        return $this->queryPorCancha(
            'busqueda_video b',
            'b.id_busqueda',
            'b.creado_en',
            'b.id_local',
            'b.codigo_cancha',
            $mes,
            $idLocal
        );
    }

    /** @return list<array<string, mixed>> */
    public function clipsPorCancha(string $mes, ?int $idLocal = null): array
    {
        return $this->queryPorCancha(
            'clip cl',
            'cl.id_clip',
            'cl.creado_en',
            'cl.id_local',
            'cl.codigo_cancha',
            $mes,
            $idLocal
        );
    }

    /** @return list<array<string, mixed>> */
    public function grabacionesPorCancha(string $mes, ?int $idLocal = null): array
    {
        try {
            [$inicio, $fin] = $this->getMonthRange($mes);

            $sql = "SELECT
                    l.nombre_local,
                    c.codigo_cancha,
                    c.descripcion AS cancha_nombre,
                    COUNT(v.codigo_video) AS total
                 FROM video v
                 JOIN cancha c
                   ON v.codigo_cancha = c.codigo_cancha
                  AND c.id_local = v.id_local
                 JOIN locales l ON v.id_local = l.id_local
                 WHERE v.estado = 1
                   AND v.fecha_partido >= :inicio
                   AND v.fecha_partido < :fin";

            if ($idLocal !== null) {
                $sql .= ' AND v.id_local = :id_local';
            }

            $sql .= "
                 GROUP BY l.id_local, c.codigo_cancha, l.nombre_local, c.descripcion
                 ORDER BY total DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':inicio', $inicio);
            $stmt->bindValue(':fin', $fin);
            if ($idLocal !== null) {
                $stmt->bindValue(':id_local', $idLocal, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }
    }

    public function totalBusquedasMes(string $mes, ?int $idLocal = null): int
    {
        return $this->totalMes('busqueda_video', 'creado_en', $mes, $idLocal);
    }

    public function totalClipsMes(string $mes, ?int $idLocal = null): int
    {
        return $this->totalMes('clip', 'creado_en', $mes, $idLocal);
    }

    public function totalGrabacionesMes(string $mes, ?int $idLocal = null): int
    {
        try {
            [$inicio, $fin] = $this->getMonthRange($mes);

            $sql = "SELECT COUNT(*) FROM video
                 WHERE estado = 1
                   AND fecha_partido >= :inicio
                   AND fecha_partido < :fin";

            if ($idLocal !== null) {
                $sql .= ' AND id_local = :id_local';
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':inicio', $inicio);
            $stmt->bindValue(':fin', $fin);
            if ($idLocal !== null) {
                $stmt->bindValue(':id_local', $idLocal, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    public function logReproduccion(
        ?string $codigoVideo,
        ?int    $idLocal,
        ?string $codigoCancha,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO reproduccion_video
                    (codigo_video, id_local, codigo_cancha, ip_address, user_agent)
                 VALUES
                    (:codigo_video, :id_local, :codigo_cancha, :ip, :ua)'
            );
            $stmt->execute([
                ':codigo_video'  => $codigoVideo ?: null,
                ':id_local'      => $idLocal,
                ':codigo_cancha' => $codigoCancha ?: null,
                ':ip'            => $ipAddress,
                ':ua'            => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            ]);
        } catch (PDOException $e) {
            error_log('EstadisticasRepository::logReproduccion error: ' . $e->getMessage());
            // No interrumpir la reproducción pública si la tabla aún no existe
        }
    }

    public function totalReproduccionesMes(string $mes, ?int $idLocal = null): int
    {
        return $this->totalMes('reproduccion_video', 'creado_en', $mes, $idLocal);
    }

    /** @return list<array<string, mixed>> */
    public function reproduccionesPorCancha(string $mes, ?int $idLocal = null): array
    {
        return $this->queryPorCancha(
            'reproduccion_video rv',
            'rv.id_reproduccion',
            'rv.creado_en',
            'rv.id_local',
            'rv.codigo_cancha',
            $mes,
            $idLocal
        );
    }

    /** @return list<array<string, mixed>> */
    private function queryPorCancha(
        string $fromAlias,
        string $countField,
        string $dateField,
        string $localField,
        string $canchaField,
        string $mes,
        ?int $idLocal = null
    ): array {
        try {
            [$inicio, $fin] = $this->getMonthRange($mes);
            $alias = explode(' ', $fromAlias)[1];
            $sql = "SELECT
                    COALESCE(l.nombre_local, 'Sin local') AS nombre_local,
                    COALESCE(c.descripcion, {$alias}.codigo_cancha, 'Sin cancha') AS cancha_nombre,
                    COALESCE({$alias}.codigo_cancha, '—') AS codigo_cancha,
                    COUNT({$countField}) AS total
                 FROM {$fromAlias}
                 LEFT JOIN locales l ON {$localField} = l.id_local
                 LEFT JOIN cancha c
                   ON {$canchaField} COLLATE utf8mb4_general_ci = c.codigo_cancha
                  AND c.id_local = {$localField}
                 WHERE {$dateField} >= :inicio
                   AND {$dateField} < :fin";

            if ($idLocal !== null) {
                $sql .= ' AND ' . $localField . ' = :id_local';
            }

            $sql .= "
                 GROUP BY {$localField}, {$canchaField}, l.nombre_local, c.descripcion, {$alias}.codigo_cancha
                 ORDER BY total DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':inicio', $inicio);
            $stmt->bindValue(':fin', $fin);
            if ($idLocal !== null) {
                $stmt->bindValue(':id_local', $idLocal, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }
    }

    private function totalMes(string $table, string $dateField, string $mes, ?int $idLocal = null): int
    {
        try {
            [$inicio, $fin] = $this->getMonthRange($mes);
            $sql = "SELECT COUNT(*) FROM {$table}
                 WHERE {$dateField} >= :inicio
                   AND {$dateField} < :fin";

            if ($idLocal !== null) {
                $sql .= ' AND id_local = :id_local';
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':inicio', $inicio);
            $stmt->bindValue(':fin', $fin);
            if ($idLocal !== null) {
                $stmt->bindValue(':id_local', $idLocal, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    private function getMonthRange(string $mes): array
    {
        $dt = \DateTime::createFromFormat('Y-m', $mes);
        if ($dt === false) {
            $dt = new \DateTime('first day of this month');
        }
        $inicio = $dt->format('Y-m-01 00:00:00');
        $fin = (clone $dt)->modify('+1 month')->format('Y-m-01 00:00:00');
        return [$inicio, $fin];
    }
}
