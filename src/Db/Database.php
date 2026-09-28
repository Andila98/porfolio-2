<?php

declare(strict_types=1);

namespace App\Db;

use App\Core\Env;
use PDO;

final class Database
{
    public static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::int('DB_PORT', 3306),
            Env::get('DB_NAME', 'portfolio'),
        );
        $pdo = new PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Store timestamps in the same zone PHP uses (bootstrap sets Africa/Nairobi).
        $pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        return $pdo;
    }
}
