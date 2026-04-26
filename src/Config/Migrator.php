<?php
declare(strict_types=1);

namespace Sangia\Api\Config;

use PDO;
use PDOException;

class Migrator
{
    public function __construct(private PDO $db) {}

    public function migrate(): void
    {
        if ($this->isUpToDate()) return;

        $driver     = strtolower(Config::get('DB_DRIVER', 'mysql'));
        $schemaFile = $this->resolveSchemaFile($driver);

        if (!file_exists($schemaFile)) return;

        $sql        = file_get_contents($schemaFile);
        $statements = $this->splitStatements($sql);

        foreach ($statements as $stmt) {
            try {
                $this->db->exec($stmt);
            } catch (PDOException) {
                // Skip: table already exists or benign DDL error
            }
        }
    }

    private function isUpToDate(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM researchers LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    private function resolveSchemaFile(string $driver): string
    {
        $base = dirname(__DIR__, 2) . '/database/';

        $candidates = match (true) {
            $driver === 'pgsql'                  => ['schema.pgsql.sql', 'schema.sql'],
            in_array($driver, ['mysql','mariadb']) => ['schema.mariadb.sql', 'schema.sql'],
            default                               => ['schema.sql'],
        };

        foreach ($candidates as $file) {
            if (file_exists($base . $file)) return $base . $file;
        }

        return $base . 'schema.sql';
    }

    private function splitStatements(string $sql): array
    {
        // Strip line comments, then split on `;`
        $sql   = preg_replace('/--[^\n]*\n/', "\n", $sql);
        $parts = explode(';', $sql);

        return array_values(array_filter(
            array_map('trim', $parts),
            fn($s) => strlen($s) > 5
        ));
    }
}
