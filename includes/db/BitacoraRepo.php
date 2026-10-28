<?php
declare(strict_types = 1);
/** Lee y escribe eventos de auditoría del sistema. */
final class BitacoraRepo {
    public function registrar(string $accion, string $tabla = 'sistema', ?string $registro = null, ?array $anteriores = null, ?array $nuevos = null, ?int $usuarioId = null, ?string $ip = null): void {
        $anteriores = self::quitarDatosSecretos($anteriores);
        $nuevos = self::quitarDatosSecretos($nuevos);
        $q = Conexion::obtener()->prepare('INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente) VALUES(:tabla,:registro,:accion,:antes,:despues,:usuario,:ip)');
        $q->execute(['tabla' => $tabla, 'registro' => $registro, 'accion' => $accion, 'antes' => $anteriores === null ? null: json_encode($anteriores, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'despues' => $nuevos === null ? null: json_encode($nuevos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'usuario' => $usuarioId, 'ip' => $ip ?? substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    }
    /** Evita guardar credenciales, hashes o tokens aunque un llamador los incluya por error. */
    private static function quitarDatosSecretos(?array $datos): ?array {
        if ($datos === null) return null;
        foreach ($datos as $clave => $valor) {
            if (preg_match('/password|contrasena|clave|hash|token|csrf|secret/i', (string)$clave)) {
                $datos[$clave] = '[OMITIDO]';
            } elseif (is_array($valor)) {
                $datos[$clave] = self::quitarDatosSecretos($valor);
            }
        }
        return $datos;
    }
    public function listar(array $filtros, int $limite, int $desplazamiento): array {
        $where = ['1=1'];
        $params = [];
        foreach (['tabla' => 'tabla_afectada', 'accion' => 'accion', 'usuario_id' => 'usuario_id'] as $key => $column) if (!empty($filtros[$key])) {
            $where[] = "$column = :$key";
            $params[$key] = $filtros[$key];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'fecha_hora >= :desde';
            $params['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'fecha_hora < DATE_ADD(:hasta,INTERVAL 1 DAY)';
            $params['hasta'] = $filtros['hasta'];
        }
        $sql = 'SELECT * FROM vw_bitacora WHERE ' . implode(' AND ', $where) . ' ORDER BY fecha_hora DESC LIMIT :limite OFFSET :offset';
        $q = Conexion::obtener()->prepare($sql);
        foreach ($params as $k => $v) $q->bindValue(':' . $k, $v);
        $q->bindValue(':limite', $limite, PDO::PARAM_INT);
        $q->bindValue(':offset', $desplazamiento, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll();
    }
}
