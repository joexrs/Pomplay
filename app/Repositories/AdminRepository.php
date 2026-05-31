<?php

namespace App\Repositories;

use PDO;

final class AdminRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUser(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare('CALL FindAdminByUser(:usuario)');
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $admin ?: null;
    }
}
