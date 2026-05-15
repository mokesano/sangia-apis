<?php
declare(strict_types=1);

namespace Sangia\Database;

use PDO;
use PDOException;
use Sangia\Api\Config\Config;

class Connection
{
    private static ?PDO $pdo = null;

    /**
     * Returns a shared PDO instance, or null if DB is not configured / unreachable.
     * Callers must handle the null case gracefully (e.g. fall back to file-based logic).
     */
    public static function get(): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver   = Config::get('DB_DRIVER', 'mysql');
        $host     = Config::get('DB_HOST');
        $port     = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE');
        $username = Config::get('DB_USERNAME');
        $password = Config::get('DB_PASSWORD', '');
        $charset  = Config::get('DB_CHARSET', 'utf8mb4');

        if (!$host || !$database || !$username) {
            return null;
        }

        $dsn = match ($driver) {
            'pgsql' => "pgsql:host=$host;port=$port;dbname=$database",
            default => "mysql:host=$host;port=$port;dbname=$database;charset=$charset",
        };

        try {
            self::$pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException) {
            self::$pdo = null;
        }

        return self::$pdo;
    }
}
