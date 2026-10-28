<?php
declare(strict_types = 1);
/** Consulta roles, estados, permisos y catálogos configurables. */
final class CatalogoRepo {
    public function lista(?string $tipo = null): array {
        if ($tipo !== null) {
            $q = Conexion::obtener()->prepare('SELECT tipo,id,codigo,nombre,descripcion,color,orden FROM vw_catalogos WHERE tipo=:tipo ORDER BY orden,nombre');
            $q->execute(['tipo' => $tipo]);
            return $q->fetchAll();
        }
        return Conexion::obtener()->query('SELECT tipo,id,codigo,nombre,descripcion,color,orden FROM vw_catalogos ORDER BY tipo,orden,nombre')->fetchAll();
    }
    public function estadoId(string $entidad, string $codigo): int {
        $q = Conexion::obtener()->prepare('SELECT id_estado FROM estados WHERE tipo_entidad=:entidad AND codigo=:codigo AND eliminado=0 LIMIT 1');
        $q->execute(['entidad' => $entidad, 'codigo' => $codigo]);
        $id = $q->fetchColumn();
        if ($id === false) throw new DomainException('El estado solicitado no está configurado.');
        return (int) $id;
    }
    public function puedeTransicionar(string $entidad, int $origen, int $destino, int $rolId): ?bool {
        $q = Conexion::obtener()->prepare('SELECT requiere_comentario FROM transiciones_estado WHERE entidad=:entidad AND id_estado_origen=:origen AND id_estado_destino=:destino AND id_rol_permitido=:rol AND eliminado=0 LIMIT 1');
        $q->execute(['entidad' => $entidad, 'origen' => $origen, 'destino' => $destino, 'rol' => $rolId]);
        $v = $q->fetchColumn();
        return $v === false ? null: (bool) $v;
    }
    public function rolId(string $codigo): int {
        $q = Conexion::obtener()->prepare('SELECT id_rol FROM roles WHERE codigo=:codigo AND eliminado=0 LIMIT 1');
        $q->execute(['codigo' => $codigo]);
        return (int) $q->fetchColumn();
    }
    public function transiciones(string $entidad, string $estadoCodigo, int $rolId): array {
        $q = Conexion::obtener()->prepare('SELECT d.codigo AS estado_codigo,d.nombre AS estado_nombre,d.color AS estado_color,t.requiere_comentario FROM transiciones_estado t JOIN tipos_entidad te ON te.codigo=t.entidad AND te.eliminado=0 JOIN estados o ON o.id_estado=t.id_estado_origen AND o.tipo_entidad=t.entidad AND o.codigo=:estado AND o.eliminado=0 JOIN estados d ON d.id_estado=t.id_estado_destino AND d.tipo_entidad=t.entidad AND d.eliminado=0 JOIN roles r ON r.id_rol=t.id_rol_permitido AND r.eliminado=0 WHERE t.entidad=:entidad AND t.id_rol_permitido=:rol AND t.eliminado=0 ORDER BY d.orden,d.nombre');
        $q->execute(['entidad' => $entidad, 'estado' => $estadoCodigo, 'rol' => $rolId]);
        return $q->fetchAll();
    }
    public function idActivo(string $catalogo, int $id): bool {
        $map = ['areas' => ['areas', 'id_area'], 'carreras' => ['carreras', 'id_carrera'], 'modalidades' => ['modalidades', 'id_modalidad']];
        if (!isset($map[$catalogo])) return false;
        [$tabla, $pk] = $map[$catalogo];
        $q = Conexion::obtener()->prepare('SELECT 1 FROM ' . $tabla . ' WHERE ' . $pk . '=:id AND eliminado=0 LIMIT 1');
        $q->execute(['id' => $id]);
        return (bool)$q->fetchColumn();
    }
    public function tienePermiso(int $usuarioId, string $permiso): bool {
        $q = Conexion::obtener()->prepare('SELECT 1 FROM vw_usuario_permisos WHERE id_usuario=:id AND permiso_codigo=:permiso LIMIT 1');
        $q->execute(['id' => $usuarioId, 'permiso' => $permiso]);
        return (bool) $q->fetchColumn();
    }
    public function administrar(string $catalogo, string $accion, array $d, ?int $id, int $actor): mixed {
        $map = [
            'areas' => ['tabla' => 'areas', 'pk' => 'id_area', 'campos' => ['codigo', 'nombre', 'descripcion']],
            'carreras' => ['tabla' => 'carreras', 'pk' => 'id_carrera', 'campos' => ['codigo', 'nombre']],
            'modalidades' => ['tabla' => 'modalidades', 'pk' => 'id_modalidad', 'campos' => ['codigo', 'nombre']],
            'tipos_entidad' => ['tabla' => 'tipos_entidad', 'pk' => 'id_tipo_entidad', 'campos' => ['codigo', 'nombre']],
            'estados' => ['tabla' => 'estados', 'pk' => 'id_estado', 'campos' => ['tipo_entidad', 'codigo', 'nombre', 'descripcion', 'color', 'orden']],
            'roles' => ['tabla' => 'roles', 'pk' => 'id_rol', 'campos' => ['codigo', 'nombre', 'descripcion']],
            'permisos' => ['tabla' => 'permisos', 'pk' => 'id_permiso', 'campos' => ['codigo', 'nombre', 'descripcion']],
            'mensajes' => ['tabla' => 'mensajes', 'pk' => 'id_mensaje', 'campos' => ['codigo', 'texto', 'tipo']],
            'transiciones' => ['tabla' => 'transiciones_estado', 'pk' => 'id_transicion', 'campos' => ['entidad', 'id_estado_origen', 'id_estado_destino', 'id_rol_permitido', 'requiere_comentario']],
        ];
        if (!isset($map[$catalogo])) throw new DomainException('Catálogo no permitido.');
        $m = $map[$catalogo];
        $pdo = Conexion::obtener();
        if ($accion === 'listar' || $accion === 'listar_eliminados') {
            $filtro = $accion === 'listar_eliminados' ? '1': '0';
            $q = $pdo->query('SELECT * FROM ' . $m['tabla'] . ' WHERE eliminado=' . $filtro . ' ORDER BY ' . $m['pk'] . ' DESC');
            return $q->fetchAll();
        }
        if ($accion === 'crear' || $accion === 'actualizar') {
            $values = [];
            $columns = [];
            foreach ($m['campos'] as $campo) {
                if (!array_key_exists($campo, $d)) throw new DomainException('Falta el campo ' . $campo . '.');
                $valor = $d[$campo];
                if (!is_scalar($valor) && $valor !== null) throw new DomainException('El campo ' . $campo . ' no es válido.');
                if ($campo === 'requiere_comentario') {
                    $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($valor === null) throw new DomainException('El campo requiere_comentario debe ser verdadero o falso.');
                    $valor = (int)$valor;
                } elseif (in_array($campo, ['id_estado_origen', 'id_estado_destino', 'id_rol_permitido', 'orden'], true)) {
                    if (filter_var($valor, FILTER_VALIDATE_INT) === false || (int)$valor < 1) throw new DomainException('El campo ' . $campo . ' debe ser un entero positivo.');
                    $valor = (int)$valor;
                } else {
                    $valor = $valor === null ? null: trim((string)$valor);
                    $limite = match($campo) {
                        'codigo' => 100, 'nombre' => 180, 'descripcion' => 5000, 'texto' => 5000, 'tipo_entidad', 'entidad', 'tipo' => 40, 'color' => 16, default => 255
                    };
                    if ($valor !== null && mb_strlen($valor) > $limite) throw new DomainException('El campo ' . $campo . ' excede su longitud permitida.');
                    if (in_array($campo, ['codigo', 'nombre', 'texto', 'tipo_entidad', 'entidad', 'color'], true) && ($valor === null || $valor === '')) throw new DomainException('El campo ' . $campo . ' es obligatorio.');
                    if ($campo === 'codigo' && !preg_match('/^[A-Za-z0-9_.-]+$/', $valor)) throw new DomainException('El código contiene caracteres no permitidos.');
                    if ($campo === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) throw new DomainException('El color debe usar el formato hexadecimal #RRGGBB.');
                }
                $columns[] = $campo;
                $values[$campo] = $valor;
            }
            if ($accion === 'crear') {
                $columns[] = 'creado_por';
                $values['creado_por'] = $actor;
                $marks = array_map(static fn($c) => ':' . $c, $columns);
                $q = $pdo->prepare('INSERT INTO ' . $m['tabla'] . '(' . implode(',', $columns) . ') VALUES(' . implode(',', $marks) . ')');
                $q->execute($values);
                return (int)$pdo->lastInsertId();
            }
            if (!$id) throw new DomainException('Identificador de catálogo inválido.');
            $sets = array_map(static fn($c) => $c . '=:' . $c, $columns);
            $values['actor'] = $actor;
            $values['id'] = $id;
            $q = $pdo->prepare('UPDATE ' . $m['tabla'] . ' SET ' . implode(',', $sets) . ',actualizado_por=:actor WHERE ' . $m['pk'] . '=:id AND eliminado=0');
            $q->execute($values);
            return $q->rowCount();
        }
        if (!$id) throw new DomainException('Identificador de catálogo inválido.');
        if ($accion === 'eliminar') {
            $q = $pdo->prepare('UPDATE ' . $m['tabla'] . ' SET eliminado=1,eliminado_en=NOW(),eliminado_por=:actor,actualizado_por=:actor WHERE ' . $m['pk'] . '=:id AND eliminado=0');
            $q->execute(['actor' => $actor, 'id' => $id]);
            return $q->rowCount();
        }
        if ($accion === 'restaurar') {
            $q = $pdo->prepare('UPDATE ' . $m['tabla'] . ' SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE ' . $m['pk'] . '=:id AND eliminado=1');
            $q->execute(['actor' => $actor, 'id' => $id]);
            return $q->rowCount();
        }
        throw new DomainException('Acción de catálogo no válida.');
    }
}
