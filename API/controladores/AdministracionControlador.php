<?php
declare(strict_types=1);
/** Catálogos, configuración y bitácora. */
final class AdministracionControlador extends ControladorBase {
    public function rutasAdmin(): never {
        $segmentosRuta = array_slice($this->ruta, 1);
        $cuentaActual = Acceso::requerirRol(['admin', 'superadmin']);
        if (($segmentosRuta[0] ?? '') === 'usuarios') {
            (new UsuariosControlador($this->ruta, $this->metodo, $this->datos))->adminUsuarios($cuentaActual, array_slice($segmentosRuta, 1));
        }
        if (($segmentosRuta[0] ?? '') === 'empresas') {
            $this->adminEmpresas($cuentaActual, array_slice($segmentosRuta, 1));
        }
        if (($segmentosRuta[0] ?? '') === 'catalogos') {
            $this->adminCatalogos($cuentaActual, array_slice($segmentosRuta, 1));
        }
        if (($segmentosRuta[0] ?? '') === 'configuracion') {
            $this->adminConfiguracion($cuentaActual, array_slice($segmentosRuta, 1));
        }
        if (($segmentosRuta[0] ?? '') === 'bitacora' && $this->metodo === 'GET') {
            Acceso::requerirPermiso('bitacora.consultar');
            $p = $this->paginacion();
            $f = [];
            foreach (['tabla', 'accion', 'usuario_id', 'desde', 'hasta'] as $k) if (isset($_GET[$k])) $f[$k] = (string)$_GET[$k];
            Respuesta::exito(['elementos' => (new BitacoraRepo())->listar($f, $p['limite'], $p['offset']), 'pagina' => $p['pagina'], 'por_pagina' => $p['limite']]);
        }
        if (($segmentosRuta[0] ?? '') === 'reportes' && $this->metodo === 'GET') {
            Acceso::requerirPermiso('reportes.consultar');
            Respuesta::exito($this->datosReporte());
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    private function adminCatalogos(array $cuentaActual, array $segmentosRuta): never {
        Acceso::requerirPermiso('catalogos.gestionar');
        $catalogo = (string)($segmentosRuta[0] ?? '');
        $id = (int)($segmentosRuta[1] ?? 0);
        $accion = $segmentosRuta[2] ?? '';
        if (in_array($catalogo, ['roles', 'permisos', 'tipos_entidad'], true)) throw new DomainException('Este catálogo es estructural y no se puede modificar desde esta operación.');
        if (in_array($catalogo, ['estados', 'transiciones'], true) && $cuentaActual['rol_codigo'] !== 'superadmin') Respuesta::error('Solo superadministración puede modificar estados y transiciones.', 403);
        if ($this->metodo === 'GET' && $catalogo !== '') {
            $accion = ($_GET['eliminados'] ?? '') === '1' ? 'listar_eliminados': 'listar';
            Respuesta::exito($this->catalogos->administrar($catalogo, $accion, [], null, (int)$cuentaActual['id_usuario']));
        }
        if ($this->metodo === 'POST' && $catalogo !== '') {
            $idNuevo = $this->catalogos->administrar($catalogo, 'crear', $this->datos, (int)$cuentaActual['id_usuario'], (int)$cuentaActual['id_usuario']);
            Respuesta::exito(['id' => $idNuevo], 'Catálogo creado.', 201);
        }
        if ($this->metodo === 'PUT' && $id > 0 && $catalogo !== '') {
            $this->catalogos->administrar($catalogo, 'actualizar', $this->datos, $id, (int)$cuentaActual['id_usuario']);
            Respuesta::exito(['id' => $id], 'Catálogo actualizado.');
        }
        if (in_array($this->metodo, ['DELETE', 'POST'], true) && $id > 0 && $catalogo !== '' && in_array($accion, ['eliminar', 'restaurar'], true)) {
            $n = $this->catalogos->administrar($catalogo, $accion, [], $id, (int)$cuentaActual['id_usuario']);
            Respuesta::exito(['id' => $id, 'actualizados' => $n], 'Operación completada.');
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    private function adminConfiguracion(array $cuentaActual, array $segmentosRuta): never {
        Acceso::requerirPermiso('configuracion.gestionar');
        $repo = new ConfiguracionRepo();
        if ($this->metodo === 'GET' && $segmentosRuta === []) Respuesta::exito($repo->listar());
        if ($this->metodo === 'PUT' && isset($segmentosRuta[0])) {
            $clave = $segmentosRuta[0];
            $valor = $this->texto('valor', 5000, true);
            $repo->guardar($clave, $valor, (int)$cuentaActual['id_usuario']);
            Respuesta::exito(['clave' => $clave, 'valor' => $valor], (new ConfiguracionRepo())->mensaje('exito_guardado', 'Configuración actualizada.'));
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }

    public function adminEmpresas(array $cuentaActual, array $segmentosRuta): never {
        Acceso::requerirRol(['admin', 'superadmin']);
        Acceso::requerirPermiso('empresas.revisar');
        $id = (int)($segmentosRuta[0] ?? 0);
        if ($segmentosRuta === [] && $this->metodo === 'GET') Respuesta::exito($this->practicas->empresasPendientes());
        if ($id > 0 && ($segmentosRuta[1] ?? '') === 'revision' && $this->metodo === 'PUT') {
            $empresa = $this->empresas->porId($id);
            if (!$empresa) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la empresa.'), 404);
            $estado = $this->texto('estado_codigo', 40, true);
            $destino = $this->catalogos->estadoId('empresa', $estado);
            $comentario = $this->texto('comentario', 2000, false);
            $this->usuarios->transaccion(function()use($cuentaActual, $empresa, $destino, $comentario, $estado) {
                $actual = (int)$empresa['id_estado_revision'];
                $requiere = $this->catalogos->puedeTransicionar('empresa', $actual, $destino, $this->catalogos->rolId((string)$cuentaActual['rol_codigo']));
                if ($requiere === null) throw new DomainException('La transición de revisión no está permitida.');
                $this->validarComentario($comentario, $requiere);
                $this->empresas->revisar((int)$empresa['id_empresa'], $destino, (int)$cuentaActual['id_usuario'], $comentario);
                $this->empresas->registrarHistorial((int)$empresa['id_empresa'], $actual, $destino, (int)$cuentaActual['id_usuario'], $comentario);
                if ($estado === 'aprobada') {
                    $cuenta = $this->usuarios->detalleAdmin((int)$empresa['id_usuario']);
                    if (!$cuenta) throw new DomainException('No se encontró la cuenta asociada a la empresa.');
                    $pendienteUsuario = $this->catalogos->estadoId('usuario', 'pendiente');
                    if ((int)$cuenta['id_estado'] === $pendienteUsuario) {
                        $activoUsuario = $this->catalogos->estadoId('usuario', 'activo');
                        $this->usuarios->cambiarEstado((int)$empresa['id_usuario'], $activoUsuario, (int)$cuentaActual['id_usuario']);
                        $this->usuarios->registrarHistorial((int)$empresa['id_usuario'], $pendienteUsuario, $activoUsuario, (int)$cuentaActual['id_usuario'], 'Empresa aprobada; cuenta activada.');
                    }
                }
            });
            Respuesta::exito(['id_empresa' => $id], (new ConfiguracionRepo())->mensaje('exito_guardado', 'Revisión empresarial guardada.'));
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
}
