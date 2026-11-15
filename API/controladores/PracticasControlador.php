<?php
declare(strict_types=1);
/** Propuestas y revisión de prácticas. */
final class PracticasControlador extends ControladorBase {
    public function rutasPracticas(): never {
        $sufijos = array_slice($this->ruta, 1);
        $cierre = new ServicioCierre();
        if ($this->metodo === 'GET' && $sufijos === []) {
            $cierre->procesarAutomaticos();
            $p = $this->paginacionPracticas();
            $rows = $this->practicas->catalogo($_GET, $p['limite'] + 1, $p['offset']);
            $haySiguiente = count($rows) > $p['limite'];
            Respuesta::exito([
                'elementos' => array_slice($rows, 0, $p['limite']),
                'pagina' => $p['pagina'],
                'por_pagina' => $p['limite'],
                'hay_siguiente' => $haySiguiente
            ]);
        }
        if ($this->metodo === 'GET' && ($sufijos[0] ?? '') === 'mias') {
            $cierre->procesarAutomaticos();
            $cuentaActual = Acceso::requerirRol(['empresa'], true);
            Acceso::requerirPermiso('practicas.gestionar_propias');
            Respuesta::exito($this->practicas->listarEmpresa((int)$cuentaActual['id_usuario']));
        }
        if ($this->metodo === 'GET' && ($sufijos[0] ?? '') === 'admin') {
            $cuentaActual = Acceso::requerirRol(['admin', 'superadmin']);
            Acceso::requerirPermiso('practicas.consultar');
            $cierre->procesarAutomaticos();
            $p = $this->paginacion();
            Respuesta::exito([
                'elementos' => $this->practicas->listarAdmin($p, $p['limite'], $p['offset'], isset($_GET['estado']) ? (string)$_GET['estado']: null),
                'pagina' => $p['pagina'],
                'por_pagina' => $p['limite']
            ]);
        }
        if ($this->metodo === 'POST' && $sufijos === []) $this->crearPractica();
        $id = (int)($sufijos[0] ?? 0);
        if ($id < 1) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'Práctica no encontrada.'), 404);
        if ($this->metodo === 'GET' && count($sufijos) === 1) {
            $cierre->procesarAutomaticos();
            $haySesion = isset($_SESSION['usuario_id']) || isset($_SESSION['user_id']);
            if (!$haySesion) {
                $row = $this->practicas->una($id);
            } else {
                $lector = Sesion::actual(true);
                $row = match ($lector['rol_codigo']) {
                    // Las empresas pueden consultar cualquier práctica publicada,
                    // además de ver sus propias prácticas aunque estén en revisión.
                    'empresa' => $this->practicas->unaEmpresa($id, (int)$lector['id_usuario'])
                        ?? $this->practicas->una($id),
                    'admin', 'superadmin' => $this->practicas->unaAdmin($id),
                    default => $this->practicas->una($id),
                };
                if (in_array($lector['rol_codigo'], ['admin', 'superadmin'], true)) Acceso::requerirPermiso('practicas.consultar');
            }
            if (!$row) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'Práctica no encontrada.'), 404);
            Respuesta::exito($row);
        }
        if (($sufijos[1] ?? '') === 'postulaciones' && $this->metodo === 'GET') {
            $cierre->procesarAutomaticos();
            $cuentaActual = Acceso::requerirRol(['empresa']);
            Acceso::requerirPermiso('postulaciones.consultar_empresa');
            Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
            Respuesta::exito($this->postulaciones->empresa((int)$cuentaActual['id_usuario'], $id));
        }
        if (($sufijos[1] ?? '') === 'postulaciones' && $this->metodo === 'POST') (new PostulacionesControlador($this->ruta, $this->metodo, $this->datos))->crearPostulacion($id);
        if (($sufijos[1] ?? '') === 'revision' && $this->metodo === 'PUT') $this->revisarPractica($id);
        if (($sufijos[1] ?? '') === 'cerrar' && $this->metodo === 'POST') $this->cerrarPractica($id, $cierre);
        if ($this->metodo === 'PUT' && count($sufijos) === 1) $this->editarPractica($id);
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    private function crearPractica(): never {
        $cuentaActual = Acceso::requerirRol(['empresa']);
        Acceso::requerirPermiso('practicas.gestionar_propias');
        Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        (new ServicioCierre())->procesarAutomaticos();
        $datosEntrada = $this->datosPractica();
        $empresa = $this->empresas->idEmpresa((int)$cuentaActual['id_usuario']);
        $estado = $this->catalogos->estadoId('practica', 'pendiente_revision');
        $id = $this->usuarios->transaccion(function()use($datosEntrada, $empresa, $estado, $cuentaActual) {
            Conexion::contextoAuditoria((int)$cuentaActual['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $id = $this->practicas->crear($datosEntrada + ['empresa' => $empresa, 'estado' => $estado, 'actor' => (int)$cuentaActual['id_usuario']]);
            $this->practicas->registrarHistorial($id, null, $estado, (int)$cuentaActual['id_usuario'], null);
            return $id;
        });
        Respuesta::exito(['id_practica' => $id], (new ConfiguracionRepo())->mensaje('exito_practica', 'Práctica enviada a revisión.'), 201);
    }
    private function editarPractica(int $id): never {
        $cuentaActual = Acceso::requerirRol(['empresa']);
        Acceso::requerirPermiso('practicas.gestionar_propias');
        Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        (new ServicioCierre())->procesarAutomaticos();
        $vigente = $this->practicas->unaEmpresa($id, (int)$cuentaActual['id_usuario']);
        if (!$vigente) throw new DomainException('La práctica no existe o no pertenece a tu empresa.');
        $datosEntrada = $this->datosPractica($vigente);
        $this->usuarios->transaccion(function()use($id, $cuentaActual, $datosEntrada) {
            Conexion::contextoAuditoria((int)$cuentaActual['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $row = $this->practicas->porIdParaActualizar($id, (int)$cuentaActual['id_usuario']);
            if (!$row) throw new DomainException('La práctica no existe o no pertenece a tu empresa.');
            $destino = $this->catalogos->estadoId('practica', 'pendiente_revision');
            $requiere = $this->catalogos->puedeTransicionar('practica', (int)$row['id_estado'], $destino, $this->catalogos->rolId('empresa'));
            if ($requiere === null) throw new DomainException('La práctica no admite cambios en su estado actual.');
            if ($this->postulaciones->aceptadas($id) > (int)$datosEntrada['cupo']) throw new DomainException('El cupo no puede reducirse por debajo de las postulaciones aceptadas.');
            $this->practicas->guardar($datosEntrada + ['estado' => $destino, 'comentario' => null, 'actor' => (int)$cuentaActual['id_usuario'], 'id' => $id]);
            $this->practicas->registrarHistorial($id, (int)$row['id_estado'], $destino, (int)$cuentaActual['id_usuario'], null);
        });
        Respuesta::exito(['id_practica' => $id], (new ConfiguracionRepo())->mensaje('exito_practica', 'Cambios enviados a revisión.'));
    }
    private function revisarPractica(int $id): never {
        $cuentaActual = Acceso::requerirRol(['admin', 'superadmin']);
        Acceso::requerirPermiso(
            $cuentaActual['rol_codigo'] === 'admin' ? 'practicas.consultar' : 'practicas.revisar',
        );
        $estado = $this->texto('estado_codigo', 40, true);
        $comentario = $this->texto('comentario', 2000, false);
        $destino = $this->catalogos->estadoId('practica', $estado);
        $this->usuarios->transaccion(function()use($id, $cuentaActual, $destino, $comentario) {
            Conexion::contextoAuditoria((int)$cuentaActual['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $row = $this->practicas->porIdParaActualizar($id);
            if (!$row) throw new DomainException('La práctica no existe.');
            $requiere = $this->catalogos->puedeTransicionar(
                'practica',
                (int)$row['id_estado'],
                $destino,
                $this->catalogos->rolId((string)$cuentaActual['rol_codigo']),
            );
            if ($requiere === null) throw new DomainException('La transición no está permitida.');
            if ($destino === $this->catalogos->estadoId('practica', 'publicada') && $row['fecha_limite_postulacion'] < date('Y-m-d')) {
                throw new DomainException('La fecha límite ya venció y la práctica no puede publicarse.');
            }
            $this->validarComentario($comentario, $requiere);
            $this->practicas->cambiarEstado($id, $destino, (int)$cuentaActual['id_usuario'], $comentario);
            $this->practicas->registrarHistorial($id, (int)$row['id_estado'], $destino, (int)$cuentaActual['id_usuario'], $comentario);
        });
        Respuesta::exito(['id_practica' => $id], (new ConfiguracionRepo())->mensaje('exito_guardado', 'Revisión guardada.'));
    }
    private function cerrarPractica(int $id, ServicioCierre $cierre): never {
        $cuentaActual = Acceso::requerirRol(['empresa', 'admin', 'superadmin']);
        if ($cuentaActual['rol_codigo'] === 'empresa') {
            Acceso::requerirPermiso('practicas.cerrar');
            Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        } else Acceso::requerirPermiso('practicas.cerrar');
        $cierre->cerrar($id, $cuentaActual, (string)$cuentaActual['rol_codigo']);
        Respuesta::exito(['id_practica' => $id], (new ConfiguracionRepo())->mensaje('exito_cierre', 'Práctica cerrada.'));
    }
    private function datosPractica(?array $vigente = null): array {
        $errores = [];
        $texto = function(string $campo, int $maximo, bool $obligatorio = false) use (&$errores): ?string {
            $valor = $this->datos[$campo] ?? null;
            if ($valor === null || !is_scalar($valor)) {
                if ($obligatorio) $errores[$campo] = 'Este campo es obligatorio.';
                return null;
            }
            $valor = trim((string)$valor);
            if ($valor === '' && $obligatorio) $errores[$campo] = 'Este campo es obligatorio.';
            elseif (mb_strlen($valor) > $maximo) $errores[$campo] = "No puede superar $maximo caracteres.";
            return $valor === '' ? null : $valor;
        };
        $entero = function(string $campo) use (&$errores): ?int {
            $valor = $this->datos[$campo] ?? null;
            if ($valor === null || $valor === '') {
                $errores[$campo] = $campo === 'cupo_maximo'
                    ? 'Este campo es obligatorio.'
                    : 'Selecciona un valor válido.';
                return null;
            }
            if (filter_var($valor, FILTER_VALIDATE_INT) === false) {
                $errores[$campo] = $campo === 'cupo_maximo'
                    ? 'Ingresa solo números enteros.'
                    : 'Selecciona un valor válido.';
                return null;
            }
            if ((int)$valor < 1) {
                $errores[$campo] = $campo === 'cupo_maximo'
                    ? 'Ingresa un número entero mayor que cero.'
                    : 'Selecciona un valor válido.';
                return null;
            }
            return (int)$valor;
        };
        $area = $entero('id_area');
        $modalidad = $entero('id_modalidad');
        if ($area !== null && !$this->catalogos->idActivo('areas', $area)) $errores['id_area'] = 'Selecciona un área activa.';
        if ($modalidad !== null && !$this->catalogos->idActivo('modalidades', $modalidad)) $errores['id_modalidad'] = 'Selecciona una modalidad activa.';
        $titulo = $texto('titulo', 180, true);
        $descripcion = $texto('descripcion', 10000, true);
        $requisitos = $texto('requisitos', 10000);
        $ubicacion = $texto('ubicacion', 255);
        $inicio = $texto('fecha_inicio', 10, true);
        $fecha = $texto('fecha_limite_postulacion', 10, true);
        foreach (['fecha_inicio' => $inicio, 'fecha_limite_postulacion' => $fecha] as $campo => $valor) {
            if ($valor === null) continue;
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
            $esFechaValida = $dt && $dt->format('Y-m-d') === $valor;
            if (!$esFechaValida) $errores[$campo] = 'Ingresa una fecha válida.';
            elseif ($valor < date('Y-m-d') && !($campo === 'fecha_inicio' && $vigente && $valor === $vigente['fecha_inicio'])) $errores[$campo] = 'La fecha no puede estar en el pasado.';
        }
        if ($inicio !== null && $fecha !== null) {
            $inicioObj = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio);
            $limiteObj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if ($inicioObj && $limiteObj && $inicioObj->format('Y-m-d') === $inicio && $limiteObj->format('Y-m-d') === $fecha && $inicio < $fecha) {
                $errores['fecha_inicio'] = 'La fecha de inicio debe ser igual o posterior al último día para postularse.';
            }
        }
        if ($fecha !== null && $fecha <= date('Y-m-d')) $errores['fecha_limite_postulacion'] = 'La fecha límite debe ser posterior a hoy.';
        $cupo = $entero('cupo_maximo');
        $maxCupo = (int)(new ConfiguracionRepo())->obtener('cupo_maximo_permitido', '5000');
        if ($cupo !== null && $cupo > $maxCupo) $errores['cupo_maximo'] = 'El cupo supera el máximo permitido.';
        if ($errores) Respuesta::error('Corrige los campos marcados.', 422, $errores);
        return [
            'area' => $area,
            'modalidad' => $modalidad,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'requisitos' => $requisitos,
            'ubicacion' => $ubicacion,
            'inicio' => $inicio,
            'cupo' => $cupo,
            'fecha' => $fecha
        ];
    }
    private function paginacionPracticas(): array {
        $limite = 10;
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        return ['limite' => $limite, 'pagina' => $pagina, 'offset' => ($pagina - 1)*$limite];
    }
}
