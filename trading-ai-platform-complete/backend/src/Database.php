<?php
namespace App;

use PDO;

class Database {
    private static ?PDO $pdo = null;
    public static function connection(): PDO {
        if (!self::$pdo) {
            self::$pdo = new PDO(
                getenv('DATABASE_URL') ?: 'pgsql:host=postgres;port=5432;dbname=trading',
                getenv('DB_USER') ?: 'trading',
                getenv('DB_PASSWORD') ?: 'trading',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        return self::$pdo;
    }
}
