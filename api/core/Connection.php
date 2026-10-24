<?php
declare(strict_types=1);
require_once __DIR__ . '/Configuration.php';

final class Connection
{
    // Reuse one PDO connection per request with native prepared statements enabled.
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $host = getenv('DATABASE_HOST') ?: (string)Env::get('DATABASE_URL', '127.0.0.1');
        $name = getenv('DATABASE_NAME') ?: (string)Env::get('DATABASE_NAME', 'sistema_practicas');
        $user = getenv('DATABASE_USER') ?: (string)Env::get('DATABASE_USER', 'root');
        $pass = getenv('DATABASE_PASSWORD') ?: (string)Env::get('DATABASE_PSSW', '');
        self::$pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
