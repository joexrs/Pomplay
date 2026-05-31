<?php

namespace App\Repositories;

use PDO;

class AuthRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Buscar usuario por nombre de usuario
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('CALL FindUserByUsername(:username)');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $user ?: null;
    }

    /**
     * Registrar último acceso
     */
    public function updateLastAccess(int $userId): void
    {
        $stmt = $this->db->prepare('CALL UpdateLastAccess(:id)');
        $stmt->execute([':id' => $userId]);
        $stmt->closeCursor();
    }

    /**
     * Buscar usuario por email
     */
    public function findByEmail(string $email): ?array
    {
        $query = <<<SQL
            SELECT 
                u.id_usuario,
                u.usuario,
                u.email,
                u.nombre_completo,
                u.id_rol,
                u.id_local,
                u.estado,
                r.nombre_rol as rol
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.email = :email AND u.estado = 1
            LIMIT 1
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':email' => $email]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtener usuario por ID
     */
    public function findById(int $userId): ?array
    {
        $query = <<<SQL
            SELECT 
                u.id_usuario,
                u.usuario,
                u.email,
                u.nombre_completo,
                u.foto,
                u.id_rol,
                u.id_local,
                u.estado,
                r.nombre_rol as rol,
                l.nombre_local as local_nombre
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            LEFT JOIN locales l ON u.id_local = l.id_local
            WHERE u.id_usuario = :id AND u.estado = 1
            LIMIT 1
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crear nuevo usuario
     */
    public function createUser(array $data): ?int
    {
        $query = <<<SQL
            INSERT INTO usuarios (usuario, password, email, nombre_completo, foto, id_rol, id_local, estado)
            VALUES (:usuario, :password, :email, :nombre_completo, :foto, :id_rol, :id_local, :estado)
        SQL;

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':usuario' => $data['usuario'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':email' => $data['email'],
                ':nombre_completo' => $data['nombre_completo'] ?? $data['usuario'],
                ':foto' => $data['foto'] ?? 'default.png',
                ':id_rol' => $data['id_rol'] ?? 1,
                ':id_local' => $data['id_local'] ?? null,
                ':estado' => $data['estado'] ?? 1,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Actualizar usuario
     */
    public function updateUser(int $userId, array $data): bool
    {
        $allowedFields = ['usuario', 'email', 'nombre_completo', 'foto', 'id_rol', 'id_local', 'estado'];
        $updateFields = [];
        $params = [':id' => $userId];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields, true)) {
                $updateFields[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $query = 'UPDATE usuarios SET ' . implode(', ', $updateFields) . ' WHERE id_usuario = :id';

        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Actualizar contraseña
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $query = 'UPDATE usuarios SET password = :password WHERE id_usuario = :id';

        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
                ':id' => $userId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Verificar contraseña
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Obtener todos los usuarios (para admin)
     */
    public function getAllUsers(int $limit = 50, int $offset = 0): array
    {
        $query = <<<SQL
            SELECT 
                u.id_usuario,
                u.usuario,
                u.email,
                u.nombre_completo,
                u.id_rol,
                u.id_local,
                u.estado,
                r.nombre_rol as rol,
                l.nombre_local as local_nombre,
                u.fecha_creacion
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            LEFT JOIN locales l ON u.id_local = l.id_local
            ORDER BY u.fecha_creacion DESC
            LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener usuarios por rol
     */
    public function getUsersByRole(int $roleId, int $limit = 50, int $offset = 0): array
    {
        $query = <<<SQL
            SELECT 
                u.id_usuario,
                u.usuario,
                u.email,
                u.nombre_completo,
                u.id_rol,
                u.id_local,
                u.estado,
                r.nombre_rol as rol
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.id_rol = :role_id
            LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener usuarios por local
     */
    public function getUsersByLocal(int $localId): array
    {
        $query = <<<SQL
            SELECT 
                u.id_usuario,
                u.usuario,
                u.email,
                u.nombre_completo,
                u.id_rol,
                u.estado,
                r.nombre_rol as rol
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.id_local = :local_id AND u.estado = 1
        SQL;

        $stmt = $this->db->prepare($query);
        $stmt->execute([':local_id' => $localId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Desactivar usuario (eliminación lógica)
     */
    public function deactivateUser(int $userId): bool
    {
        $query = 'UPDATE usuarios SET estado = 0 WHERE id_usuario = :id';

        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':id' => $userId]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Obtener roles disponibles
     */
    public function getAllRoles(): array
    {
        $query = <<<SQL
            SELECT id_rol, nombre_rol, descripcion
            FROM roles
            WHERE estado = 1
            ORDER BY nombre_rol
        SQL;

        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener rol por ID
     */
    public function getRoleById(int $roleId): ?array
    {
        $query = 'SELECT id_rol, nombre_rol, descripcion FROM roles WHERE id_rol = :id';

        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $roleId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtener rol por nombre
     */
    public function getRoleByName(string $roleName): ?array
    {
        $query = 'SELECT id_rol, nombre_rol, descripcion FROM roles WHERE nombre_rol = :name';

        $stmt = $this->db->prepare($query);
        $stmt->execute([':name' => $roleName]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
