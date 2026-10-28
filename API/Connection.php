<?php
declare(strict_types = 1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
final class Connection {
    private PDO $pdo;
    public function __construct() {
        $this->pdo = Conexion::obtener();
    }
    public static function get(): PDO {
        return Conexion::obtener();
    }
    public function __call(string $metodo, array $argumentos): mixed {
        return $this->pdo-> {
            $metodo
        }
        (...$argumentos);
    }
}
