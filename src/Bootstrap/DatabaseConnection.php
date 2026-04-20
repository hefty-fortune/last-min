<?php

declare(strict_types=1);

namespace App\Bootstrap;

use PDO;

final class DatabaseConnection
{
    public static function fromEnvironment(): PDO
    {
        $dsn = self::dsnFromEnvironment();
        $username = getenv('DB_USER') ?: null;
        $password = getenv('DB_PASSWORD') ?: null;

        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private static function dsnFromEnvironment(): string
    {
        $explicitDsn = getenv('DB_DSN');
        if (is_string($explicitDsn) && $explicitDsn !== '') {
            return $explicitDsn;
        }

        $driver = getenv('DB_DRIVER') ?: 'pgsql';
        if ($driver !== 'pgsql') {
            throw new \RuntimeException('Unsupported DB_DRIVER. Local development supports only pgsql.');
        }

        $host = getenv('DB_HOST') ?: 'postgres';
        $port = getenv('DB_PORT') ?: '5432';
        $database = getenv('DB_NAME') ?: 'lastmin';

        return sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database);
    }
}
