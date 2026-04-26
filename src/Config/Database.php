<?php
declare(strict_types=1);

namespace Sangia\Api\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = strtolower(Config::get('DB_DRIVER', 'mysql'));
        $host   = Config::get('DB_HOST', '127.0.0.1');
        $port   = Config::get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $name   = Config::get('DB_DATABASE', 'wizdam_research');
        $user   = Config::get('DB_USERNAME', 'root');
        $pass   = Config::get('DB_PASSWORD', '');

        try {
            if ($driver === 'pgsql') {
                self::$instance = self::connectPgsql($host, $port, $name, $user, $pass);
            } else {
                self::$instance = self::connectMysql($host, $port, $name, $user, $pass);
            }
        } catch (PDOException $e) {
            self::abort('Database connection failed: ' . $e->getMessage());
        }

        (new Migrator(self::$instance))->migrate();

        return self::$instance;
    }

    private static function connectMysql(
        string $host, string $port, string $name, string $user, string $pass
    ): PDO {
        // Connect without selecting a database so we can CREATE it if needed
        $serverPdo = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $serverPdo->exec(
            "CREATE DATABASE IF NOT EXISTS `$name`
             CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        unset($serverPdo);

        return new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    private static function connectPgsql(
        string $host, string $port, string $name, string $user, string $pass
    ): PDO {
        // PostgreSQL: try to connect; if database missing, create via postgres DB
        try {
            return new PDO(
                "pgsql:host=$host;port=$port;dbname=$name",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'does not exist')) {
                $adminPdo = new PDO(
                    "pgsql:host=$host;port=$port;dbname=postgres",
                    $user, $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $adminPdo->exec("CREATE DATABASE \"$name\" ENCODING 'UTF8'");
                unset($adminPdo);

                return new PDO(
                    "pgsql:host=$host;port=$port;dbname=$name",
                    $user, $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            }
            throw $e;
        }
    }

    private static function abort(string $message): never
    {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
