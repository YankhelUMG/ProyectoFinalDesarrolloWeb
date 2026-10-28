<?php
declare(strict_types = 1);
/** Obtiene y actualiza parámetros que controlan el comportamiento del sistema. */
final class ConfiguracionRepo {
    public function obtener(string $clave, ?string $predeterminado = null): ?string {
        $q = Conexion::obtener()->prepare('SELECT valor FROM configuracion WHERE clave=:clave AND eliminado=0 LIMIT 1');
        $q->execute(['clave' => $clave]);
        $valor = $q->fetchColumn();
        return $valor === false ? $predeterminado: (string) $valor;
    }
    public function listar(): array {
        return Conexion::obtener()->query('SELECT clave,valor,tipo,descripcion,editable FROM configuracion WHERE eliminado=0 ORDER BY clave')->fetchAll();
    }
    public function publicos(): array {
        return Conexion::obtener()->query("SELECT clave,valor FROM configuracion WHERE eliminado=0 AND clave IN ('nombre_sistema','password_longitud_minima','cv_tamano_maximo_bytes')")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    public function guardar(string $clave, string $valor, int $actor): void {
        $q = Conexion::obtener()->prepare('SELECT tipo,editable FROM configuracion WHERE clave=:clave AND eliminado=0 LIMIT 1');
        $q->execute(['clave' => $clave]);
        $dato = $q->fetch();
        if (!$dato || (int)$dato['editable'] !== 1) throw new DomainException('La configuración no existe o no se puede editar.');
        if ($dato['tipo'] === 'entero' && filter_var($valor, FILTER_VALIDATE_INT) === false) throw new DomainException('El valor debe ser un entero.');
        if ($dato['tipo'] === 'entero') {
            $rangos = [
                'sesion_inactividad_minutos' => [1, 1440], 'login_intentos_maximos' => [1, 50],
                'login_bloqueo_minutos' => [1, 1440], 'cv_tamano_maximo_bytes' => [1024, 104857600],
                'password_longitud_minima' => [8, 200], 'comentario_rechazo_minimo' => [1, 500],
                'paginacion_tamano' => [1, 500], 'paginacion_maxima' => [1, 1000], 'cupo_maximo_permitido' => [1, 100000],
            ];
            if (isset($rangos[$clave]) && ((int)$valor < $rangos[$clave][0] || (int)$valor > $rangos[$clave][1])) {
                throw new DomainException('El valor está fuera del rango permitido para esta configuración.');
            }
        }
        if ($dato['tipo'] === 'decimal' && !is_numeric($valor)) throw new DomainException('El valor debe ser numérico.');
        if ($dato['tipo'] === 'booleano' && !in_array(strtolower($valor), ['0', '1', 'true', 'false'], true)) throw new DomainException('El valor debe ser verdadero o falso.');
        if ($dato['tipo'] === 'json' && json_decode($valor, true) === null && trim($valor) !== 'null') throw new DomainException('El valor debe contener JSON válido.');
        $q = Conexion::obtener()->prepare('UPDATE configuracion SET valor=:valor,actualizado_por=:actor WHERE clave=:clave AND eliminado=0 AND editable=1');
        $q->execute(['valor' => $valor, 'actor' => $actor, 'clave' => $clave]);
    }
}
