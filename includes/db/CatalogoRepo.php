<?php
declare(strict_types = 1);
/** Consulta roles, estados, permisos y catálogos configurables. */
final class CatalogoRepo {
    public function lista(?string $tipo = null): array {
        if ($tipo !== null) {
            $consultaSql = Conexion::obtener()->prepare('SELECT tipo,id,codigo,nombre,descripcion,color,orden FROM vw_catalogos WHERE tipo=:tipo ORDER BY orden,nombre');
            $consultaSql->execute(['tipo' => $tipo]);
            return $consultaSql->fetchAll();
        }
        return Conexion::obtener()->query('SELECT tipo,id,codigo,nombre,descripcion,color,orden FROM vw_catalogos ORDER BY tipo,orden,nombre')->fetchAll();
    }
    public function estadoId(string $entidad, string $codigo): int {
        $consultaSql = Conexion::obtener()->prepare('SELECT id_estado FROM estados WHERE tipo_entidad=:entidad AND codigo=:codigo AND eliminado=0 LIMIT 1');
        $consultaSql->execute(['entidad' => $entidad, 'codigo' => $codigo]);
        $id = $consultaSql->fetchColumn();
        if ($id === false) throw new DomainException('El estado solicitado no está configurado.');
        return (int) $id;
    }
    public function puedeTransicionar(string $entidad, int $origen, int $destino, int $rolId): ?bool {
        $consultaSql = Conexion::obtener()->prepare('SELECT requiere_comentario FROM transiciones_estado WHERE entidad=:entidad AND id_estado_origen=:origen AND id_estado_destino=:destino AND id_rol_permitido=:rol AND eliminado=0 LIMIT 1');
        $consultaSql->execute(['entidad' => $entidad, 'origen' => $origen, 'destino' => $destino, 'rol' => $rolId]);
        $valorCampo = $consultaSql->fetchColumn();
        if ($valorCampo !== false) return (bool) $valorCampo;
        if (
            $entidad === 'usuario' &&
            in_array($rolId, [$this->rolId('admin'), $this->rolId('superadmin')], true) &&
            $origen !== $destino
        ) {
            $codigoDestino = Conexion::obtener()->prepare("SELECT codigo FROM estados WHERE id_estado=:id AND tipo_entidad='usuario' AND eliminado=0 LIMIT 1");
            $codigoDestino->execute(['id' => $destino]);
            return $codigoDestino->fetchColumn() === 'rechazado';
        }
        if (
            $entidad === 'practica' &&
            in_array($rolId, [$this->rolId('admin'), $this->rolId('superadmin')], true) &&
            $origen !== $destino
        ) {
            $codigoDestino = Conexion::obtener()->prepare("SELECT codigo FROM estados WHERE id_estado=:id AND tipo_entidad='practica' AND eliminado=0 LIMIT 1");
            $codigoDestino->execute(['id' => $destino]);
            $codigo = $codigoDestino->fetchColumn();
            if ($codigo !== false) return in_array($codigo, ['rechazada', 'cambios_solicitados', 'cancelada'], true);
        }
        return null;
    }
    public function rolId(string $codigo): int {
        $consultaSql = Conexion::obtener()->prepare('SELECT id_rol FROM roles WHERE codigo=:codigo AND eliminado=0 LIMIT 1');
        $consultaSql->execute(['codigo' => $codigo]);
        return (int) $consultaSql->fetchColumn();
    }
    public function transiciones(string $entidad, string $estadoCodigo, int $rolId): array {
        $consultaSql = Conexion::obtener()->prepare('SELECT d.codigo AS estado_codigo,d.nombre AS estado_nombre,d.color AS estado_color,t.requiere_comentario FROM transiciones_estado t JOIN tipos_entidad te ON te.codigo=t.entidad AND te.eliminado=0 JOIN estados o ON o.id_estado=t.id_estado_origen AND o.tipo_entidad=t.entidad AND o.codigo=:estado AND o.eliminado=0 JOIN estados d ON d.id_estado=t.id_estado_destino AND d.tipo_entidad=t.entidad AND d.eliminado=0 JOIN roles r ON r.id_rol=t.id_rol_permitido AND r.eliminado=0 WHERE t.entidad=:entidad AND t.id_rol_permitido=:rol AND t.eliminado=0 ORDER BY d.orden,d.nombre');
        $consultaSql->execute(['entidad' => $entidad, 'estado' => $estadoCodigo, 'rol' => $rolId]);
        $opciones = $consultaSql->fetchAll();
        if ($entidad === 'usuario' && $estadoCodigo === 'pendiente' && $rolId === $this->rolId('admin')) {
            $yaIncluyeActivo = array_filter($opciones, static fn(array $opcion): bool => $opcion['estado_codigo'] === 'activo');
            if (!$yaIncluyeActivo) {
                $activo = Conexion::obtener()->prepare("SELECT codigo AS estado_codigo,nombre AS estado_nombre,color AS estado_color,0 AS requiere_comentario FROM estados WHERE tipo_entidad='usuario' AND codigo='activo' AND eliminado=0 LIMIT 1");
                $activo->execute();
                $opcionActiva = $activo->fetch();
                if ($opcionActiva) $opciones[] = $opcionActiva;
            }
        }
        if (
            $entidad === 'usuario' &&
            in_array($rolId, [$this->rolId('admin'), $this->rolId('superadmin')], true)
        ) {
            $todos = Conexion::obtener()->prepare("SELECT codigo AS estado_codigo,nombre AS estado_nombre,color AS estado_color FROM estados WHERE tipo_entidad='usuario' AND eliminado=0 ORDER BY orden,nombre");
            $todos->execute();
            $existentes = array_column($opciones, null, 'estado_codigo');
            foreach ($todos->fetchAll() as $opcion) {
                if ($opcion['estado_codigo'] === $estadoCodigo || isset($existentes[$opcion['estado_codigo']])) continue;
                $opcion['requiere_comentario'] = $opcion['estado_codigo'] === 'rechazado' ? 1 : 0;
                $opciones[] = $opcion;
            }
            usort($opciones, static fn(array $a, array $b): int => strnatcasecmp((string)$a['estado_nombre'], (string)$b['estado_nombre']));
        }
        if (
            $entidad === 'practica' &&
            in_array($rolId, [$this->rolId('admin'), $this->rolId('superadmin')], true)
        ) {
            $todos = Conexion::obtener()->prepare("SELECT codigo AS estado_codigo,nombre AS estado_nombre,color AS estado_color FROM estados WHERE tipo_entidad='practica' AND eliminado=0 ORDER BY orden,nombre");
            $todos->execute();
            $existentes = array_column($opciones, null, 'estado_codigo');
            foreach ($todos->fetchAll() as $opcion) {
                if ($opcion['estado_codigo'] === $estadoCodigo || isset($existentes[$opcion['estado_codigo']])) continue;
                $opcion['requiere_comentario'] = in_array($opcion['estado_codigo'], ['rechazada', 'cambios_solicitados', 'cancelada'], true) ? 1 : 0;
                $opciones[] = $opcion;
            }
            usort($opciones, static fn(array $a, array $b): int => strnatcasecmp((string)$a['estado_nombre'], (string)$b['estado_nombre']));
        }
        return $opciones;
    }
    public function idActivo(string $catalogo, int $id): bool {
        $map = ['areas' => ['areas', 'id_area'], 'carreras' => ['carreras', 'id_carrera'], 'modalidades' => ['modalidades', 'id_modalidad']];
        if (!isset($map[$catalogo])) return false;
        [$tabla, $pk] = $map[$catalogo];
        $consultaSql = Conexion::obtener()->prepare('SELECT 1 FROM ' . $tabla . ' WHERE ' . $pk . '=:id AND eliminado=0 LIMIT 1');
        $consultaSql->execute(['id' => $id]);
        return (bool)$consultaSql->fetchColumn();
    }
    public function tienePermiso(int $usuarioId, string $permiso): bool {
        $consultaSql = Conexion::obtener()->prepare('SELECT 1 FROM vw_usuario_permisos WHERE id_usuario=:id AND permiso_codigo=:permiso LIMIT 1');
        $consultaSql->execute(['id' => $usuarioId, 'permiso' => $permiso]);
        return (bool) $consultaSql->fetchColumn();
    }
    public function administrar(string $catalogo, string $accion, array $datosEntrada, ?int $id, int $actor): mixed {
        $map = [
            'areas' => ['tabla' => 'areas', 'pk' => 'id_area', 'campos' => ['codigo', 'nombre', 'descripcion']],
            'carreras' => ['tabla' => 'carreras', 'pk' => 'id_carrera', 'campos' => ['codigo', 'nombre']],
            'modalidades' => ['tabla' => 'modalidades', 'pk' => 'id_modalidad', 'campos' => ['codigo', 'nombre']],
            'tipos_entidad' => ['tabla' => 'tipos_entidad', 'pk' => 'id_tipo_entidad', 'campos' => ['codigo', 'nombre']],
            'estados' => ['tabla' => 'estados', 'pk' => 'id_estado', 'campos' => ['tipo_entidad', 'codigo', 'nombre', 'descripcion', 'color', 'orden']],
            'roles' => ['tabla' => 'roles', 'pk' => 'id_rol', 'campos' => ['codigo', 'nombre', 'descripcion']],
            'permisos' => ['tabla' => 'permisos', 'pk' => 'id_permiso', 'campos' => ['codigo', 'nombre', 'descripcion']],
            'mensajes' => ['tabla' => 'mensajes', 'pk' => 'id_mensaje', 'campos' => ['codigo', 'texto', 'tipo']],
            'transiciones' => [
                'tabla' => 'transiciones_estado',
                'pk' => 'id_transicion',
                'campos' => ['entidad', 'id_estado_origen', 'id_estado_destino', 'id_rol_permitido', 'requiere_comentario']
            ],
        ];
        if (!isset($map[$catalogo])) throw new DomainException('Catálogo no permitido.');
        $m = $map[$catalogo];
        $pdo = Conexion::obtener();
        if ($accion === 'listar' || $accion === 'listar_eliminados') {
            $filtro = $accion === 'listar_eliminados' ? '1': '0';
            $consultaSql = $pdo->query('SELECT * FROM ' . $m['tabla'] . ' WHERE eliminado=' . $filtro . ' ORDER BY ' . $m['pk'] . ' DESC');
            return $consultaSql->fetchAll();
        }
        if ($accion === 'crear' || $accion === 'actualizar') {
            $values = [];
            $columns = [];
            foreach ($m['campos'] as $campo) {
                if (!array_key_exists($campo, $datosEntrada)) throw new DomainException('Falta el campo ' . $campo . '.');
                $valor = $datosEntrada[$campo];
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
                $consultaSql = $pdo->prepare('INSERT INTO ' . $m['tabla'] . '(' . implode(',', $columns) . ') VALUES(' . implode(',', $marks) . ')');
                $consultaSql->execute($values);
                return (int)$pdo->lastInsertId();
            }
            if (!$id) throw new DomainException('Identificador de catálogo inválido.');
            $sets = array_map(static fn($c) => $c . '=:' . $c, $columns);
            $values['actor'] = $actor;
            $values['id'] = $id;
            $consultaSql = $pdo->prepare('UPDATE ' . $m['tabla'] . ' SET ' . implode(',', $sets) . ',actualizado_por=:actor WHERE ' . $m['pk'] . '=:id AND eliminado=0');
            $consultaSql->execute($values);
            return $consultaSql->rowCount();
        }
        if (!$id) throw new DomainException('Identificador de catálogo inválido.');
        if ($accion === 'eliminar') {
            $consultaSql = $pdo->prepare('UPDATE ' . $m['tabla'] . ' SET eliminado=1,eliminado_en=NOW(),eliminado_por=:actor,actualizado_por=:actor WHERE ' . $m['pk'] . '=:id AND eliminado=0');
            $consultaSql->execute(['actor' => $actor, 'id' => $id]);
            return $consultaSql->rowCount();
        }
        if ($accion === 'restaurar') {
            $consultaSql = $pdo->prepare('UPDATE ' . $m['tabla'] . ' SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE ' . $m['pk'] . '=:id AND eliminado=1');
            $consultaSql->execute(['actor' => $actor, 'id' => $id]);
            return $consultaSql->rowCount();
        }
        throw new DomainException('Acción de catálogo no válida.');
    }
}
