<?php

namespace App\Repositories;

use PDO;

final class VideoPasswordRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findVideo(string $codigoVideo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT codigo_video, id_local, video_url, password_hash
             FROM video
             WHERE codigo_video = :codigo_video
             LIMIT 1'
        );
        $stmt->execute([':codigo_video' => $codigoVideo]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $video ?: null;
    }

    public function verifyPassword(string $codigoVideo, string $password): bool
    {
        $video = $this->findVideo($codigoVideo);
        $hash = (string) ($video['password_hash'] ?? '');

        return $hash !== '' && password_verify($password, $hash);
    }

    public function updatePassword(string $codigoVideo, ?string $password): bool
    {
        $hash = $password === null ? null : password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            'UPDATE video SET password_hash = :password_hash WHERE codigo_video = :codigo_video'
        );
        $stmt->execute([
            ':password_hash' => $hash,
            ':codigo_video' => $codigoVideo,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function canManageVideo(string $codigoVideo, int $propietarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM video v
             INNER JOIN locales l ON l.id_local = v.id_local
             WHERE v.codigo_video = :codigo_video
               AND l.id_propietario = :id_propietario
             LIMIT 1'
        );
        $stmt->execute([
            ':codigo_video' => $codigoVideo,
            ':id_propietario' => $propietarioId,
        ]);
        $allowed = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();

        return $allowed;
    }
}
