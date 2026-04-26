<?php
declare(strict_types=1);

namespace Sangia\Api\Models;

class User extends BaseModel
{
    public function findByEmail(string $email): ?array
    {
        return $this->queryOne(
            "SELECT * FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );
    }

    public function findByOrcid(string $orcid): ?array
    {
        return $this->queryOne(
            "SELECT * FROM users WHERE orcid_id = :orcid LIMIT 1",
            [':orcid' => $orcid]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->queryOne(
            "SELECT id, name, email, role, orcid_id FROM users WHERE id = :id LIMIT 1",
            [':id' => $id]
        );
    }

    public function create(array $data): int
    {
        return $this->execute(
            "INSERT INTO users (name, email, password, orcid_id, role, created_at)
             VALUES (:name, :email, :password, :orcid, :role, NOW())",
            [
                ':name'     => $data['name'],
                ':email'    => $data['email'],
                ':password' => $data['password'] ?? null,
                ':orcid'    => $data['orcid_id'] ?? null,
                ':role'     => $data['role'] ?? 'viewer',
            ]
        );
    }

    public function updateOrcid(int $id, string $orcidId): void
    {
        $this->execute(
            "UPDATE users SET orcid_id = :orcid WHERE id = :id",
            [':orcid' => $orcidId, ':id' => $id]
        );
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
