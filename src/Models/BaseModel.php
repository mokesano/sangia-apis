<?php
declare(strict_types=1);

namespace Sangia\Api\Models;

use PDO;
use Sangia\Api\Config\Database;

abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    protected function count(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    protected function decodeJson(mixed $value): mixed
    {
        if (is_string($value) && ($value[0] === '[' || $value[0] === '{')) {
            return json_decode($value, true) ?? $value;
        }
        return $value;
    }
}
